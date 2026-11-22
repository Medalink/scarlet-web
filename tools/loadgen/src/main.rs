mod client;
mod run;
mod stats;
mod template;

use run::Settings;
use std::collections::BTreeMap;
use std::ffi::OsString;
use std::path::Path;
use std::process::ExitCode;

const USAGE: &str = "usage:
  loadgen prepare --root <companion out dir> --folders a,b,c --out <template file>
  loadgen info --template <file>
  loadgen check --template <file>
  loadgen run --target https://scarlet-load-app --template <file> [--ca <cert.pem>] [--rate <installs per hour>] [--ramp-to <installs per hour>]
              [--duration <s>] [--concurrency <n>] [--characters <n>] [--history-hours <h>] [--spread-days <n>] [--sources A,B]
              [--salt <text>] [--offset <n>] [--poll-rate <requests/s>] [--drain-seconds <s>] [--no-poll] [--retries <n>] [--log <file>] [--dry-run]";

type Flags = BTreeMap<String, String>;

fn flags(args: Vec<OsString>, names: &[&str], bools: &[&str]) -> Result<Flags, String> {
    use lexopt::prelude::*;
    let mut parser = lexopt::Parser::from_args(args);
    let mut map = Flags::new();
    while let Some(arg) = parser.next().map_err(|e| e.to_string())? {
        let long = match &arg {
            Long(n) => Some(n.to_string()),
            _ => None,
        };
        match long {
            Some(n) if names.contains(&n.as_str()) => {
                let v = parser.value().map_err(|e| e.to_string())?;
                map.insert(n, v.string().map_err(|e| e.to_string())?);
            }
            Some(n) if bools.contains(&n.as_str()) => {
                map.insert(n, String::new());
            }
            _ => return Err(arg.unexpected().to_string()),
        }
    }
    Ok(map)
}

fn need<'a>(f: &'a Flags, name: &str) -> Result<&'a str, String> {
    f.get(name)
        .map(String::as_str)
        .ok_or_else(|| format!("missing --{name}"))
}

fn number<T: std::str::FromStr>(f: &Flags, name: &str, default: T) -> Result<T, String> {
    f.get(name).map_or(Ok(default), |v| {
        v.parse().map_err(|_| format!("bad --{name}"))
    })
}

fn info(path: &Path) -> Result<(), String> {
    let template = template::load(path)?;
    for s in &template.sources {
        let parts: usize = s.sessions.iter().map(|x| x.parts.len()).sum();
        let bytes: usize = s
            .sessions
            .iter()
            .flat_map(|x| x.parts.iter())
            .map(|(_, t)| t.len())
            .sum();
        println!(
            "source {}: {} sessions, {} parts, {} bytes of text, {:.1} h played over {:.1} days ({:.1} h per week)",
            s.label,
            s.sessions.len(),
            parts,
            bytes,
            s.hours(),
            s.span_days(),
            s.hours() / (s.span_days() / 7.0).max(1e-9)
        );
    }
    Ok(())
}

fn check(path: &Path) -> Result<(), String> {
    let template = template::load(path)?;
    let source = template
        .sources
        .iter()
        .max_by(|a, b| a.hours().total_cmp(&b.hours()))
        .ok_or("empty template")?;
    let marker = format!("@NAME@{}", source.label);
    let mut compared = 0;
    for session in source.sessions.iter().take(40) {
        let variant = |name: &str, delta: i64| -> Vec<String> {
            session
                .parts
                .iter()
                .map(|(group, text)| {
                    let body = text
                        .split_once('\n')
                        .map_or("", |(_, r)| r)
                        .replace(&marker, name);
                    format!(
                        "scarlet-part\t1\t{name}\t{}\t{group}\n{body}",
                        session.key + delta
                    )
                })
                .collect()
        };
        let (a, b) = (
            variant("Aaaa-Bbbb", 0),
            variant("Cccc-Dddd", 5 * template::DAY),
        );
        let (ra, rb): (Vec<&str>, Vec<&str>) = (
            a.iter().map(String::as_str).collect(),
            b.iter().map(String::as_str).collect(),
        );
        let (mut oa, mut ob) = (String::new(), String::new());
        let sa = scarlet_part::decode::decode_session(&ra, &mut oa)?;
        let sb = scarlet_part::decode::decode_session(&rb, &mut ob)?;
        if (sa.rows, sa.lines, sa.played_ms) != (sb.rows, sb.lines, sb.played_ms) {
            return Err(format!(
                "session {}: rows, lines or played time differ ({} {} {} against {} {} {})",
                session.key, sa.rows, sa.lines, sa.played_ms, sb.rows, sb.lines, sb.played_ms
            ));
        }
        compared += 1;
        if compared == 1 {
            println!(
                "session {}: {} rows, {} lines, {} ms played, identical in both; first row: {}",
                session.key,
                sa.rows,
                sa.lines,
                sa.played_ms,
                oa.lines().next().unwrap_or("")
            );
        }
    }
    println!(
        "check: {compared} sessions decode to the same row, line and play-time counts under another name and 5 days later"
    );
    Ok(())
}

