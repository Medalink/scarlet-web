//! The open-loop run: installs arrive on a schedule whether or not the server keeps up, workers run each one through
//! the real protocol, and a poller watches the status route until every session is decoded.

use crate::client::{Api, Identity, now_seconds};
use crate::stats::{Stats, cpu_seconds};
use crate::template::{Part, Plan, Rng, Source, Template, instantiate};
use scarlet_part::pseudo::hex;
use scarlet_part::wire::{compress_part, set_digest, split_requests};
use serde_json::{Value, json};
use sha2::{Digest, Sha256};
use std::collections::BTreeSet;
use std::io::Write;
use std::sync::mpsc;
use std::sync::{Arc, Mutex};
use std::thread;
use std::time::{Duration, Instant};

pub struct Settings {
    pub rate: f64,
    pub ramp_to: Option<f64>,
    pub duration: f64,
    pub concurrency: usize,
    pub characters: usize,
    pub hours: f64,
    pub spread_days: i64,
    pub sources: Vec<String>,
    pub salt: String,
    pub offset: u64,
    pub poll_rate: f64,
    pub drain_seconds: f64,
    pub poll: bool,
    pub retries: u32,
    pub log: Option<String>,
    pub dry_run: bool,
}

struct Job {
    index: u64,
    due: Instant,
}

struct Watch {
    identity: Arc<Identity>,
    expected_sessions: usize,
    done_at: Instant,
    due_poll: Instant,
}

fn name_for(salt: &str, index: u64, character: usize) -> String {
    let letter = char::from(b'a' + u8::try_from(character % 26).unwrap_or(0));
    format!("Lg{salt}{index:x}{letter}-Load")
}

fn seed_for(salt: &str, index: u64, extra: &str) -> [u8; 32] {
    Sha256::digest(format!("scarlet-loadgen\n{salt}\n{index}\n{extra}").as_bytes()).into()
}

fn arrival_times(settings: &Settings) -> Vec<f64> {
    let mut times = Vec::new();
    let end = settings.duration;
    let (a, b) = (
        settings.rate / 3600.0,
        settings.ramp_to.unwrap_or(settings.rate) / 3600.0,
    );
    let mut t = 0.0;
    while t < end {
        times.push(t);
        let rate_now = (a + (b - a) * (t / end)).max(1e-9);
        t += 1.0 / rate_now;
    }
    times
}

struct Outcome {
    failed: Option<String>,
    expected_sessions: usize,
    history_seconds: i64,
    parts: usize,
    request_bytes: usize,
    verified: bool,
}

fn build(template: &Template, settings: &Settings, index: u64, character: usize, now: i64) -> Plan {
    let longest = template
        .sources
        .iter()
        .max_by(|a, b| a.hours().total_cmp(&b.hours()))
        .map(|s| s.label.clone());
    let pool: Vec<&Source> = template
        .sources
        .iter()
        .filter(|s| {
            if settings.sources.is_empty() {
                Some(&s.label) == longest.as_ref()
            } else {
                settings.sources.contains(&s.label)
            }
        })
        .collect();
    let mut rng = Rng::new(u64::from_le_bytes(
        seed_for(&settings.salt, index, &character.to_string())[..8]
            .try_into()
            .unwrap_or([0; 8]),
    ));
    let source = pool[rng.below(pool.len() as u64) as usize];
    instantiate(
        source,
        &name_for(&settings.salt, index, character),
        settings.hours,
        settings.spread_days,
        now,
        &mut rng,
    )
}

fn group_list(parts: &[Part]) -> Vec<String> {
    parts
        .iter()
        .map(|p| p.group.clone())
        .collect::<BTreeSet<_>>()
        .into_iter()
        .collect()
}

fn check(
    reply: &crate::client::Reply,
    stats: &Stats,
    endpoint: &'static str,
    wanted: &[u16],
) -> Result<Value, String> {
    stats.record(endpoint, reply.status, reply.ms, reply.sent);
    if !wanted.contains(&reply.status) {
        let text = String::from_utf8_lossy(&reply.body);
        return Err(format!(
            "{endpoint} {} {}",
            reply.status,
            text.chars().take(160).collect::<String>()
        ));
    }
    serde_json::from_slice(&reply.body).map_err(|e| format!("{endpoint}: bad JSON ({e})"))
}

