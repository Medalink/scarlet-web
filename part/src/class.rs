//! The class tables (docs/ROWS.md): `spell_casts`, `meter_totals`, `meter_spells`, `state_times`, `item_flows` and
//! `class_tallies`. The decoder feeds every line of the session here once, and the machines pick the lines they read.

use crate::auras::{Auras, Loss, Recent};
use crate::combat::Combat;
use crate::decode::{field, num};
use crate::flows::Flows;
use crate::rows::Rows;
use crate::states::States;
use crate::tallies::Tallies;

/// Where and when a line was read.
#[derive(Clone, Copy)]
pub struct Now {
    pub at: i64,
    pub start: i64,
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

/// A seconds field in milliseconds; 0 when it is empty, secret or not a number.
pub fn seconds_ms(f: &[&str], i: usize) -> i64 {
    f.get(i)
        .and_then(|v| v.trim().parse::<f64>().ok())
        .filter(|s| s.is_finite())
        .map_or(0, |s| (s * 1000.0).round() as i64)
}

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
                if let Some(spell) = num(f, 0) {
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
        let Some(spell) = num(f, 0) else { return };
        self.combat.on_cast(now, self.states.fighting(), f);
        self.tallies.on_cast(now, spell, &mut self.auras);
        self.recent.push(now.at, spell, false);
    }

    fn on_aura(&mut self, now: &Now, f: &[&str]) {
        let Some(spell) = num(f, 1) else { return };
        if field(f, 0) != MINE {
            self.on_aura_end(now, spell, seconds_ms(f, 2));
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
            .gain(now, spell, (helpful, mine), seconds_ms(f, 3), initial)
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
