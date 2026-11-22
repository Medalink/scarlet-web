//! Gathering and crafting rows of the decoder (docs/ROWS.md): `gathers`, `gather_items` and the profession columns of
//! `crafts`. A gather is read from the cast (`C`, `CH`) and the loot window (`LO`) after it; the skill line (`SL`), the
//! main hand (`G`), the combat flag (`F`) and the last point (`P`) give the rest. A craft is a `CR` that follows a `CB`;
//! a lure on the fishing pole reads as a `CR` too and is not one.

use crate::decode::{field, num, prefix};
use crate::rows::Rows;
use crate::tables::leading_id;
use std::collections::{BTreeMap, BTreeSet};

#[derive(Clone, Copy, PartialEq, Eq)]
enum Kind {
    Fish,
    Herb,
    Ore,
    Skin,
}

const FISH_SPELLS: [i64; 8] = [
    7_620, 7_731, 7_732, 18_248, 1_240_948, 1_240_959, 1_240_970, 1_240_983,
];
const HERB_SPELLS: [i64; 11] = [
    2_366, 2_368, 2_369, 2_371, 3_570, 11_993, 1_235_236, 1_240_949, 1_240_960, 1_240_971,
    1_240_984,
];
const ORE_SPELLS: [i64; 12] = [
    2_575, 2_576, 2_577, 2_578, 2_579, 3_564, 10_248, 1_235_230, 1_240_951, 1_240_962, 1_240_973,
    1_240_986,
];
const SKIN_SPELLS: [i64; 8] = [
    8_613, 8_617, 8_618, 10_768, 1_240_952, 1_240_963, 1_240_974, 1_240_987,
];

const SKILL_PARENTS: [(i64, i64); 12] = [
    (2_937, 171),
    (2_938, 164),
    (2_939, 185),
    (2_940, 333),
    (2_941, 202),
    (2_942, 129),
    (2_943, 356),
    (2_944, 182),
    (2_945, 165),
    (2_946, 186),
    (2_947, 393),
    (2_948, 197),
];

const MAIN_HAND_SLOT: &str = "16";
const LOOT_WINDOW_MS: i64 = 2_000;
const SKILL_UP_WINDOW_MS: i64 = 2_000;
const TRIP_GAP_MS: i64 = 120_000;
const CANCELLED_CHANNEL_S: f64 = 2.0;
const STALE_CAST_MS: i64 = 120_000;

impl Kind {
    const ALL: [Kind; 4] = [Kind::Fish, Kind::Herb, Kind::Ore, Kind::Skin];

    fn of_spell(spell: i64) -> Option<Kind> {
        Kind::ALL.into_iter().find(|k| k.spells().contains(&spell))
    }

    fn spells(self) -> &'static [i64] {
        match self {
            Kind::Fish => &FISH_SPELLS,
            Kind::Herb => &HERB_SPELLS,
            Kind::Ore => &ORE_SPELLS,
            Kind::Skin => &SKIN_SPELLS,
        }
    }

    fn name(self) -> &'static str {
        match self {
            Kind::Fish => "fish",
            Kind::Herb => "herb",
            Kind::Ore => "ore",
            Kind::Skin => "skin",
        }
    }

    fn skill_line(self) -> i64 {
        match self {
            Kind::Fish => 356,
            Kind::Herb => 182,
            Kind::Ore => 186,
            Kind::Skin => 393,
        }
    }

    fn loot_source(self) -> &'static str {
        match self {
            Kind::Skin => "c",
            _ => "g",
        }
    }

    fn index(self) -> usize {
        Kind::ALL.iter().position(|k| *k == self).unwrap_or(0)
    }
}

fn skill_line(id: i64) -> i64 {
    SKILL_PARENTS
        .iter()
        .find(|(child, _)| *child == id)
        .map_or(id, |(_, parent)| *parent)
}

fn seconds_of(f: &[&str], i: usize) -> Option<f64> {
    field(f, i)
        .trim()
        .parse::<f64>()
        .ok()
        .filter(|s| s.is_finite() && *s >= 0.0)
}

fn flag(f: &[&str], i: usize) -> Option<bool> {
    match field(f, i) {
        "1" => Some(true),
        "0" => Some(false),
        _ => None,
    }
}

/// The last `P` read: map, x and y.
pub type Pos = Option<[Option<i64>; 3]>;

#[derive(Clone, Copy)]
pub struct Here {
    pub level: Option<i64>,
    pub zone_id: Option<i64>,
}

#[derive(Clone, Copy)]
pub struct Present {
    pub professions: bool,
    pub loot: bool,
}