fn run_command(f: &Flags) -> Result<(), String> {
    let template = template::load(Path::new(need(f, "template")?))?;
    let dry_run = f.contains_key("dry-run");
    let api = if dry_run {
        None
    } else {
        Some(client::Api::new(
            need(f, "target")?,
            f.get("ca").map(Path::new),
        )?)
    };
    let salt = f
        .get("salt")
        .cloned()
        .unwrap_or_else(|| format!("{:x}", client::now_seconds() % 0x0FFF_FFFF));
    let settings = Settings {
        rate: number(f, "rate", 600.0)?,
        ramp_to: f
            .get("ramp-to")
            .map(|v| v.parse())
            .transpose()
            .map_err(|_| "bad --ramp-to")?,
        duration: number(f, "duration", 60.0)?,
        concurrency: number(f, "concurrency", 32)?,
        characters: number(f, "characters", 1)?,
        hours: number(f, "history-hours", 3.0)?,
        spread_days: number(f, "spread-days", 7)?,
        sources: f
            .get("sources")
            .map(|s| s.split(',').map(str::to_string).collect())
            .unwrap_or_default(),
        salt,
        offset: number(f, "offset", 0)?,
        poll_rate: number(f, "poll-rate", 20.0)?,
        drain_seconds: number(f, "drain-seconds", 600.0)?,
        poll: !f.contains_key("no-poll"),
        retries: number(f, "retries", 1)?,
        log: f.get("log").cloned(),
        dry_run,
    };
    run::run(api, template, settings)
}

fn main() -> ExitCode {
    let mut args: Vec<OsString> = std::env::args_os().skip(1).collect();
    if args.is_empty() {
        eprintln!("{USAGE}");
        return ExitCode::from(2);
    }
    let command = args.remove(0).to_string_lossy().into_owned();
    let result = match command.as_str() {
        "prepare" => flags(args, &["root", "folders", "out"], &[]).and_then(|f| {
            let folders: Vec<String> = need(&f, "folders")?
                .split(',')
                .map(str::to_string)
                .collect();
            template::prepare(
                Path::new(need(&f, "root")?),
                &folders,
                Path::new(need(&f, "out")?),
            )
        }),
        "info" => {
            flags(args, &["template"], &[]).and_then(|f| info(Path::new(need(&f, "template")?)))
        }
        "check" => {
            flags(args, &["template"], &[]).and_then(|f| check(Path::new(need(&f, "template")?)))
        }
        "run" => flags(
            args,
            &[
                "target",
                "template",
                "ca",
                "rate",
                "ramp-to",
                "duration",
                "concurrency",
                "characters",
                "history-hours",
                "spread-days",
                "sources",
                "salt",
                "offset",
                "poll-rate",
                "drain-seconds",
                "retries",
                "log",
            ],
            &["no-poll", "dry-run"],
        )
        .and_then(|f| run_command(&f)),
        _ => Err(USAGE.to_string()),
    };
    match result {
        Ok(()) => ExitCode::SUCCESS,
        Err(e) => {
            eprintln!("loadgen: {e}");
            ExitCode::FAILURE
        }
    }
}