struct Call<'a> {
    endpoint: &'static str,
    wanted: &'a [u16],
    method: &'a str,
    path: &'a str,
    who: Option<&'a Identity>,
    content_type: &'a str,
    body: &'a [u8],
}

fn attempt(api: &Api, stats: &Stats, retries: u32, call: &Call) -> Result<Value, String> {
    let mut last = String::new();
    for n in 0..=retries {
        let reply = api.call(
            call.method,
            call.path,
            call.who,
            call.content_type,
            call.body,
        );
        let retry = reply.status == 0 || reply.status >= 500;
        match check(&reply, stats, call.endpoint, call.wanted) {
            Ok(v) => return Ok(v),
            Err(e) => last = e,
        }
        if !retry || n == retries {
            break;
        }
        thread::sleep(Duration::from_millis(1100));
    }
    Err(last)
}

fn upload(
    api: &Api,
    stats: &Stats,
    settings: &Settings,
    who: &Identity,
    plan: &Plan,
) -> Result<(usize, bool), String> {
    let groups = group_list(&plan.parts);
    let digest = hex(&set_digest(plan.parts.iter().map(|p| &p.id)));
    let sync = json!({"character": plan.name, "groups": groups, "count": plan.parts.len(), "digest": digest}).to_string();
    let mut sent = 0;
    let a = attempt(
        api,
        stats,
        settings.retries,
        &Call {
            endpoint: "sync",
            wanted: &[200],
            method: "POST",
            path: "/api/v1/upload/sync",
            who: Some(who),
            content_type: "application/json",
            body: sync.as_bytes(),
        },
    )?;
    if a["match"] == json!(true) {
        return Ok((0, true));
    }
    let entries: Vec<Value> = plan.parts.iter().map(|p| json!({"id": hex(&p.id), "session": p.session, "group": p.group, "lines": p.lines, "raw_bytes": p.text.len()})).collect();
    let manifest = json!({"character": plan.name, "groups": groups, "parts": entries}).to_string();
    let body = compress_part(&manifest)?;
    sent += body.len();
    let m = attempt(
        api,
        stats,
        settings.retries,
        &Call {
            endpoint: "manifest",
            wanted: &[200],
            method: "POST",
            path: "/api/v1/upload/manifest",
            who: Some(who),
            content_type: "application/vnd.scarlet.manifest+zstd",
            body: &body,
        },
    )?;
    let need: BTreeSet<String> = m["need"]
        .as_array()
        .map(|a| {
            a.iter()
                .filter_map(|v| v.as_str().map(str::to_string))
                .collect()
        })
        .unwrap_or_default();
    let wanted: Vec<&Part> = plan
        .parts
        .iter()
        .filter(|p| need.contains(&hex(&p.id)))
        .collect();
    let texts: Vec<&str> = wanted.iter().map(|p| p.text.as_str()).collect();
    for (body, _) in split_requests(&texts)? {
        sent += body.len();
        let r = attempt(
            api,
            stats,
            settings.retries,
            &Call {
                endpoint: "parts",
                wanted: &[200],
                method: "POST",
                path: "/api/v1/upload/parts",
                who: Some(who),
                content_type: "application/vnd.scarlet.parts+zstd",
                body: &body,
            },
        )?;
        if r["stored"].as_array().is_none() {
            return Err("parts: no stored list".into());
        }
    }
    let b = attempt(
        api,
        stats,
        settings.retries,
        &Call {
            endpoint: "verify",
            wanted: &[200],
            method: "POST",
            path: "/api/v1/upload/sync",
            who: Some(who),
            content_type: "application/json",
            body: format!("{sync}\n").as_bytes(),
        },
    )?;
    Ok((sent, b["match"] == json!(true)))
}

