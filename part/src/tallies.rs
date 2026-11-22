//! The `class_tallies` rows (docs/ROWS.md): counts of sequences, one small machine for each key. The machines that
//! look back read the casts and aura ends the feed keeps; the ones that wait for an outcome keep a deadline and settle
//! on the first line past it, or when the session ends. An outcome depends on line times only, never on which line
//! settled it.

use crate::auras::{Auras, Inst, Recent};
use crate::class::{Now, TRACKED_MAX, is_id};
use crate::decode::{num, prefix};
use crate::fates::{Resurrections, Seals, Totems};
use crate::rows::Rows;
use crate::sets::Set;
use std::collections::BTreeMap;

pub const SAME_DEATH_MS: i64 = 3_000;

const KILL_BLOW_MS: i64 = 3_000;
const DEATH_WINDOW_MS: i64 = 10_000;
const UNDER_MS: i64 = 300;
const SHIFT_MS: i64 = 2_000;
const FIRST_BEFORE_MS: i64 = 1_500;
const FIRST_AFTER_MS: i64 = 3_000;
const STEALTH_MS: i64 = 1_000;
const MAX_FIGHT_KILLS: i64 = 20;
const MINE: i64 = 1;

const SHIFTS: [(Set, &str); 5] = [
    (Set::Form, "shift_form"),
    (Set::Stance, "shift_stance"),
    (Set::Aspect, "shift_aspect"),
    (Set::PalAura, "shift_pal_aura"),
    (Set::Seal, "shift_seal"),
];

#[derive(PartialEq, Eq, PartialOrd, Ord)]
struct Key {
    key: &'static str,
    a: i64,
    b: i64,
    level: Option<i64>,
}

struct Row {
    at: i64,
    zone_id: Option<i64>,
    n: i64,
    sum: i64,
    best: i64,
}

#[derive(Default)]
pub struct Book {
    rows: BTreeMap<Key, Row>,
}

impl Book {
    /// One more of `key` with `a` and `b`, filed under the level and place of `now`. `sum` is the measure some keys
    /// add up, in milliseconds. Nothing is written for an `a` or `b` that no id column holds.
    pub fn add(&mut self, now: &Now, key: &'static str, a: i64, b: i64, sum: Option<i64>) {
        if !is_id(a) || !is_id(b) {
            return;
        }
        let key = Key {
            key,
            a,
            b,
            level: now.level,
        };
        let row = self.rows.entry(key).or_insert_with(|| Row {
            at: now.at,
            zone_id: now.zone_id,
            n: 0,
            sum: 0,
            best: 0,
        });
        row.n += 1;
        let sum = sum.unwrap_or(0).max(0);
        row.sum = row.sum.saturating_add(sum);
        row.best = row.best.max(sum);
    }

    pub fn write(&self, session: i64, w: &mut Rows) {
        for (key, row) in &self.rows {
            prefix(w, "class_tallies", session, row.at, key.level, row.zone_id);
            w.str("key", key.key)
                .int("a", key.a)
                .int("b", key.b)
                .int("n", row.n)
                .int("sum", row.sum)
                .int("best", row.best);
            w.end();
        }
    }
}

struct Fight {
    now: Now,
    kills: i64,
    opener: Option<i64>,
    finisher_after_opener: Option<i64>,
    first_finisher: Option<i64>,
}

#[derive(Default)]
pub struct Tallies {
    book: Book,
    fight: Option<Fight>,
    firsts: Vec<(Now, i64)>,
    last_death: Option<i64>,
    seals: Seals,
    totems: Totems,
    resurrections: Resurrections,
}

impl Tallies {
    /// Settle what waits for a line past its deadline.
    pub fn advance(&mut self, t: i64) {
        if !self.firsts.is_empty() {
            let (late, open): (Vec<_>, Vec<_>) = self.firsts.drain(..).partition(|(_, d)| *d < t);
            self.firsts = open;
            for (now, _) in late {
                self.book.add(&now, "fight_first", 0, 0, None);
            }
        }
        self.seals.advance(t, &mut self.book);
    }

    pub fn fight_id(&self) -> Option<i64> {
        self.fight.as_ref().map(|f| f.now.at)
    }

    pub fn fight_start(&mut self, now: &Now, recent: &Recent) {
        let first = recent
            .casts_since(now.at - FIRST_BEFORE_MS)
            .next()
            .map(|c| c.spell);
        let opener = recent
            .casts_since(now.at - FIRST_BEFORE_MS)
            .find(|c| Set::Opener.has(c.spell))
            .map(|c| c.spell);
        match first {
            Some(spell) => self.book.add(now, "fight_first", spell, 0, None),
            None => {
                if self.firsts.len() >= TRACKED_MAX {
                    let (oldest, _) = self.firsts.remove(0);
                    self.book.add(&oldest, "fight_first", 0, 0, None);
                }
                self.firsts.push((*now, now.at + FIRST_AFTER_MS));
            }
        }
        self.totems.fight_start();
        self.fight = Some(Fight {
            now: *now,
            kills: 0,
            opener,
            finisher_after_opener: None,
            first_finisher: None,
        });
    }

    pub fn fight_end(&mut self, now: &Now) {
        let Some(fight) = self.fight.take() else {
            return;
        };
        let id = fight.now.at;
        let book = &mut self.book;
        book.add(
            &fight.now,
            "fight_kills",
            fight.kills.min(MAX_FIGHT_KILLS),
            0,
            None,
        );
        let finisher = if fight.opener.is_some() {
            fight.finisher_after_opener
        } else {
            fight.first_finisher
        };
        book.add(
            &fight.now,
            "open_finish",
            fight.opener.unwrap_or(0),
            finisher.unwrap_or(0),
            None,
        );
        self.totems.fight_end(id, &fight.now, book);
        self.seals.fight_end(id, now, book);
    }

