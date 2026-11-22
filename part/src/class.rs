//! The class tables (docs/ROWS.md): `spell_casts`, `meter_totals`, `meter_spells`, `state_times`, `item_flows` and
//! `class_tallies`. The decoder feeds every line of the session here once, and the machines pick the lines they read.

use crate::auras::{Auras, Loss, Recent};
use crate::combat::Combat;
use crate::decode::{field, num};
use crate::flows::Flows;
use crate::rows::Rows;
use crate::states::States;
use crate::tallies::Tallies;

/// Where and when a line was read. `len` is the session's length: no time a line reports is longer.
#[derive(Clone, Copy)]
pub struct Now {
    pub at: i64,
    pub start: i64,
    pub len: i64,
    pub level: Option<i64>,
    pub zone_id: Option<i64>,
    pub place: char,
}

pub const OPEN_WORLD: char = 'w';

/// The `place` of an instance type: `w` open world, `d` dungeon, `r` raid, `b` battleground or arena.
pub fn place_of(instance_type: &str) -> char {
    match instance_type {
        "party" | "scenario" => 'd',
        "raid" => 'r',
        "pvp" | "arena" => 'b',
        _ => OPEN_WORLD,
    }
}

/// A seconds field in milliseconds, from 0 to the session's length; 0 when it is empty, secret or not a number.
pub fn seconds_ms(now: &Now, f: &[&str], i: usize) -> i64 {
    let ms = f
        .get(i)
        .and_then(|v| v.trim().parse::<f64>().ok())
        .filter(|s| s.is_finite())
        .map_or(0, |s| (s * 1000.0).round() as i64);
    ms.max(0).min(now.len.max(0))
}

/// The largest id a column holds.
pub const MAX_ID: i64 = i32::MAX as i64;

pub fn is_id(v: i64) -> bool {
    (0..=MAX_ID).contains(&v)
}

/// An id field; none when it is missing, not a number or outside what an id column holds.
pub fn id(f: &[&str], i: usize) -> Option<i64> {
    num(f, i).filter(|v| is_id(*v))
}

/// An id that may be empty, which reads as 0; none when the field holds a number no id column holds.
pub fn id_or_zero(f: &[&str], i: usize) -> Option<i64> {
    match num(f, i) {
        None => Some(0),
        Some(v) => is_id(v).then_some(v),
    }
}

/// A total, amount or quantity field; none when it is missing, not a number or negative.
pub fn amount(f: &[&str], i: usize) -> Option<i64> {
    num(f, i).filter(|v| *v >= 0)
}

/// The text of a name column: its first 255 characters.
pub fn clip(text: &str) -> String {
    text.chars().take(NAME_CHARS).collect()
}

const NAME_CHARS: usize = 255;
const MINE: &str = "1";
const HELPFUL: &str = "1";
const HARMFUL: &str = "0";
const INITIAL: &str = "s";

#[derive(Default)]
pub struct Class {
    combat: Combat,
    auras: Auras,
    recent: Recent,
    states: States,
    flows: Flows,
    tallies: Tallies,
}

fn aura_kind(helpful: Option<bool>, mine: bool) -> Option<char> {
    match (helpful, mine) {
        (Some(true), true) => Some('a'),
        (Some(true), false) => Some('b'),
        (Some(false), _) => Some('x'),
        (None, _) => None,
    }
}

impl Class {
    /// One line of the session, read before the decoder acts on it: a level-up still shows the old level in `now`.
    pub fn on_line(&mut self, now: &Now, code: &str, f: &[&str]) {
        self.states.sync(now);
        self.tallies.advance(now.at);
        match code {
            "C" => self.on_cast(now, f),
            "CH" => {
                self.combat.on_channel(now, f);
                if let Some(spell) = id(f, 0) {
                    self.recent.push(now.at, spell, true);
                }
            }
            "CI" => self.combat.on_interrupt(now, f),
            "DM" => self.combat.on_meter(now, f),
            "DP" => self.combat.on_peer(now, f),
            "DS" => self.combat.on_top_spell(now, f),
            "F" => match field(f, 0) {
                "1" if self.states.fight_start(now) => self.tallies.fight_start(now, &self.recent),
                "0" if self.states.fight_end(now) => self.tallies.fight_end(now),
                _ => {}
            },
            "A" => self.on_aura(now, f),
            "L" => self
                .states
                .level_up(num(f, 0).filter(|l| *l > 0).or(now.level)),
            "K" => self.tallies.on_kill(now, f, &self.recent),
            "D" => self.tallies.on_death(now, &self.recent, &self.auras),
            "SR" => self.tallies.on_offer(now, f),
            "U" | "R" | "SH" => self.tallies.on_back(code),
            "TW" => {
                self.states.on_line(now, code, f);
                self.tallies.on_totem(now, f);
            }
            "AR" | "MO" | "PT" | "PD" | "MP" | "G" => self.states.on_line(now, code, f),
            "I" => self
                .flows
                .on_item(now, f, &self.recent, self.states.auto_spell()),
            "DI" => self.flows.on_delete(now, f),
            _ => {}
        }
    }

    fn on_cast(&mut self, now: &Now, f: &[&str]) {
        let Some(spell) = id(f, 0) else { return };
        self.combat.on_cast(now, self.states.fighting(), f);
        self.tallies.on_cast(now, spell, &mut self.auras);
        self.recent.push(now.at, spell, false);
    }

    fn on_aura(&mut self, now: &Now, f: &[&str]) {
        let Some(spell) = id(f, 1) else { return };
        if field(f, 0) != MINE {
            self.on_aura_end(now, spell, seconds_ms(now, f, 2));
            return;
        }
        let helpful = match field(f, 2) {
            HELPFUL => Some(true),
            HARMFUL => Some(false),
            _ => None,
        };
        let mine = field(f, 4) == MINE;
        let initial = field(f, 5) == INITIAL;
        let Some(id) = self
            .auras
            .gain(now, spell, (helpful, mine), seconds_ms(now, f, 3), initial)
        else {
            return;
        };
        if let Some(inst) = self.auras.get_mut(id) {
            self.states.on_gain(now, inst, aura_kind(helpful, mine));
        }
        self.tallies.on_gain(now, &self.auras, id);
    }

    fn on_aura_end(&mut self, now: &Now, spell: i64, ms: i64) {
        match self.auras.lose(now, spell, ms) {
            Loss::Carried => {}
            Loss::Ended { inst, ms } => {
                self.states.on_end(&inst, ms);
                self.tallies.on_loss(now, &inst, ms, &self.recent);
            }
            Loss::Unseen { helpful, mine, ms } => {
                self.states
                    .on_unseen_end(aura_kind(helpful, mine), spell, ms);
            }
        }
    }

    /// The session ended at `end`.
    pub fn finish(&mut self, session: i64, end: &Now, combat: bool, w: &mut Rows) {
        self.states.sync(end);
        if self.states.fight_end(end) {
            self.tallies.fight_end(end);
        }
        self.states.finish(end.at);
        self.combat.finish(session, w);
        self.states.ledger.write(session, combat, w);
        self.flows.finish(session, w);
        self.tallies.finish(end, session, w);
    }
}
