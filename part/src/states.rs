//! The state machines behind `state_times` (docs/ROWS.md): auras, auto repeat, mounts, pets, tracking, weapons,
//! totems and fights. Each line opens or ends spans of the ledger; the ledger credits the time.

use crate::auras::Inst;
use crate::class::{Now, TRACKED_MAX, id_or_zero, seconds_ms};
use crate::decode::{field, num};
use crate::spans::{Begin, Ledger, SpanId};
use std::collections::{BTreeMap, BTreeSet};

const TOTEM_SLOTS: i64 = 4;
const MAIN_HAND: i64 = 16;
const OFF_HAND: i64 = 17;
const BASELINE: &str = "s";

struct Pet {
    name: String,
    span: SpanId,
    happy: Option<(i64, SpanId)>,
}

#[derive(Default)]
pub struct States {
    pub ledger: Ledger,
    auto: Option<(i64, SpanId)>,
    mount: Option<SpanId>,
    pet: Option<Pet>,
    tracking: BTreeMap<String, SpanId>,
    gear: [Option<(i64, SpanId)>; 2],
    totems: [Option<SpanId>; TOTEM_SLOTS as usize],
    fight: Option<SpanId>,
}

impl States {
    pub fn sync(&mut self, now: &Now) {
        self.ledger.sync(now);
    }

    pub fn fighting(&self) -> bool {
        self.ledger.clock.open().is_some()
    }

    /// The spell of the auto repeat that is running, when the line named it.
    pub fn auto_spell(&self) -> Option<i64> {
        self.auto.map(|(spell, _)| spell).filter(|s| *s > 0)
    }

    pub fn level_up(&mut self, level: Option<i64>) {
        self.ledger.level_up(level);
    }

    pub fn finish(&mut self, end: i64) {
        self.ledger.clock.end(end);
        self.ledger.finish(end);
    }

    /// An aura of `kind` (`a` from you, `b` from others, `x` a debuff) was gained.
    pub fn on_gain(&mut self, now: &Now, inst: &mut Inst, kind: Option<char>) {
        let Some(kind) = kind else { return };
        let mut begin = Begin::new(kind, inst.spell);
        if inst.initial {
            begin = begin.uncounted().since(now.start);
        }
        inst.span = Some(self.ledger.begin(begin));
    }

    pub fn on_end(&mut self, inst: &Inst, ms: i64) {
        if let Some(span) = inst.span {
            self.ledger.end(span, Some(ms));
        }
    }

    pub fn on_unseen_end(&mut self, kind: Option<char>, spell: i64, ms: i64) {
        if let Some(kind) = kind {
            self.ledger.add_unseen(kind, spell, ms);
        }
    }

    pub fn fight_start(&mut self, now: &Now) -> bool {
        if !self.ledger.clock.start(now.at) {
            return false;
        }
        self.fight = Some(self.ledger.begin(Begin::new('f', 0)));
        true
    }

    pub fn fight_end(&mut self, now: &Now) -> bool {
        if !self.ledger.clock.end(now.at) {
            return false;
        }
        if let Some(span) = self.fight.take() {
            self.ledger.end(span, None);
        }
        true
    }

    pub fn on_line(&mut self, now: &Now, code: &str, f: &[&str]) {
        match code {
            "AR" => self.on_auto(f),
            "MO" => self.on_mount(now, f),
            "PT" => self.on_pet(f),
            "PD" => self.on_pet_data(f),
            "MP" => self.on_tracking(f),
            "G" => self.on_gear(f),
            "TW" => self.on_totem(now, f),
            _ => {}
        }
    }

    fn on_auto(&mut self, f: &[&str]) {
        if field(f, 0) != "1" {
            if let Some((_, span)) = self.auto.take() {
                self.ledger.end(span, None);
            }
            return;
        }
        let Some(spell) = id_or_zero(f, 1) else {
            return;
        };
        if self.auto.is_some_and(|(open, _)| open == spell) {
            return;
        }
        if let Some((_, span)) = self.auto.take() {
            self.ledger.end(span, None);
        }
        self.auto = Some((spell, self.ledger.begin(Begin::new('r', spell))));
    }

