//! Checks the decoder against the companion: the decoded rows of every session
//! are summed the way docs/OVERVIEW.md counts, and compared with the session
//! rows of the character's `overview.json`.

use crate::decode::{RUN_REJOIN_MS, round_s};
use serde_json::Value;
use std::collections::BTreeMap;

#[derive(Default, Clone, PartialEq, Debug)]
struct Share {
    p: i64,
    x: [i64; 5],
    k: i64,
    dd: i64,
    q: i64,
}

#[derive(Default, Clone, PartialEq, Debug)]
struct ZShare {
    p: i64,
    k: i64,
    dd: i64,
    x: i64,
}

#[derive(Default)]
struct Row {
    p: i64,
    l0: i64,
    l1: i64,
    x: [i64; 5],
    k: i64,
    dd: i64,
    q: i64,
    r: i64,
    g: i64,
    s: i64,
    m: Option<i64>,
    z: i64,
    lv: BTreeMap<i64, Share>,
    zn: BTreeMap<String, ZShare>,
}

struct RunRec {
    inst: i64,
    entered: i64,
    left: Option<i64>,
}

const SOURCES: [&str; 5] = ["k", "d", "q", "e", "o"];

fn int(v: &Value, k: &str) -> i64 {
    v.get(k).and_then(Value::as_i64).unwrap_or(0)
}

fn opt(v: &Value, k: &str) -> Option<i64> {
    v.get(k).and_then(Value::as_i64)
}

/// Sum the rows of one session. Returns the row and its dungeon runs.
fn aggregate(ndjson: &str, notes: &mut Vec<String>) -> Result<(i64, Row, Vec<RunRec>), String> {
    let mut row = Row::default();
    let mut key = 0;
    let mut zone_names: BTreeMap<i64, Vec<String>> = BTreeMap::new();
    let mut vals: Vec<Value> = Vec::new();
    for line in ndjson.lines().filter(|l| !l.is_empty()) {
        let v: Value = serde_json::from_str(line).map_err(|e| format!("bad row: {e}"))?;
        if v["t"] == "zones"
            && let (Some(id), Some(name)) = (opt(&v, "id"), v["name"].as_str())
        {
            let names = zone_names.entry(id).or_default();
            if !names.iter().any(|n| n == name) {
                names.push(name.to_string());
            }
        }
        vals.push(v);
    }
    for (id, names) in &zone_names {
        if names.len() > 1 {
            notes.push(format!(
                "zone id {id} has {} names in one session",
                names.len()
            ));
        }
    }
    for names in zone_names.values() {
        for n in names {
            row.zn.entry(n.clone()).or_default();
        }
    }
    let zone_of = |v: &Value| -> Option<String> {
        let id = opt(v, "zone_id")?;
        zone_names.get(&id).and_then(|n| n.first().cloned())
    };
    let mut runs: Vec<(i64, RunRec)> = Vec::new();
    for v in &vals {
        let level = opt(v, "level");
        match v["t"].as_str().unwrap_or("") {
            "sessions" => {
                key = int(v, "session");
                row.p = int(v, "played_s");
                row.l0 = int(v, "level_start");
                row.l1 = int(v, "level_end");
                row.z = int(v, "zones");
                row.m = opt(v, "money_end");
            }
            "xp_gains" => {
                let src = v["source"].as_str().unwrap_or("o");
                let i = SOURCES.iter().position(|s| *s == src).unwrap_or(4);
                let a = int(v, "amount");
                row.x[i] += a;
                if let Some(l) = level {
                    row.lv.entry(l).or_default().x[i] += a;
                }
                if let Some(z) = zone_of(v) {
                    row.zn.entry(z).or_default().x += a;
                }
            }
            "kills" => {
                row.k += 1;
                if let Some(l) = level {
                    row.lv.entry(l).or_default().k += 1;
                }
                if let Some(z) = zone_of(v) {
                    row.zn.entry(z).or_default().k += 1;
                }
            }
            "deaths" => {
                row.dd += 1;
                if let Some(l) = level {
                    row.lv.entry(l).or_default().dd += 1;
                }
                if let Some(z) = zone_of(v) {
                    row.zn.entry(z).or_default().dd += 1;
                }
            }
            "quest_events" if v["event"] == "turned_in" => {
                row.q += 1;
                if let Some(l) = level {
                    row.lv.entry(l).or_default().q += 1;
                }
            }
            "money_changes" if v["src"] == "M" => {
                row.g += int(v, "earned");
                row.s += int(v, "spent");
            }
            "dungeon_runs" => runs.push((
                int(v, "index"),
                RunRec {
                    inst: int(v, "instance_id"),
                    entered: int(v, "at"),
                    left: opt(v, "left_at"),
                },
            )),
            _ => {}
        }
    }
    // Time: spans are summed per level and per zone, then rounded once.
    let mut lv_ms: BTreeMap<i64, i64> = BTreeMap::new();
    let mut zn_ms: BTreeMap<String, i64> = BTreeMap::new();
    for v in vals.iter().filter(|v| v["t"] == "play_spans") {
        let ms = int(v, "ms");
        if let Some(l) = opt(v, "level") {
            *lv_ms.entry(l).or_default() += ms;
        }
        if let Some(z) = v["zone"].as_str() {
            *zn_ms.entry(z.to_string()).or_default() += ms;
        }
    }
    for (l, ms) in lv_ms {
        row.lv.entry(l).or_default().p = round_s(ms);
    }
    for (z, ms) in zn_ms {
        row.zn.entry(z).or_default().p = round_s(ms);
    }
    runs.sort_by_key(|r| r.0);
    Ok((key, row, runs.into_iter().map(|r| r.1).collect()))
}

