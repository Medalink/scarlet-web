//! Campsite rows of the decoder (docs/ROWS.md): `camp_stays` and `camp_benefits`, read from the `A` aura lines, the
//! `C` cast lines and the `D`, `CR`, `P` and `AFK` lines around them. The `CP` camp-visit line is not read: it also
//! counts the hour-long Camp Benefits buff, so it overstates the time at a camp (Medalink/scarlet#2).

use crate::decode::{num, prefix, round_s};
use crate::rows::Rows;
use std::collections::{BTreeMap, VecDeque};

/// What a spell of the campsite is.
#[derive(Clone, Copy, PartialEq, Eq)]
pub enum Kind {
    Near,
    Sit,
    Tent,
    Benefits,
    Benefit,
    Campfire,
    PlacedTent,
    PlacedChair,
}

const SPELLS: [(i64, Kind); 19] = [
    (1_283_391, Kind::Near),
    (1_229_739, Kind::Sit),
    (1_289_723, Kind::Sit),
    (1_229_487, Kind::Tent),
    (1_229_741, Kind::Benefits),
    (1_229_519, Kind::Benefit),
    (1_230_124, Kind::Benefit),
    (1_230_172, Kind::Benefit),
    (1_230_164, Kind::Benefit),
    (1_230_587, Kind::Benefit),
    (1_229_513, Kind::Benefit),
    (1_229_718, Kind::Benefit),
    (1_230_098, Kind::Benefit),
    (1_230_653, Kind::Benefit),
    (1_307_227, Kind::Campfire),
    (1_307_252, Kind::Campfire),
    (1_307_237, Kind::Campfire),
    (1_307_230, Kind::PlacedTent),
    (1_307_229, Kind::PlacedChair),
];

const STAY_ENDED_WITH_AURA: &str = "w";
const STAY_ENDED_WITH_SESSION: &str = "s";

const PLACEMENT_CAST_LEAD_MS: i64 = 60_000;
const DEATH_AFTER_STAY_MS: i64 = 300_000;
const DEATH_AT_AURA_END_MS: i64 = 2_000;
const REPLACED_INSTANCE_REMOVAL_MS: i64 = 2_000;
const ALREADY_ON_AT_FIRST_SCAN_MS: i64 = 10_000;

fn kind_of(spell: i64) -> Option<Kind> {
    SPELLS
        .iter()
        .find(|(id, _)| *id == spell)
        .map(|(_, kind)| *kind)
}

type Pos = Option<[Option<i64>; 3]>;

struct Held {
    start: i64,
    initial: bool,
    refreshed: bool,
    duration: Option<i64>,
    level: Option<i64>,
    zone_id: Option<i64>,
    pos: Pos,
}

impl Held {
    fn new(start: i64, f: &[&str], level: Option<i64>, zone_id: Option<i64>, pos: Pos) -> Self {
        Self {
            start,
            initial: f.get(5).is_some_and(|s| s.trim() == "s"),
            refreshed: false,
            duration: num(f, 3),
            level,
            zone_id,
            pos,
        }
    }
}

struct Aura {
    spell: i64,
    kind: Kind,
    held: Held,
    end: i64,
    held_s: Option<i64>,
    by_session: bool,
}

/// Which groups beside combat had a part: what they would fill is null without one.
#[derive(Clone, Copy)]
pub struct Known {
    pub afk: bool,
    pub crafts: bool,
    pub xp: bool,
}

struct StayOverlaps<'a> {
    away: &'a [(i64, i64)],
    sat: &'a [(i64, i64)],
    tent: &'a [(i64, i64)],
    casts_from: i64,
}

#[derive(Default)]
pub struct Camp {
    open: BTreeMap<i64, (Kind, Held)>,
    replaced: BTreeMap<i64, VecDeque<i64>>,
    benefit_gains: Vec<i64>,
    done: Vec<Aura>,
    afk_since: Option<i64>,
    afk: Vec<(i64, i64)>,
    casts: [Vec<(i64, i64)>; 3],
    deaths: Vec<i64>,
    crafts: Vec<i64>,
    xp: Vec<(i64, i64)>,
    xp_prefix: Vec<i64>,
    pos: Pos,
}

fn cast_slot(kind: Kind) -> Option<usize> {
    match kind {
        Kind::Campfire => Some(0),
        Kind::PlacedTent => Some(1),
        Kind::PlacedChair => Some(2),
        _ => None,
    }
}