fn run_install(
    api: Option<&Api>,
    stats: &Stats,
    template: &Template,
    settings: &Settings,
    index: u64,
) -> (Outcome, Option<Arc<Identity>>) {
    let mut outcome = Outcome {
        failed: None,
        expected_sessions: 0,
        history_seconds: 0,
        parts: 0,
        request_bytes: 0,
        verified: true,
    };
    let mut who = Identity::from_seed(seed_for(&settings.salt, index, "key"));
    let now = now_seconds();
    let plans: Vec<Plan> = (0..settings.characters)
        .map(|c| build(template, settings, index, c, now))
        .collect();
    outcome.parts = plans.iter().map(|p| p.parts.len()).sum();
    outcome.expected_sessions = plans.iter().map(|p| p.sessions).sum();
    outcome.history_seconds = plans.iter().map(|p| p.seconds).sum();
    if settings.dry_run {
        outcome.request_bytes = plans
            .iter()
            .map(|p| p.parts.iter().map(|x| x.text.len()).sum::<usize>())
            .sum();
        return (outcome, None);
    }
    let Some(api) = api else {
        outcome.failed = Some("no target".into());
        return (outcome, None);
    };
    let register =
        json!({"public_key": who.public_key(), "name": format!("loadgen {index}")}).to_string();
    match attempt(
        api,
        stats,
        settings.retries,
        &Call {
            endpoint: "register",
            wanted: &[200, 201],
            method: "POST",
            path: "/api/v1/installs",
            who: None,
            content_type: "application/json",
            body: register.as_bytes(),
        },
    ) {
        Ok(v) => who.id = v["install"]["id"].as_str().unwrap_or_default().to_string(),
        Err(e) => {
            outcome.failed = Some(e);
            return (outcome, None);
        }
    }
    for plan in &plans {
        match upload(api, stats, settings, &who, plan) {
            Ok((sent, verified)) => {
                outcome.request_bytes += sent;
                outcome.verified &= verified;
            }
            Err(e) => {
                outcome.failed = Some(e);
                break;
            }
        }
    }
    (outcome, Some(Arc::new(who)))
}

fn poll_loop(
    api: &Api,
    stats: &Stats,
    settings: &Settings,
    rx: mpsc::Receiver<Watch>,
    stop: &Mutex<bool>,
    log: &Mutex<Option<std::fs::File>>,
) {
    let mut pending: Vec<Watch> = Vec::new();
    let gap = Duration::from_secs_f64(1.0 / settings.poll_rate.max(0.1));
    let drain_until = Mutex::new(None::<Instant>);
    loop {
        while let Ok(w) = rx.try_recv() {
            pending.push(w);
        }
        let finished = *stop.lock().unwrap_or_else(|e| e.into_inner());
        if finished {
            let mut until = drain_until.lock().unwrap_or_else(|e| e.into_inner());
            let limit = *until.get_or_insert_with(|| {
                Instant::now() + Duration::from_secs_f64(settings.drain_seconds)
            });
            if pending.is_empty() || Instant::now() > limit {
                stats.undecoded_at_end(pending.len());
                return;
            }
        }
        let Some(position) = pending.iter().position(|w| w.due_poll <= Instant::now()) else {
            thread::sleep(Duration::from_millis(200));
            continue;
        };
        let mut watch = pending.swap_remove(position);
        let reply = api.call(
            "GET",
            "/api/v1/upload/status",
            Some(&watch.identity),
            "",
            b"",
        );
        stats.record("status", reply.status, reply.ms, 0);
        let mut done = false;
        if let (200, Ok(v)) = (reply.status, serde_json::from_slice::<Value>(&reply.body)) {
            let rows = v["characters"].as_array().cloned().unwrap_or_default();
            let waiting: i64 = rows
                .iter()
                .map(|r| r["sessions_waiting"].as_i64().unwrap_or(0))
                .sum();
            let sessions: usize = rows
                .iter()
                .map(|r| r["sessions"].as_u64().unwrap_or(0) as usize)
                .sum();
            done = waiting == 0 && sessions >= watch.expected_sessions;
        }
        if done {
            let latency = watch.done_at.elapsed().as_secs_f64();
            stats.decoded(latency);
            if let Some(file) = log.lock().unwrap_or_else(|e| e.into_inner()).as_mut() {
                let _ = writeln!(
                    file,
                    "{}",
                    json!({"install": watch.identity.id, "decoded_after_s": latency})
                );
            }
        } else {
            watch.due_poll = Instant::now() + Duration::from_secs(5);
            pending.push(watch);
        }
        thread::sleep(gap);
    }
}