#[derive(Default)]
pub struct Report {
    pub sessions: usize,
    pub compared: usize,
    pub missing_in_overview: Vec<i64>,
    pub missing_in_parts: Vec<i64>,
    /// field -> (matches, mismatch lines)
    pub fields: BTreeMap<String, (usize, Vec<String>)>,
    pub notes: Vec<String>,
}

impl Report {
    fn check<T: PartialEq + std::fmt::Debug>(&mut self, t: i64, field: &str, mine: T, theirs: T) {
        let e = self.fields.entry(field.to_string()).or_default();
        if mine == theirs {
            e.0 += 1;
        } else {
            e.1.push(format!(
                "session {t} {field}: decoder {mine:?}, overview {theirs:?}"
            ));
        }
    }
}

fn share_of(v: &Value) -> Share {
    Share {
        p: int(v, "p"),
        x: [
            int(v, "xk"),
            int(v, "xd"),
            int(v, "xq"),
            int(v, "xe"),
            int(v, "xo"),
        ],
        k: int(v, "k"),
        dd: int(v, "dd"),
        q: int(v, "q"),
    }
}

/// `sessions` is each session's decoded rows (newline-delimited JSON) in session
/// order; `overview` is the text of the character's overview.json.
pub fn compare(sessions: &[String], overview: &str) -> Result<Report, String> {
    let ov: Value = serde_json::from_str(overview).map_err(|e| format!("overview.json: {e}"))?;
    let theirs: BTreeMap<i64, &Value> = ov["sessions"]
        .as_array()
        .map(|a| a.iter().map(|s| (int(s, "t"), s)).collect())
        .unwrap_or_default();
    let mut rep = Report::default();
    let mut mine: Vec<(i64, Row, Vec<RunRec>)> = Vec::new();
    for text in sessions {
        mine.push(aggregate(text, &mut rep.notes)?);
    }
    mine.sort_by_key(|m| m.0);
    // Runs join across sessions (ROWS.md, dungeon_runs).
    let (mut last_inst, mut last_left): (Option<i64>, Option<i64>) = (None, None);
    for (_, row, runs) in &mut mine {
        row.r = runs.len() as i64;
        for (i, run) in runs.iter().enumerate() {
            if i == 0
                && last_inst == Some(run.inst)
                && last_left.is_some_and(|l| run.entered - l < RUN_REJOIN_MS)
            {
                row.r -= 1;
            }
        }
        if let Some(last) = runs.last() {
            last_inst = Some(last.inst);
            last_left = last.left;
        }
    }
    rep.sessions = mine.len();
    for (t, row, _) in &mine {
        let Some(o) = theirs.get(t) else {
            rep.missing_in_overview.push(*t);
            continue;
        };
        rep.compared += 1;
        // The overview adds the reload before the next session (OVERVIEW.md, Played), up to the wall time
        // between sessions; the decoder reads one session alone. Within two minutes over is that credit.
        let theirs_p = int(o, "p");
        rep.check(
            *t,
            "played_s",
            row.p,
            if (0..=120).contains(&(theirs_p - row.p)) {
                row.p
            } else {
                theirs_p
            },
        );
        rep.check(*t, "level_start", row.l0, int(o, "l0"));
        rep.check(*t, "level_end", row.l1, int(o, "l1"));
        for (i, name) in [
            "xp_kill",
            "xp_dungeon",
            "xp_quest",
            "xp_explore",
            "xp_other",
        ]
        .iter()
        .enumerate()
        {
            rep.check(
                *t,
                name,
                row.x[i],
                int(o, ["xk", "xd", "xq", "xe", "xo"][i]),
            );
        }
        rep.check(*t, "kills", row.k, int(o, "k"));
        rep.check(*t, "deaths", row.dd, int(o, "dd"));
        rep.check(*t, "quests", row.q, int(o, "q"));
        rep.check(*t, "copper_earned", row.g, int(o, "g"));
        rep.check(*t, "copper_spent", row.s, int(o, "s"));
        rep.check(*t, "dungeon_runs", row.r, int(o, "r"));
        rep.check(*t, "zones", row.z, int(o, "z"));
        if let Some(m) = row.m {
            rep.check(*t, "balance_end", m, opt(o, "m").unwrap_or(-1));
        }
        let their_lv: BTreeMap<i64, Share> = o["lv"]
            .as_object()
            .map(|m| {
                m.iter()
                    .filter_map(|(k, v)| Some((k.parse().ok()?, share_of(v))))
                    .collect()
            })
            .unwrap_or_default();
        let my_lv: BTreeMap<i64, Share> = row
            .lv
            .iter()
            .filter(|(_, s)| **s != Share::default())
            .map(|(k, v)| (*k, v.clone()))
            .collect();
        rep.check(*t, "per_level_split", my_lv, their_lv);
        let their_zn: BTreeMap<String, ZShare> = o["zn"]
            .as_object()
            .map(|m| {
                m.iter()
                    .map(|(k, v)| {
                        (
                            k.clone(),
                            ZShare {
                                p: int(v, "p"),
                                k: int(v, "k"),
                                dd: int(v, "dd"),
                                x: int(v, "x"),
                            },
                        )
                    })
                    .collect()
            })
            .unwrap_or_default();
        let my_zn: BTreeMap<String, ZShare> =
            row.zn.iter().map(|(k, v)| (k.clone(), v.clone())).collect();
        rep.check(*t, "per_zone_split", my_zn, their_zn);
    }
    let mine_keys: Vec<i64> = mine.iter().map(|m| m.0).collect();
    rep.missing_in_parts = theirs
        .keys()
        .filter(|t| !mine_keys.contains(t))
        .copied()
        .collect();
    Ok(rep)
}