    pub fn on_cast(&mut self, now: &Now, spell: i64, auras: &mut Auras) {
        let waiting = std::mem::take(&mut self.firsts);
        for (first, _) in waiting {
            self.book.add(&first, "fight_first", spell, 0, None);
        }
        if let Some(fight) = self.fight.as_mut() {
            if Set::Opener.has(spell) && fight.opener.is_none() {
                fight.opener = Some(spell);
            }
            if Set::Finisher.has(spell) {
                fight.first_finisher.get_or_insert(spell);
                if fight.opener.is_some() {
                    fight.finisher_after_opener.get_or_insert(spell);
                }
            }
        }
        self.cast_under(now, spell, auras);
        if Set::Opener.has(spell) {
            self.stealth_open(now, spell, auras);
        }
    }

    fn cast_under(&mut self, now: &Now, spell: i64, auras: &Auras) {
        let mut watched: Vec<i64> = auras
            .on
            .iter()
            .map(|i| i.spell)
            .chain(auras.ended_since(now.at - UNDER_MS).map(|e| e.spell))
            .filter(|s| Set::Watch.has(*s))
            .collect();
        watched.sort_unstable();
        watched.dedup();
        for aura in watched {
            self.book.add(now, "cast_under", aura, spell, None);
        }
    }

    fn stealth_open(&mut self, now: &Now, spell: i64, auras: &mut Auras) {
        let on = auras
            .on
            .iter_mut()
            .filter(|i| Set::Stealth.has(i.spell) && !i.opened)
            .max_by_key(|i| i.gain_at);
        if let Some(inst) = on {
            inst.opened = true;
            self.book
                .add(now, "stealth_open", spell, 0, Some(now.at - inst.gain_at));
            return;
        }
        let ended = auras
            .ended_since(now.at - STEALTH_MS)
            .find(|e| Set::Stealth.has(e.spell) && !e.opened)
            .map(|e| (e.id, e.gain_at));
        if let Some((id, gain_at)) = ended {
            auras.mark_ended_opened(id);
            self.book
                .add(now, "stealth_open", spell, 0, Some(now.at - gain_at));
        }
    }

    pub fn on_kill(&mut self, now: &Now, f: &[&str], recent: &Recent) {
        if num(f, 1) != Some(MINE) {
            return;
        }
        let blow = recent
            .latest(now.at - KILL_BLOW_MS, false)
            .map_or(0, |c| c.spell);
        self.book.add(now, "kill_blow", blow, 0, None);
        if let Some(fight) = self.fight.as_mut() {
            fight.kills += 1;
        }
    }

    pub fn on_death(&mut self, now: &Now, recent: &Recent, auras: &Auras) {
        if self.last_death.is_some_and(|d| now.at - d <= SAME_DEATH_MS) {
            return;
        }
        self.last_death = Some(now.at);
        let mut casts: BTreeMap<i64, i64> = BTreeMap::new();
        for cast in recent.casts_since(now.at - DEATH_WINDOW_MS) {
            casts.insert(cast.spell, cast.at);
        }
        for (spell, at) in casts {
            self.book
                .add(now, "death_after", spell, (now.at - at) / 1000, None);
        }
        let mut ends: BTreeMap<i64, i64> = BTreeMap::new();
        for end in auras.ended_since(now.at - DEATH_WINDOW_MS) {
            if end.helpful == Some(true) {
                ends.entry(end.spell).or_insert(end.at);
            }
        }
        for (spell, at) in ends {
            self.book
                .add(now, "death_after_aura", spell, (now.at - at) / 1000, None);
        }
        self.resurrections.on_death();
    }

    pub fn on_offer(&mut self, now: &Now, f: &[&str]) {
        self.resurrections.on_offer(now, f, &mut self.book);
    }

    pub fn on_back(&mut self, code: &str) {
        self.resurrections.on_back(code, &mut self.book);
    }

    /// A seal or shape-shift aura was gained; `id` is its instance.
    pub fn on_gain(&mut self, now: &Now, auras: &Auras, id: u64) {
        let Some(inst) = auras.get(id).filter(|i| !i.initial) else {
            return;
        };
        for (set, key) in SHIFTS {
            if !set.has(inst.spell) {
                continue;
            }
            let left = auras
                .on
                .iter()
                .filter(|i| i.id != id && set.has(i.spell))
                .max_by_key(|i| i.gain_at)
                .map(|i| i.spell)
                .or_else(|| auras.ended_of(set, now.at - SHIFT_MS).map(|e| e.spell))
                .unwrap_or(0);
            self.book.add(now, key, left, inst.spell, None);
        }
        if Set::Seal.has(inst.spell) {
            self.seals
                .on_gain(now, inst, self.fight_id(), &mut self.book);
        }
    }

    pub fn on_loss(&mut self, now: &Now, inst: &Inst, ms: i64, recent: &Recent) {
        self.seals.on_end(now, inst, ms, recent, &mut self.book);
    }

    pub fn on_totem(&mut self, now: &Now, f: &[&str]) {
        let fight = self.fight_id();
        self.totems.on_totem(now, f, fight, &mut self.book);
    }

    /// The session ended: the fight closes, and everything that waited settles.
    pub fn finish(&mut self, end: &Now, session: i64, w: &mut Rows) {
        self.fight_end(end);
        for (now, _) in std::mem::take(&mut self.firsts) {
            self.book.add(&now, "fight_first", 0, 0, None);
        }
        self.seals.finish(&mut self.book);
        self.book.write(session, w);
    }
}