pub fn run(api: Option<Api>, template: Template, settings: Settings) -> Result<(), String> {
    let api = Arc::new(api);
    let template = Arc::new(template);
    let settings = Arc::new(settings);
    let stats = Arc::new(Stats::default());
    let log = Arc::new(Mutex::new(match &settings.log {
        Some(path) => Some(std::fs::File::create(path).map_err(|e| format!("{path}: {e}"))?),
        None => None,
    }));
    let times = arrival_times(&settings);
    println!(
        "loadgen: {} installs planned over {} s, {} characters each, {} h of history, {} workers{}",
        times.len(),
        settings.duration,
        settings.characters,
        settings.hours,
        settings.concurrency,
        if settings.dry_run {
            " (dry run, no requests)"
        } else {
            ""
        }
    );

    let (job_tx, job_rx) = mpsc::channel::<Job>();
    let job_rx = Arc::new(Mutex::new(job_rx));
    let (watch_tx, watch_rx) = mpsc::channel::<Watch>();
    let stop = Arc::new(Mutex::new(false));
    let cpu_start = cpu_seconds();
    let started = Instant::now();

    let mut workers = Vec::new();
    for _ in 0..settings.concurrency {
        let (api, template, settings, stats, job_rx, watch_tx, log) = (
            api.clone(),
            template.clone(),
            settings.clone(),
            stats.clone(),
            job_rx.clone(),
            watch_tx.clone(),
            log.clone(),
        );
        workers.push(thread::spawn(move || {
            loop {
                let job = match job_rx.lock().unwrap_or_else(|e| e.into_inner()).recv() {
                    Ok(job) => job,
                    Err(_) => return,
                };
                let late = Instant::now().saturating_duration_since(job.due).as_secs_f64();
                stats.started(late);
                let t0 = Instant::now();
                let (outcome, identity) = run_install(api.as_ref().as_ref(), &stats, &template, &settings, job.index);
                let seconds = t0.elapsed().as_secs_f64();
                stats.finished(outcome.failed.as_deref(), seconds, outcome.parts, outcome.request_bytes, outcome.verified);
                if let Some(file) = log.lock().unwrap_or_else(|e| e.into_inner()).as_mut() {
                    let _ = writeln!(file, "{}", json!({"index": job.index, "late_s": late, "seconds": seconds, "parts": outcome.parts, "sessions": outcome.expected_sessions, "history_hours": outcome.history_seconds as f64 / 3600.0, "bytes": outcome.request_bytes, "verified": outcome.verified, "failed": outcome.failed}));
                }
                if let (true, true, Some(identity)) = (settings.poll, outcome.failed.is_none(), identity) {
                    let _ = watch_tx.send(Watch { identity, expected_sessions: outcome.expected_sessions, done_at: Instant::now(), due_poll: Instant::now() + Duration::from_secs(2) });
                }
            }
        }));
    }
    drop(watch_tx);

    let poller = (!settings.dry_run).then(|| {
        let (api, stats, settings, stop, log) = (
            api.clone(),
            stats.clone(),
            settings.clone(),
            stop.clone(),
            log.clone(),
        );
        thread::spawn(move || {
            if let Some(api) = api.as_ref() {
                poll_loop(api, &stats, &settings, watch_rx, &stop, &log);
            }
        })
    });

    let reporter = {
        let (stats, stop) = (stats.clone(), stop.clone());
        thread::spawn(move || {
            let t0 = Instant::now();
            loop {
                thread::sleep(Duration::from_secs(10));
                if *stop.lock().unwrap_or_else(|e| e.into_inner()) {
                    return;
                }
                println!(
                    "loadgen: {:>5.0} s  {}",
                    t0.elapsed().as_secs_f64(),
                    stats.line()
                );
            }
        })
    };

    for (i, t) in times.iter().enumerate() {
        let due = started + Duration::from_secs_f64(*t);
        let now = Instant::now();
        if due > now {
            thread::sleep(due - now);
        }
        let _ = job_tx.send(Job {
            index: settings.offset + i as u64,
            due,
        });
    }
    drop(job_tx);
    for w in workers {
        let _ = w.join();
    }
    let sending_seconds = started.elapsed().as_secs_f64();
    *stop.lock().unwrap_or_else(|e| e.into_inner()) = true;
    if let Some(poller) = poller {
        let _ = poller.join();
    }
    let _ = reporter.join();
    let wall = started.elapsed().as_secs_f64();
    let cpu = cpu_seconds() - cpu_start;
    let summary = stats.summary(
        sending_seconds,
        wall,
        cpu,
        thread::available_parallelism().map_or(1, usize::from),
        &settings,
    );
    println!("SUMMARY {summary}");
    Ok(())
}