fn count_in(sorted: &[i64], from: i64, to: i64) -> usize {
    let lo = sorted.partition_point(|t| *t < from);
    let hi = sorted.partition_point(|t| *t <= to);
    hi.saturating_sub(lo)
}

fn merged(mut spans: Vec<(i64, i64)>) -> Vec<(i64, i64)> {
    spans.sort_unstable();
    let mut out: Vec<(i64, i64)> = Vec::new();
    for (a, b) in spans {
        match out.last_mut() {
            Some(last) if a <= last.1 => last.1 = last.1.max(b),
            _ => out.push((a, b)),
        }
    }
    out
}

fn overlap_ms(spans: &[(i64, i64)], from: i64, to: i64) -> i64 {
    let first = spans.partition_point(|(_, b)| *b <= from);
    spans[first..]
        .iter()
        .take_while(|(a, _)| *a < to)
        .map(|(a, b)| (b.min(&to) - a.max(&from)).max(0))
        .sum()
}

impl Camp {
    pub fn on_aura(&mut self, t: i64, f: &[&str], level: Option<i64>, zone_id: Option<i64>) {
        let Some(spell) = num(f, 1) else { return };
        let Some(kind) = kind_of(spell) else { return };
        if f.first() == Some(&"1") {
            let sitting = self.open.values().any(|(open, _)| *open == Kind::Sit);
            let Some((_, held)) = self.open.get_mut(&spell) else {
                if kind == Kind::Benefits {
                    self.benefit_gains.push(t);
                }
                self.open
                    .insert(spell, (kind, Held::new(t, f, level, zone_id, self.pos)));
                return;
            };
            if kind == Kind::Benefits && sitting {
                self.benefit_gains.push(t);
            }
            held.refreshed = true;
            held.duration = held.duration.max(num(f, 3));
            self.replaced.entry(spell).or_default().push_back(t);
            return;
        }
        let replaced = self.replaced.entry(spell).or_default();
        while replaced
            .front()
            .is_some_and(|at| t - at > REPLACED_INSTANCE_REMOVAL_MS)
        {
            replaced.pop_front();
        }
        if replaced.pop_back().is_some() {
            return;
        }
        let Some((kind, held)) = self.open.remove(&spell) else {
            return;
        };
        let held_s = num(f, 2).filter(|_| !held.refreshed);
        self.done.push(Aura {
            spell,
            kind,
            held,
            end: t,
            held_s,
            by_session: false,
        });
    }

    pub fn on_cast(&mut self, t: i64, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        if let Some(slot) = kind_of(spell).and_then(cast_slot) {
            self.casts[slot].push((t, spell));
        }
    }

    pub fn on_death(&mut self, t: i64) {
        self.deaths.push(t);
    }

    pub fn on_xp(&mut self, t: i64, amount: i64) {
        self.xp.push((t, amount));
    }

    pub fn on_craft(&mut self, t: i64) {
        self.crafts.push(t);
    }

    pub fn on_pos(&mut self, f: &[&str]) {
        self.pos = Some([num(f, 0), num(f, 1), num(f, 2)]);
    }

    pub fn on_afk(&mut self, t: i64, away: bool) {
        if away {
            self.afk_since.get_or_insert(t);
            return;
        }
        if let Some(since) = self.afk_since.take() {
            self.afk.push((since, t));
        }
    }

