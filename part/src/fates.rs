//! The class tallies that follow one thing to its end: what happened to a seal, what happened to a totem, which
//! totems stood in a fight, and whether a death with a self-resurrect on offer ended in using it.

use crate::auras::{Inst, Recent};
use crate::class::{Now, id_or_zero, seconds_ms};
use crate::decode::num;
use crate::sets::Set;
use crate::tallies::Book;
use std::collections::{BTreeMap, VecDeque};

const JUDGED_MS: i64 = 500;
const REPLACED_MS: i64 = 500;
const RAN_OUT_MS: i64 = 1_000;
const GAINS_KEEP_MS: i64 = 1_000;
const FULL_TIME_SLACK_MS: i64 = 1_000;
const TOTEM_SLOTS: usize = 4;

const JUDGED: i64 = 1;
const REPLACED: i64 = 2;
const RAN_OUT: i64 = 3;
const OTHER: i64 = 4;
const FIGHT_ENDED: i64 = 5;
const STOOD: i64 = 1;
const GONE_EARLY: i64 = 3;

struct Track {
    now: Now,
    spell: i64,
    gain_at: i64,
    duration_ms: i64,
    fight: Option<i64>,
}

struct Ended {
    track: Track,
    at: i64,
    held_ms: i64,
}

fn settle(book: &mut Book, track: &Track, fate: i64, at: i64) {
    book.add(
        &track.now,
        "seal_fate",
        track.spell,
        fate,
        Some(at - track.gain_at),
    );
}

fn run_out_or_other(book: &mut Book, ended: &Ended) {
    let ran_out = ended.track.duration_ms > 0
        && (ended.held_ms - ended.track.duration_ms).abs() <= RAN_OUT_MS;
    let fate = if ran_out { RAN_OUT } else { OTHER };
    settle(book, &ended.track, fate, ended.at);
}

/// Each seal gained, followed to its fate.
#[derive(Default)]
pub struct Seals {
    open: BTreeMap<u64, Track>,
    ended: Vec<Ended>,
    gains: VecDeque<(i64, u64)>,
}

impl Seals {
    pub fn on_gain(&mut self, now: &Now, inst: &Inst, fight: Option<i64>, book: &mut Book) {
        let waiting = std::mem::take(&mut self.ended);
        for ended in waiting {
            if now.at <= ended.at + REPLACED_MS {
                settle(book, &ended.track, REPLACED, ended.at);
            } else {
                self.ended.push(ended);
            }
        }
        self.gains.push_back((now.at, inst.id));
        while self
            .gains
            .front()
            .is_some_and(|(at, _)| *at < now.at - GAINS_KEEP_MS)
        {
            self.gains.pop_front();
        }
        self.open.insert(
            inst.id,
            Track {
                now: *now,
                spell: inst.spell,
                gain_at: now.at,
                duration_ms: inst.dur_ms,
                fight,
            },
        );
    }

    pub fn on_end(
        &mut self,
        now: &Now,
        inst: &Inst,
        held_ms: i64,
        recent: &Recent,
        book: &mut Book,
    ) {
        let Some(track) = self.open.remove(&inst.id) else {
            return;
        };
        if recent
            .casts_since(now.at - JUDGED_MS)
            .any(|c| Set::Judge.has(c.spell))
        {
            settle(book, &track, JUDGED, now.at);
            return;
        }
        if self
            .gains
            .iter()
            .any(|(at, id)| *id != inst.id && *at >= now.at - REPLACED_MS)
        {
            settle(book, &track, REPLACED, now.at);
            return;
        }
        self.ended.push(Ended {
            track,
            at: now.at,
            held_ms,
        });
    }

    pub fn advance(&mut self, t: i64, book: &mut Book) {
        if self.ended.is_empty() {
            return;
        }
        let waiting = std::mem::take(&mut self.ended);
        for ended in waiting {
            if t > ended.at + REPLACED_MS {
                run_out_or_other(book, &ended);
            } else {
                self.ended.push(ended);
            }
        }
    }

