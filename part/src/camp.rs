//! Campsite rows of the decoder (docs/ROWS.md): `camp_stays` and `camp_benefits`, read from the `A` aura lines, the
//! `C` cast lines and the `D`, `CR`, `P` and `AFK` lines around them. The `CP` camp-visit line is not read: it also
//! counts the hour-long Camp Benefits buff, so it overstates the time at a camp (Medalink/scarlet#2).

use crate::decode::{num, prefix, round_s};
use crate::rows::Rows;
use std::collections::BTreeMap;

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
    level: Option<i64>,
    zone_id: Option<i64>,
    pos: Pos,
}

struct Aura {
    spell: i64,
    kind: Kind,
    held: Held,
    end: i64,
    held_s: Option<i64>,
    by_session: bool,
    regained: bool,
}

/// Which groups beside combat had a part: what they would fill is null without one.
#[derive(Clone, Copy)]
pub struct Known {
    pub afk: bool,
    pub crafts: bool,
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
    done: Vec<Aura>,
    afk_since: Option<i64>,
    afk: Vec<(i64, i64)>,
    casts: Vec<(i64, i64, Kind)>,
    deaths: Vec<i64>,
    crafts: Vec<i64>,
    pos: Pos,
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
    spans
        .iter()
        .map(|(a, b)| (b.min(&to) - a.max(&from)).max(0))
        .sum()
}

impl Camp {
    pub fn on_aura(&mut self, t: i64, f: &[&str], level: Option<i64>, zone_id: Option<i64>) {
        let Some(spell) = num(f, 1) else { return };
        let Some(kind) = kind_of(spell) else { return };
        if f.first() == Some(&"1") {
            if self.open.contains_key(&spell) {
                if !matches!(kind, Kind::Benefits | Kind::Benefit) {
                    return;
                }
                self.close(spell, t, None, true);
            }
            let held = Held {
                start: t,
                initial: f.get(5).is_some_and(|s| s.trim() == "s"),
                level,
                zone_id,
                pos: self.pos,
            };
            self.open.insert(spell, (kind, held));
            return;
        }
        self.close(spell, t, num(f, 2), false);
    }

    fn close(&mut self, spell: i64, t: i64, held_s: Option<i64>, regained: bool) {
        let Some((kind, held)) = self.open.remove(&spell) else {
            return;
        };
        self.done.push(Aura {
            spell,
            kind,
            held,
            end: t,
            held_s,
            by_session: false,
            regained,
        });
    }

    pub fn on_cast(&mut self, t: i64, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        if let Some(kind @ (Kind::Campfire | Kind::PlacedTent | Kind::PlacedChair)) = kind_of(spell)
        {
            self.casts.push((t, spell, kind));
        }
    }

    pub fn on_death(&mut self, t: i64) {
        self.deaths.push(t);
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
                regained: false,
            });
        }
        if let Some(since) = self.afk_since.take() {
            self.afk.push((since, end_ms));
        }
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
                && !a.regained
                && self
                    .deaths
                    .iter()
                    .any(|d| (d - a.end).abs() <= DEATH_AT_AURA_END_MS);
            prefix(
                w,
                "camp_benefits",
                session,
                a.held.start,
                a.held.level,
                a.held.zone_id,
            );
            w.int("spell_id", a.spell)
                .int("seconds", seconds(a))
                .bool("carried", a.held.initial)
                .bool("lost_to_death", lost);
            w.end();
        }
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
        let benefits = self.done.iter().any(|b| {
            b.kind == Kind::Benefits && !b.held.initial && (from..=to).contains(&b.held.start)
        });
        let placed = |wanted: Kind| {
            self.casts
                .iter()
                .rev()
                .find(|(t, _, kind)| *kind == wanted && *t >= casts_from && *t <= to)
                .map(|(_, spell, _)| *spell)
        };
        let crafts = self
            .crafts
            .iter()
            .filter(|t| (from..=to).contains(t))
            .count() as i64;
        let died_after = !a.by_session
            && self
                .deaths
                .iter()
                .any(|d| *d >= to - DEATH_AT_AURA_END_MS && *d <= to + DEATH_AFTER_STAY_MS);
        let length = seconds(a);
        let within = |spans: &[(i64, i64)]| round_s(overlap_ms(spans, from, to)).min(length);
        prefix(w, "camp_stays", session, from, a.held.level, a.held.zone_id);
        w.int("seconds", length)
            .bool("carried", a.held.initial)
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

fn seconds(a: &Aura) -> i64 {
    a.held_s.unwrap_or_else(|| round_s(a.end - a.held.start))
}
