//! Boss kills of one session: the per-kill list that `boss_kills` and the bosses of a dungeon run both read
//! (docs/ROWS.md).
//!
//! A kill is a `B` line, or a won `E` end that no `B` of its encounter stands beside. Scarlet's own addon writes both
//! lines for one kill and another recorder's backport only the `B`, so each kill is in the list once whichever
//! lines came.

use crate::decode::{field, num, prefix};
use crate::rows::Rows;

const SAME_KILL_MS: i64 = 5_000;
const WIPE_B_MS: i64 = 1_000;
const NAME_CHARS: usize = 128;

fn encounter(f: &[&str], i: usize) -> Option<i64> {
    num(f, i).filter(|id| (1..=i64::from(i32::MAX)).contains(id))
}

#[derive(Clone, Copy)]
pub(crate) struct Seen {
    pub at: i64,
    pub level: Option<i64>,
    pub zone_id: Option<i64>,
    pub run: Option<usize>,
}

struct Boss {
    seen: Seen,
    id: Option<i64>,
    name: String,
}

struct End {
    seen: Seen,
    id: i64,
    won: bool,
}

pub(crate) struct Kill {
    seen: Seen,
    id: Option<i64>,
    name: String,
    source: &'static str,
}

impl Kill {
    pub(crate) fn run(&self) -> Option<usize> {
        self.seen.run
    }

    pub(crate) fn boss(&self) -> (Option<i64>, String) {
        match self.id {
            Some(id) => (Some(id), String::new()),
            None => (None, self.name.clone()),
        }
    }

    pub(crate) fn write(&self, session: i64, instance_id: Option<i64>, w: &mut Rows) {
        let s = &self.seen;
        prefix(w, "boss_kills", session, s.at, s.level, s.zone_id);
        w.opt("encounter_id", self.id)
            .text("name", &self.name)
            .opt("instance_id", instance_id)
            .str("source", self.source);
        w.end();
    }
}

#[derive(Default)]
pub(crate) struct Bosses {
    bosses: Vec<Boss>,
    ends: Vec<End>,
}

impl Bosses {
    pub(crate) fn on_b(&mut self, seen: Seen, f: &[&str]) {
        self.bosses.push(Boss {
            seen,
            id: encounter(f, 0),
            name: field(f, 1).chars().take(NAME_CHARS).collect(),
        });
    }

    pub(crate) fn on_e(&mut self, seen: Seen, f: &[&str]) {
        if field(f, 0) != "0" {
            return;
        }
        if let Some(id) = encounter(f, 1) {
            self.ends.push(End {
                seen,
                id,
                won: field(f, 2) == "1",
            });
        }
    }

    pub(crate) fn finish(self) -> Vec<Kill> {
        let Bosses { bosses, ends } = self;
        let near = |a: i64, b: i64| (a - b).abs() < SAME_KILL_MS;
        let won_near = |id: i64, at: i64| {
            ends.iter()
                .any(|e| e.won && e.id == id && near(e.seen.at, at))
        };

        let mut lines: Vec<Boss> = Vec::new();
        for mut b in bosses {
            let lost_just_before = |id: i64| {
                ends.iter().any(|e| {
                    !e.won && e.id == id && (0..WIPE_B_MS).contains(&(b.seen.at - e.seen.at))
                })
            };
            if b.id
                .is_some_and(|id| lost_just_before(id) && !won_near(id, b.seen.at))
            {
                continue;
            }
            if b.id.is_none() {
                b.id = ends
                    .iter()
                    .filter(|e| e.won && near(e.seen.at, b.seen.at))
                    .min_by_key(|e| (e.seen.at - b.seen.at).abs())
                    .map(|e| e.id);
            }
            lines.push(b);
        }

        let mut kills: Vec<Kill> = Vec::new();
        let mut last: Option<(i64, Option<i64>, &str)> = None;
        for b in &lines {
            let key = (b.seen.at, b.id, b.name.as_str());
            if last.is_some_and(|(at, id, name)| {
                id == b.id && (b.id.is_some() || name == b.name) && b.seen.at - at < SAME_KILL_MS
            }) {
                continue;
            }
            last = Some(key);
            kills.push(Kill {
                seen: b.seen,
                id: b.id,
                name: b.name.clone(),
                source: "B",
            });
        }
        for e in ends.iter().filter(|e| e.won) {
            let beside = lines
                .iter()
                .any(|b| b.id == Some(e.id) && near(b.seen.at, e.seen.at));
            if !beside {
                kills.push(Kill {
                    seen: e.seen,
                    id: Some(e.id),
                    name: String::new(),
                    source: "E",
                });
            }
        }
        kills.sort_by_key(|k| k.seen.at);
        kills
    }
}