    pub fn fight_end(&mut self, fight: i64, now: &Now, book: &mut Book) {
        let standing: Vec<u64> = self
            .open
            .iter()
            .filter(|(_, t)| t.fight == Some(fight))
            .map(|(id, _)| *id)
            .collect();
        for id in standing {
            if let Some(track) = self.open.remove(&id) {
                settle(book, &track, FIGHT_ENDED, now.at);
            }
        }
    }

    pub fn finish(&mut self, book: &mut Book) {
        for ended in std::mem::take(&mut self.ended) {
            run_out_or_other(book, &ended);
        }
    }
}

struct Drop {
    now: Now,
    duration_ms: i64,
    fight: Option<i64>,
}

#[derive(Default)]
struct Slot {
    occupied: bool,
    touched: bool,
    drop: Option<Drop>,
}

/// The four totem slots: the fate of each drop, and how many slots held a totem in a fight.
#[derive(Default)]
pub struct Totems {
    slots: [Slot; TOTEM_SLOTS],
}

impl Totems {
    pub fn on_totem(&mut self, now: &Now, f: &[&str], fight: Option<i64>, book: &mut Book) {
        let Some(number) = num(f, 0).filter(|s| (1..=TOTEM_SLOTS as i64).contains(s)) else {
            return;
        };
        let spell = id_or_zero(f, 1).unwrap_or(0);
        let slot = &mut self.slots[(number - 1) as usize];
        if let Some(drop) = slot.drop.take() {
            let stood = drop.duration_ms > 0
                && now.at - drop.now.at + FULL_TIME_SLACK_MS >= drop.duration_ms;
            let fate = match (stood, spell > 0) {
                (true, _) => STOOD,
                (false, true) => REPLACED,
                (false, false) => GONE_EARLY,
            };
            book.add(&drop.now, "totem_fate", number, fate, None);
        }
        slot.occupied = spell > 0;
        if spell > 0 {
            slot.touched |= fight.is_some();
            slot.drop = Some(Drop {
                now: *now,
                duration_ms: seconds_ms(now, f, 2),
                fight,
            });
        }
    }

    pub fn fight_start(&mut self) {
        for slot in &mut self.slots {
            slot.touched = slot.occupied;
        }
    }

    pub fn fight_end(&mut self, fight: i64, started: &Now, book: &mut Book) {
        let held = self.slots.iter().filter(|s| s.touched).count();
        book.add(started, "fight_totems", held as i64, 0, None);
        for (index, slot) in self.slots.iter_mut().enumerate() {
            slot.touched = false;
            if slot.drop.as_ref().is_some_and(|d| d.fight == Some(fight))
                && let Some(drop) = slot.drop.take()
            {
                book.add(&drop.now, "totem_fate", index as i64 + 1, FIGHT_ENDED, None);
            }
        }
    }
}

/// The self-resurrect options a death offered, and whether the next thing that happened was using one.
#[derive(Default)]
pub struct Resurrections {
    awaiting: bool,
    offers: Vec<(Now, i64, i64)>,
}

impl Resurrections {
    pub fn on_death(&mut self) {
        self.awaiting = true;
        self.offers.clear();
    }

    pub fn on_offer(&mut self, now: &Now, f: &[&str], book: &mut Book) {
        let (Some(kind), Some(id)) = (num(f, 0), num(f, 1)) else {
            return;
        };
        if self.offers.iter().any(|(_, k, i)| *k == kind && *i == id) {
            return;
        }
        self.awaiting = true;
        self.offers.push((*now, kind, id));
        book.add(now, "res_offer", kind, id, None);
    }

    /// `U` came back to life, `R` released the spirit, `SH` took the spirit healer.
    pub fn on_back(&mut self, code: &str, book: &mut Book) {
        if code == "U" && self.awaiting {
            for (now, kind, id) in &self.offers {
                book.add(now, "res_taken", *kind, *id, None);
            }
        }
        self.awaiting = false;
        self.offers.clear();
    }
}