    pub fn finish(mut self, session: i64, end_ms: i64, known: Known, w: &mut Rows) {
        for (spell, (kind, held)) in std::mem::take(&mut self.open) {
            self.done.push(Aura {
                spell,
                kind,
                held,
                end: end_ms,
                held_s: None,
                by_session: true,
            });
        }
        if let Some(since) = self.afk_since.take() {
            self.afk.push((since, end_ms));
        }
        self.xp_prefix = std::iter::once(0)
            .chain(self.xp.iter().scan(0i64, |sum, (_, amount)| {
                *sum = sum.wrapping_add(*amount);
                Some(*sum)
            }))
            .collect();
        self.done.sort_by_key(|a| (a.held.start, a.spell));
        let away = merged(std::mem::take(&mut self.afk));
        let spans_of = |wanted: Kind| {
            merged(
                self.done
                    .iter()
                    .filter(|a| a.kind == wanted)
                    .map(|a| (a.held.start, a.end))
                    .collect(),
            )
        };
        let (sat, tent) = (spans_of(Kind::Sit), spans_of(Kind::Tent));
        let mut casts_from = i64::MIN;
        for a in self.done.iter().filter(|a| a.kind == Kind::Near) {
            let spans = StayOverlaps {
                away: &away,
                sat: &sat,
                tent: &tent,
                casts_from: casts_from.max(a.held.start - PLACEMENT_CAST_LEAD_MS),
            };
            self.stay(session, a, spans, known, w);
            casts_from = a.end + 1;
        }
        for a in self
            .done
            .iter()
            .filter(|a| matches!(a.kind, Kind::Benefits | Kind::Benefit))
        {
            let lost = !a.by_session
                && count_in(
                    &self.deaths,
                    a.end - DEATH_AT_AURA_END_MS,
                    a.end + DEATH_AT_AURA_END_MS,
                ) > 0;
            prefix(
                w,
                "camp_benefits",
                session,
                a.held.start,
                a.held.level,
                a.held.zone_id,
            );
            let outcome = (a.kind == Kind::Benefits).then(|| self.outcome(a, known));
            let [xp, deaths] = outcome.unwrap_or_default();
            w.int("spell_id", a.spell)
                .int("seconds", seconds(a))
                .bool("carried", carried(session, a))
                .bool("lost_to_death", lost)
                .opt("duration", a.held.duration)
                .opt("xp", xp)
                .opt("deaths", deaths);
            w.end();
        }
    }

    fn outcome(&self, a: &Aura, known: Known) -> [Option<i64>; 2] {
        let (from, to) = (a.held.start, a.end);
        let death_to = if a.by_session {
            to
        } else {
            to + DEATH_AT_AURA_END_MS
        };
        let xp = known.xp.then(|| {
            let lo = self.xp.partition_point(|(t, _)| *t < from);
            let hi = self.xp.partition_point(|(t, _)| *t <= to).max(lo);
            self.xp_prefix[hi].wrapping_sub(self.xp_prefix[lo])
        });
        let deaths = count_in(&self.deaths, from, death_to) as i64;
        [xp, Some(deaths)]
    }

    fn stay(
        &self,
        session: i64,
        a: &Aura,
        StayOverlaps {
            away,
            sat,
            tent,
            casts_from,
        }: StayOverlaps,
        known: Known,
        w: &mut Rows,
    ) {
        let (from, to) = (a.held.start, a.end);
        let first_scan_end = session * 1000 + ALREADY_ON_AT_FIRST_SCAN_MS;
        let benefits = count_in(&self.benefit_gains, from.max(first_scan_end + 1), to) > 0;
        let placed = |wanted: Kind| {
            let casts = &self.casts[cast_slot(wanted)?];
            let upto = casts.partition_point(|(t, _)| *t <= to);
            casts[..upto]
                .last()
                .filter(|(t, _)| *t >= casts_from)
                .map(|(_, spell)| *spell)
        };
        let crafts = count_in(&self.crafts, from, to) as i64;
        let died_after = !a.by_session
            && count_in(
                &self.deaths,
                to - DEATH_AT_AURA_END_MS,
                to + DEATH_AFTER_STAY_MS,
            ) > 0;
        let length = seconds(a);
        let within = |spans: &[(i64, i64)]| round_s(overlap_ms(spans, from, to)).min(length);
        prefix(w, "camp_stays", session, from, a.held.level, a.held.zone_id);
        w.int("seconds", length)
            .bool("carried", carried(session, a))
            .str(
                "ended",
                if a.by_session {
                    STAY_ENDED_WITH_SESSION
                } else {
                    STAY_ENDED_WITH_AURA
                },
            )
            .opt("afk_seconds", known.afk.then(|| within(away)))
            .int("sat_seconds", within(sat))
            .bool("benefits", benefits)
            .int("tent_seconds", within(tent))
            .opt("campfire_spell_id", placed(Kind::Campfire))
            .bool("placed_tent", placed(Kind::PlacedTent).is_some())
            .bool("placed_chair", placed(Kind::PlacedChair).is_some())
            .opt("crafts", known.crafts.then_some(crafts))
            .bool("died_after", died_after);
        let [map, x, y] = a.held.pos.unwrap_or([None; 3]);
        w.opt("map", map).opt("x", x).opt("y", y);
        w.end();
    }
}

fn carried(session: i64, a: &Aura) -> bool {
    a.held.initial || a.held.start - session * 1000 <= ALREADY_ON_AT_FIRST_SCAN_MS
}

fn seconds(a: &Aura) -> i64 {
    a.held_s.unwrap_or_else(|| round_s(a.end - a.held.start))
}