struct Gather {
    kind: Kind,
    spell: i64,
    at: i64,
    end: i64,
    here: Here,
    seconds: Option<f64>,
    looted: bool,
    loot_at: Option<i64>,
    source_id: Option<i64>,
    again: Option<bool>,
    skill: Option<i64>,
    skill_up: bool,
    gap: Option<f64>,
    lure: Option<(i64, f64)>,
    pole: Option<i64>,
    in_combat: bool,
    pos: Pos,
}

impl Gather {
    fn anchor(&self) -> i64 {
        self.loot_at.unwrap_or(self.end)
    }

    fn cancelled(&self, known: Present) -> bool {
        self.kind == Kind::Fish
            && known.loot
            && !self.looted
            && self.seconds.is_some_and(|s| s < CANCELLED_CHANNEL_S)
    }
}

#[derive(Clone)]
struct Source {
    at: i64,
    here: Here,
    kind: Kind,
    source_id: Option<i64>,
    skill: Option<i64>,
    map: Option<i64>,
    container: Option<i64>,
}

struct Craft {
    at: i64,
    here: Here,
    fields: [Option<i64>; 6],
    profession: Option<i64>,
    difficulty: Option<i64>,
    skill: Option<i64>,
    skill_up: bool,
    enchant: Option<bool>,
    sitting_gap: Option<f64>,
}

#[derive(Default)]
pub struct Professions {
    pos: Pos,
    main_hand: Option<i64>,
    in_combat: bool,
    ranks: BTreeMap<i64, i64>,
    rises: Vec<(i64, i64)>,
    last_cast: Option<(i64, i64)>,
    lures: Vec<(i64, i64, i64)>,
    fishing: Option<Gather>,
    gathers: Vec<Gather>,
    last_end: [Option<i64>; 4],
    awaiting: Option<usize>,
    loot_read: Option<Kind>,
    items: Vec<(Source, i64, i64)>,
    receipts: Option<(i64, Source)>,
    caught: BTreeSet<i64>,
    window: Option<i64>,
    cb: bool,
    difficulty: BTreeMap<i64, i64>,
    crafts: Vec<Craft>,
    last_craft: BTreeMap<Option<i64>, i64>,
}