    fn on_mount(&mut self, now: &Now, f: &[&str]) {
        if let Some(span) = self.mount.take() {
            self.ledger.end(span, None);
        }
        if field(f, 0) != "1" {
            return;
        }
        let Some(spell) = id_or_zero(f, 1) else {
            return;
        };
        let mut begin = Begin::new('m', spell);
        if field(f, 4) == BASELINE {
            begin = begin.uncounted().since(now.start);
        }
        self.mount = Some(self.ledger.begin(begin));
    }

    fn drop_pet(&mut self) {
        let Some(pet) = self.pet.take() else { return };
        if let Some((_, span)) = pet.happy {
            self.ledger.end(span, None);
        }
        self.ledger.end(pet.span, None);
    }

    fn on_pet(&mut self, f: &[&str]) {
        let name = field(f, 0);
        if name.is_empty() {
            self.drop_pet();
            return;
        }
        let family = field(f, 1);
        if self.pet.as_ref().is_some_and(|p| p.name == name) {
            self.ledger.fill_extra('p', 0, name, family);
            return;
        }
        self.start_pet(name, family);
    }

    fn start_pet(&mut self, name: &str, family: &str) {
        self.drop_pet();
        let span = self
            .ledger
            .begin(Begin::new('p', 0).name(name).extra(family));
        self.pet = Some(Pet {
            name: name.to_string(),
            span,
            happy: None,
        });
    }

    fn on_pet_data(&mut self, f: &[&str]) {
        let name = field(f, 0);
        if name.is_empty() {
            return;
        }
        let known = self.pet.as_ref().is_some_and(|p| p.name == name);
        if !known && field(f, 8) == BASELINE {
            self.start_pet(name, "");
        }
        let Some(pet) = self.pet.as_mut().filter(|p| p.name == name) else {
            return;
        };
        let happiness = num(f, 4).filter(|h| (1..=3).contains(h));
        if pet.happy.map(|(h, _)| h) == happiness {
            return;
        }
        if let Some((_, span)) = pet.happy.take() {
            self.ledger.end(span, None);
        }
        if let Some(h) = happiness {
            pet.happy = Some((h, self.ledger.begin(Begin::new('h', h).name(name))));
        }
    }

    fn on_tracking(&mut self, f: &[&str]) {
        let baseline = field(f, 1) == BASELINE;
        let listed: Vec<&str> = field(f, 0)
            .split(',')
            .map(str::trim)
            .filter(|t| !t.is_empty())
            .collect();
        let now_on: BTreeSet<&str> = listed.iter().copied().collect();
        let gone: Vec<String> = self
            .tracking
            .keys()
            .filter(|t| !now_on.contains(t.as_str()))
            .cloned()
            .collect();
        for track in gone {
            if let Some(span) = self.tracking.remove(&track) {
                self.ledger.end(span, None);
            }
        }
        for track in listed {
            if self.tracking.contains_key(track) || self.tracking.len() >= TRACKED_MAX {
                continue;
            }
            let mut begin = Begin::new('k', track.parse::<i64>().unwrap_or(0)).name(track);
            if baseline {
                begin = begin.uncounted();
            }
            self.tracking
                .insert(track.to_string(), self.ledger.begin(begin));
        }
    }

    fn on_gear(&mut self, f: &[&str]) {
        let (slot, kind) = match num(f, 0) {
            Some(MAIN_HAND) => (0, 'w'),
            Some(OFF_HAND) => (1, 'o'),
            _ => return,
        };
        let item = id_or_zero(f, 1).unwrap_or(0);
        if item > 0 && self.gear[slot].is_some_and(|(held, _)| held == item) {
            return;
        }
        if let Some((_, span)) = self.gear[slot].take() {
            self.ledger.end(span, None);
        }
        if item > 0 {
            self.gear[slot] = Some((item, self.ledger.begin(Begin::new(kind, item))));
        }
    }

    fn on_totem(&mut self, now: &Now, f: &[&str]) {
        let slot = match num(f, 0) {
            Some(s @ 1..=TOTEM_SLOTS) => (s - 1) as usize,
            _ => return,
        };
        if let Some(span) = self.totems[slot].take() {
            self.ledger.end(span, None);
        }
        let spell = id_or_zero(f, 1).unwrap_or(0);
        if spell <= 0 {
            return;
        }
        let mut begin = Begin::new('t', spell).extra(&(slot + 1).to_string());
        let duration = seconds_ms(now, f, 2);
        if duration > 0 {
            begin = begin.until(now.at + duration);
        }
        self.totems[slot] = Some(self.ledger.begin(begin));
    }
}
