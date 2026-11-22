use crate::run::Settings;
use serde_json::{Value, json};
use std::collections::BTreeMap;
use std::sync::Mutex;

#[derive(Default)]
struct Inner {
    started: u64,
    ok: u64,
    failed: u64,
    unverified: u64,
    late: Vec<f64>,
    seconds: Vec<f64>,
    latency: BTreeMap<&'static str, Vec<u32>>,
    statuses: BTreeMap<String, u64>,
    request_bytes: u64,
    parts: u64,
    reasons: BTreeMap<String, u64>,
    decoded: Vec<f64>,
    undecoded_at_end: usize,
}

#[derive(Default)]
pub struct Stats(Mutex<Inner>);

pub fn cpu_seconds() -> f64 {
    let Ok(stat) = std::fs::read_to_string("/proc/self/stat") else {
        return 0.0;
    };
    let after = stat.rsplit_once(')').map_or("", |(_, rest)| rest);
    let fields: Vec<&str> = after.split_whitespace().collect();
    let ticks = |i: usize| {
        fields
            .get(i)
            .and_then(|f| f.parse::<f64>().ok())
            .unwrap_or(0.0)
    };
    (ticks(11) + ticks(12)) / 100.0
}

fn percentile<T: Copy + PartialOrd + Default>(values: &mut [T], p: f64) -> T {
    if values.is_empty() {
        return T::default();
    }
    values.sort_by(|a, b| a.partial_cmp(b).unwrap_or(std::cmp::Ordering::Equal));
    values[((values.len() - 1) as f64 * p).round() as usize]
}

impl Stats {
    fn inner(&self) -> std::sync::MutexGuard<'_, Inner> {
        self.0.lock().unwrap_or_else(|e| e.into_inner())
    }

    pub fn record(&self, endpoint: &'static str, status: u16, ms: u32, sent: usize) {
        let mut s = self.inner();
        s.latency.entry(endpoint).or_default().push(ms);
        *s.statuses
            .entry(format!("{endpoint} {status}"))
            .or_default() += 1;
        s.request_bytes += sent as u64;
    }

    pub fn started(&self, late: f64) {
        let mut s = self.inner();
        s.started += 1;
        s.late.push(late);
    }

    pub fn finished(
        &self,
        failed: Option<&str>,
        seconds: f64,
        parts: usize,
        _bytes: usize,
        verified: bool,
    ) {
        let mut s = self.inner();
        s.seconds.push(seconds);
        s.parts += parts as u64;
        match failed {
            None => {
                s.ok += 1;
                if !verified {
                    s.unverified += 1;
                }
            }
            Some(reason) => {
                s.failed += 1;
                let key: String = reason.chars().take(90).collect();
                *s.reasons.entry(key).or_default() += 1;
            }
        }
    }

    pub fn decoded(&self, seconds: f64) {
        self.inner().decoded.push(seconds);
    }

    pub fn undecoded_at_end(&self, n: usize) {
        self.inner().undecoded_at_end = n;
    }

    pub fn line(&self) -> String {
        let s = self.inner();
        let limited: u64 = s
            .statuses
            .iter()
            .filter(|(k, _)| k.ends_with(" 429"))
            .map(|(_, v)| *v)
            .sum();
        let late = s.late.iter().filter(|l| **l > 1.0).count();
        format!(
            "started {} done {} failed {} in flight {} late(>1s) {} http429 {} decoded {}",
            s.started,
            s.ok,
            s.failed,
            s.started - s.ok - s.failed,
            late,
            limited,
            s.decoded.len()
        )
    }

    pub fn summary(
        &self,
        sending: f64,
        wall: f64,
        cpu: f64,
        cpus: usize,
        settings: &Settings,
    ) -> Value {
        let mut s = self.inner();
        let latency: BTreeMap<&str, Value> = s
            .latency
            .iter_mut()
            .map(|(k, v)| (*k, json!({"n": v.len(), "p50_ms": percentile(v, 0.5), "p95_ms": percentile(v, 0.95), "p99_ms": percentile(v, 0.99), "max_ms": percentile(v, 1.0)})))
            .collect();
        let late_count = s.late.iter().filter(|l| **l > 1.0).count();
        let (late_p95, late_max) = (percentile(&mut s.late, 0.95), percentile(&mut s.late, 1.0));
        let (install_p50, install_p95) = (
            percentile(&mut s.seconds, 0.5),
            percentile(&mut s.seconds, 0.95),
        );
        let (decode_p50, decode_p95, decode_max) = (
            percentile(&mut s.decoded, 0.5),
            percentile(&mut s.decoded, 0.95),
            percentile(&mut s.decoded, 1.0),
        );
        json!({
            "rate_per_hour": settings.rate, "ramp_to_per_hour": settings.ramp_to, "duration_s": settings.duration, "concurrency": settings.concurrency,
            "characters": settings.characters, "history_hours": settings.hours,
            "installs_started": s.started, "installs_ok": s.ok, "installs_failed": s.failed, "installs_unverified": s.unverified,
            "sending_s": sending, "wall_s": wall,
            "late_over_1s": late_count, "late_p95_s": late_p95, "late_max_s": late_max,
            "install_p50_s": install_p50, "install_p95_s": install_p95,
            "parts": s.parts, "request_bytes": s.request_bytes,
            "statuses": s.statuses, "failure_reasons": s.reasons, "latency": latency,
            "decoded": s.decoded.len(), "decode_p50_s": decode_p50, "decode_p95_s": decode_p95, "decode_max_s": decode_max, "undecoded_at_end": s.undecoded_at_end,
            "generator_cpu_s": cpu, "generator_cpu_pct": 100.0 * cpu / (wall * cpus as f64).max(1e-9), "generator_cpus": cpus,
        })
    }
}