impl Professions {
    /// The gathering kind (`fish`, `herb`, `ore` or `skin`) of the attempt the last `LO` line was matched to, by the
    /// rule that sets `gathers.looted`. None when that window followed no gathering cast.
    pub fn gathered(&self) -> Option<&'static str> {
        self.loot_read.map(Kind::name)
    }

    /// The last `P` read at or before the line being read.
    pub fn pos(&self) -> Pos {
        self.pos
    }

    /// Reads one line; true when it was a craft that wrote a `crafts` row (not a lure).
    pub fn on_line(&mut self, at: i64, code: &str, f: &[&str], here: Here) -> bool {
        match code {
            "P" => self.pos = Some([num(f, 0), num(f, 1), num(f, 2)]),
            "F" => self.in_combat = field(f, 0) == "1",
            "G" if field(f, 0) == MAIN_HAND_SLOT => self.main_hand = num(f, 1).filter(|i| *i > 0),
            "SL" => self.on_skill(at, f),
            "C" => self.on_cast(at, f, here),
            "CH" => self.on_channel(at, f, here),
            "LO" => self.on_loot(at, f, here),
            "O" => self.on_receipt(at, f),
            "TS" if field(f, 0) == "1" => {
                self.window = num(f, 6).filter(|p| *p > 0).or(num(f, 1));
            }
            "CB" => self.cb = true,
            "CS" => self.cb = false,
            "RD" => match (num(f, 0), num(f, 1)) {
                (Some(recipe), Some(d)) => {
                    self.difficulty.insert(recipe, d);
                }
                (Some(recipe), None) => {
                    self.difficulty.remove(&recipe);
                }
                _ => {}
            },
            "CR" => return self.on_craft(at, f, here),
            _ => {}
        }
        false
    }

    fn on_skill(&mut self, at: i64, f: &[&str]) {
        let (Some(id), Some(rank)) = (num(f, 0), num(f, 1)) else {
            return;
        };
        let line = skill_line(id);
        let before = self.ranks.insert(line, rank);
        if before.is_some_and(|b| rank > b) && field(f, 4) != "s" {
            self.rises.push((at, line));
        }
    }

    fn start(&self, kind: Kind, spell: i64, at: i64, here: Here) -> Gather {
        let skill = self.ranks.get(&kind.skill_line()).copied();
        let pole = (kind == Kind::Fish).then_some(self.main_hand).flatten();
        let lure = pole.and_then(|p| {
            let (_, spell, applied) = self.lures.iter().rev().find(|(item, ..)| *item == p)?;
            Some((*spell, (at - applied).max(0) as f64 / 1000.0))
        });
        let gap = self.last_end[kind.index()].and_then(|end| {
            let d = at - end;
            (d <= TRIP_GAP_MS).then_some(d.max(0) as f64 / 1000.0)
        });
        Gather {
            kind,
            spell,
            at,
            end: at,
            here,
            seconds: None,
            looted: false,
            loot_at: None,
            source_id: None,
            again: None,
            skill,
            skill_up: false,
            gap,
            lure,
            pole,
            in_combat: self.in_combat,
            pos: self.pos,
        }
    }

    fn on_cast(&mut self, at: i64, f: &[&str], here: Here) {
        let Some(spell) = num(f, 0) else { return };
        self.last_cast = Some((at, spell));
        let Some(kind) = Kind::of_spell(spell) else {
            return;
        };
        if kind == Kind::Fish {
            self.fishing = Some(self.start(kind, spell, at, here));
            return;
        }
        let seconds = seconds_of(f, 1);
        let started = at - seconds.map_or(0, |s| (s * 1000.0).round() as i64);
        let mut g = self.start(kind, spell, started, here);
        g.seconds = seconds;
        g.end = at;
        self.finish_cast(g);
    }

    fn on_channel(&mut self, at: i64, f: &[&str], here: Here) {
        let Some(spell) = num(f, 0) else { return };
        if Kind::of_spell(spell) != Some(Kind::Fish) {
            return;
        }
        let seconds = seconds_of(f, 1);
        let pending = self
            .fishing
            .take()
            .filter(|g| g.spell == spell && at - g.at <= STALE_CAST_MS);
        let mut g = pending.unwrap_or_else(|| {
            let started = at - seconds.map_or(0, |s| (s * 1000.0).round() as i64);
            self.start(Kind::Fish, spell, started, here)
        });
        g.seconds = seconds;
        g.end = at;
        self.finish_cast(g);
    }

    fn finish_cast(&mut self, g: Gather) {
        self.last_end[g.kind.index()] = Some(g.end);
        self.gathers.push(g);
        self.awaiting = Some(self.gathers.len() - 1);
    }

    fn on_loot(&mut self, at: i64, f: &[&str], here: Here) {
        self.receipts = None;
        self.loot_read = None;
        let source = field(f, 0);
        let id = num(f, 1);
        if let Some(i) = self.awaiting
            && self.gathers[i].kind.loot_source() == source
            && at - self.gathers[i].end <= LOOT_WINDOW_MS
        {
            self.awaiting = None;
            let g = &mut self.gathers[i];
            self.loot_read = Some(g.kind);
            g.looted = true;
            g.loot_at = Some(at);
            g.source_id = id;
            g.again = flag(f, 4);
            self.last_end[g.kind.index()] = Some(at);
            let src = Source {
                at,
                here,
                kind: g.kind,
                source_id: id,
                skill: g.skill,
                map: g.pos.and_then(|p| p[0]),
                container: None,
            };
            self.take_items(at, f, src);
            return;
        }
        let Some(item) = id.filter(|i| source == "i" && self.caught.contains(i)) else {
            return;
        };
        let src = Source {
            at,
            here,
            kind: Kind::Fish,
            source_id: None,
            skill: self.ranks.get(&Kind::Fish.skill_line()).copied(),
            map: self.pos.and_then(|p| p[0]),
            container: Some(item),
        };
        self.take_items(at, f, src);
    }

    fn take_items(&mut self, at: i64, f: &[&str], src: Source) {
        if f.len() < 4 {
            self.receipts = Some((at + LOOT_WINDOW_MS, src));
            return;
        }
        for (item, quantity) in field(f, 3).split(',').filter_map(item_of) {
            self.add_item(src.clone(), item, quantity);
        }
    }

    fn add_item(&mut self, src: Source, item: i64, quantity: i64) {
        if src.kind == Kind::Fish && src.container.is_none() {
            self.caught.insert(item);
        }
        self.items.push((src, item, quantity));
    }

    fn on_receipt(&mut self, at: i64, f: &[&str]) {
        let Some((until, src)) = &self.receipts else {
            return;
        };
        if at > *until {
            return;
        }
        let (src, item) = (src.clone(), num(f, 0));
        if let Some(item) = item {
            self.add_item(src, item, num(f, 1).unwrap_or(1));
        }
    }

    fn on_craft(&mut self, at: i64, f: &[&str], here: Here) -> bool {
        let item = num(f, 0);
        let enchant = flag(f, 11);
        let started = self.cb;
        self.cb = false;
        let recipe = num(f, 4);
        let on_main_hand = match self.main_hand {
            Some(held) => item == Some(held),
            None => recipe.is_none(),
        };
        if !started && enchant == Some(true) && item.is_some() && on_main_hand {
            self.main_hand = self.main_hand.or(item);
            if let (Some(item), Some((cast_at, spell))) = (item, self.last_cast)
                && at - cast_at <= LOOT_WINDOW_MS
            {
                self.lures.push((item, spell, at));
            }
            return false;
        }
        let profession = self.window;
        let skill = profession.and_then(|p| self.ranks.get(&skill_line(p)).copied());
        let sitting_gap = self.last_craft.insert(profession, at).and_then(|before| {
            (at - before <= TRIP_GAP_MS).then_some((at - before) as f64 / 1000.0)
        });
        self.crafts.push(Craft {
            at,
            here,
            fields: [item, num(f, 1), num(f, 2), num(f, 3), recipe, num(f, 5)],
            profession,
            difficulty: recipe.and_then(|r| self.difficulty.get(&r).copied()),
            skill,
            skill_up: false,
            enchant,
            sitting_gap,
        });
        true
    }

    fn credit_skill_ups(&mut self, kept: &mut [Gather]) {
        for &(at, line) in &self.rises {
            if let Some(g) = kept.iter_mut().rev().find(|g| {
                g.kind.skill_line() == line
                    && (g.anchor()..=g.anchor() + SKILL_UP_WINDOW_MS).contains(&at)
            }) {
                g.skill_up = true;
            }
            if let Some(c) = self.crafts.iter_mut().rev().find(|c| {
                c.profession.map(skill_line) == Some(line)
                    && (c.at..=c.at + SKILL_UP_WINDOW_MS).contains(&at)
            }) {
                c.skill_up = true;
            }
        }
    }

    pub fn finish(mut self, session: i64, known: Present, w: &mut Rows) {
        let mut kept: Vec<Gather> = std::mem::take(&mut self.gathers)
            .into_iter()
            .filter(|g| !g.cancelled(known))
            .collect();
        self.credit_skill_ups(&mut kept);
        for c in &self.crafts {
            prefix(w, "crafts", session, c.at, c.here.level, c.here.zone_id);
            let [item, quantity, crit, multicraft, recipe, quality] = c.fields;
            w.opt("item_id", item)
                .opt("quantity", quantity)
                .opt("crit", crit)
                .opt("multicraft", multicraft)
                .opt("recipe_id", recipe)
                .opt("quality", quality)
                .opt("profession_id", c.profession)
                .opt("difficulty", c.difficulty)
                .opt("skill", c.skill)
                .bool("skill_up", c.skill_up)
                .opt_bool("enchant", c.enchant)
                .opt_f64("sitting_gap", c.sitting_gap);
            w.end();
        }
        if !known.professions {
            return;
        }
        for g in &kept {
            Self::write_gather(session, g, known, w);
        }
        for (src, item, quantity) in &self.items {
            prefix(
                w,
                "gather_items",
                session,
                src.at,
                src.here.level,
                src.here.zone_id,
            );
            w.str("kind", src.kind.name())
                .opt("source_id", src.source_id)
                .int("item_id", *item)
                .int("quantity", *quantity)
                .opt("skill", src.skill)
                .opt("map", src.map)
                .opt("container_item_id", src.container);
            w.end();
        }
    }

    fn write_gather(session: i64, g: &Gather, known: Present, w: &mut Rows) {
        prefix(w, "gathers", session, g.at, g.here.level, g.here.zone_id);
        w.str("kind", g.kind.name())
            .int("spell_id", g.spell)
            .opt_f64("seconds", g.seconds)
            .opt_bool("looted", known.loot.then_some(g.looted))
            .opt("source_id", g.source_id)
            .opt_bool("again", g.again)
            .opt("skill", g.skill)
            .bool("skill_up", g.skill_up)
            .opt_f64("gap", g.gap)
            .opt("lure_spell", g.lure.map(|(spell, _)| spell))
            .opt_f64("lure_age", g.lure.map(|(_, age)| age))
            .opt("pole_item", g.pole)
            .bool("in_combat", g.in_combat);
        let [map, x, y] = g.pos.unwrap_or([None; 3]);
        w.opt("map", map).opt("x", x).opt("y", y);
        w.end();
    }
}

pub(crate) fn item_of(el: &str) -> Option<(i64, i64)> {
    if el.starts_with('m') || el.starts_with('c') {
        return None;
    }
    let item = leading_id(el)? as i64;
    let quantity = el
        .split_once('x')
        .and_then(|(_, q)| leading_id(q))
        .unwrap_or(1) as i64;
    Some((item, quantity))
}
