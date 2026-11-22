//! The group a character plays in: the stretches in which nothing about the group changed (`group_spans`, from `GP`,
//! `GM` and `GL`), what the other members received (`group_loot`, from `LT`) and the loot rolls (`loot_rolls`, from
//! `LD`, `RW` and `RL`). Other players are read as the upload carries them, a pseudonym each, and a span holds no
//! name. docs/ROWS.md has the rows.

use crate::decode::{field, num};
use crate::rows::Rows;
use crate::talents::Ctx;
use std::collections::{BTreeMap, BTreeSet, HashMap};

const ROLL_WINDOW_MS: i64 = 60_000;
const ROLL_ECHO_MS: i64 = 2_000;

#[derive(Clone)]
pub struct Rule {
    pub method: String,
    pub threshold: Option<i64>,
    pub size: Option<i64>,
}

impl Rule {
    fn write(&self, w: &mut Rows) {
        w.str("loot_rule", &self.method)
            .opt("threshold", self.threshold)
            .opt("group_size", self.size);
    }
}

fn name_key(name: &str) -> String {
    name.split_whitespace()
        .collect::<Vec<_>>()
        .join("-")
        .to_lowercase()
}

#[derive(Default)]
struct Member {
    role: String,
    level: Option<i64>,
    class: String,
    guild_mate: bool,
    friend: bool,
}

#[derive(Clone, PartialEq, Eq)]
struct SpanKey {
    size: Option<i64>,
    raid: Option<bool>,
    members_known: i64,
    classes: String,
    tanks: i64,
    healers: i64,
    damage: i64,
    no_role: i64,
    level_min: Option<i64>,
    level_max: Option<i64>,
    guild_mates: i64,
    friends: i64,
    skinners: Option<i64>,
}

struct Open {
    start: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    key: SpanKey,
}

struct Receipt {
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    kind: String,
    who: String,
    item: i64,
    quantity: i64,
    rule: Rule,
}

struct Event {
    no: i64,
    last: i64,
    closed_at: Option<i64>,
    winner: bool,
    all_passed: bool,
}

struct Roll {
    event: usize,
    who: String,
    mine: bool,
    item: i64,
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    last: i64,
    joined: bool,
    passed: bool,
    won: bool,
    kind: Option<&'static str>,
    number: Option<i64>,
    decide_ms: Option<i64>,
    rule: Rule,
}

#[derive(Default)]
struct Line {
    joined: bool,
    passed: bool,
    won: bool,
    winner: bool,
    closes: bool,
    kind: Option<&'static str>,
    number: Option<i64>,
    decide_ms: Option<i64>,
}

fn roll_kind(n: Option<i64>) -> Option<&'static str> {
    match n {
        Some(0) => Some("pass"),
        Some(1) => Some("need"),
        Some(2) => Some("greed"),
        Some(3) => Some("disenchant"),
        Some(4) => Some("transmog"),
        _ => None,
    }
}

pub struct GroupBook {
    own: String,
    own_key: String,
    in_group: bool,
    gp_size: Option<i64>,
    roster_seen: bool,
    others: BTreeSet<String>,
    known: BTreeMap<String, Member>,
    raid: Option<bool>,
    skinning: Option<bool>,
    floor: i64,
    open: Option<Open>,
    classes: BTreeMap<String, String>,
    receipts: Vec<Receipt>,
    rolls: Vec<Roll>,
    live: HashMap<(String, i64), usize>,
    events: Vec<Event>,
    item_event: HashMap<i64, usize>,
}

impl GroupBook {
    pub fn new(own: &str, professions: bool) -> Self {
        GroupBook {
            own: own.to_string(),
            own_key: name_key(own),
            in_group: false,
            gp_size: None,
            roster_seen: false,
            others: BTreeSet::new(),
            known: BTreeMap::new(),
            raid: None,
            skinning: professions.then_some(false),
            floor: i64::MAX,
            open: None,
            classes: BTreeMap::new(),
            receipts: Vec::new(),
            rolls: Vec::new(),
            live: HashMap::new(),
            events: Vec::new(),
            item_event: HashMap::new(),
        }
    }

    /// The session began at `t`: a span opened by the login lines starts here.
    pub fn on_started(&mut self, t: i64) {
        self.floor = t;
        if let Some(o) = self.open.as_mut() {
            o.start = o.start.max(t);
        }
    }

    /// The `at` of the span in force, which is also the `at` of its `group_spans` row.
    pub fn at(&self) -> Option<i64> {
        self.open.as_ref().map(|o| o.start.max(self.floor))
    }

    pub fn on_gp(&mut self, ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        self.in_group = field(f, 0) == "1";
        self.gp_size = if self.in_group { num(f, 1) } else { None };
        self.roster_seen = false;
        self.others.clear();
        self.known.clear();
        self.refresh(ctx, w);
    }

    pub fn on_gm(&mut self, ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        let key = name_key(field(f, 1));
        if key.is_empty() || key == self.own_key {
            return;
        }
        if field(f, 0) == "1" {
            self.others.insert(key);
            self.roster_seen = true;
        } else {
            self.others.remove(&key);
            self.known.remove(&key);
        }
        self.refresh(ctx, w);
    }

    pub fn on_gl(&mut self, ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        match field(f, 0) {
            "m" => {
                let key = name_key(field(f, 1));
                if key.is_empty() {
                    return;
                }
                let class = field(f, 9).to_string();
                if !class.is_empty() {
                    self.classes.insert(key.clone(), class.clone());
                }
                if key != self.own_key {
                    self.others.insert(key.clone());
                    self.roster_seen = true;
                }
                self.known.insert(
                    key,
                    Member {
                        role: field(f, 2).to_string(),
                        level: num(f, 8),
                        class,
                        guild_mate: field(f, 6) == "1",
                        friend: field(f, 7) == "1",
                    },
                );
            }
            "s" => self.raid = Some(field(f, 1) == "1"),
            _ => return,
        }
        self.refresh(ctx, w);
    }

    /// A skill line was read. A baseline read (the scan at the session's start) says what was known all along, so it
    /// does not split the span in force.
    pub fn on_skill(&mut self, ctx: &Ctx, w: &mut Rows, has_skinning: bool, baseline: bool) {
        if self.skinning.is_none_or(|s| s == has_skinning) {
            return;
        }
        self.skinning = Some(has_skinning);
        if baseline {
            if let Some(o) = self.open.as_mut() {
                o.key.skinners = Some(i64::from(has_skinning));
            }
            return;
        }
        self.refresh(ctx, w);
    }

    fn key(&self) -> SpanKey {
        let mut classes: Vec<&str> = self
            .known
            .values()
            .map(|m| m.class.as_str())
            .filter(|c| !c.is_empty())
            .collect();
        classes.sort_unstable();
        let role = |r: &str| self.known.values().filter(|m| m.role == r).count() as i64;
        let (tanks, healers, damage) = (role("t"), role("h"), role("d"));
        let levels = || self.known.values().filter_map(|m| m.level);
        SpanKey {
            size: if self.roster_seen {
                Some(1 + self.others.len() as i64)
            } else {
                self.gp_size
            },
            raid: self.raid,
            members_known: self.known.len() as i64,
            classes: classes.join(","),
            tanks,
            healers,
            damage,
            no_role: self.known.len() as i64 - tanks - healers - damage,
            level_min: levels().min(),
            level_max: levels().max(),
            guild_mates: self.known.values().filter(|m| m.guild_mate).count() as i64,
            friends: self.known.values().filter(|m| m.friend).count() as i64,
            skinners: self.skinning.map(i64::from),
        }
    }

    fn refresh(&mut self, ctx: &Ctx, w: &mut Rows) {
        let Some(key) = self.in_group.then(|| self.key()) else {
            if let Some(o) = self.open.take() {
                self.write(&o, ctx.at, ctx.session, w);
            }
            return;
        };
        match self.open.take() {
            Some(o) if o.key == key => self.open = Some(o),
            Some(mut o) if o.start == ctx.at => {
                o.key = key;
                self.open = Some(o);
            }
            old => {
                if let Some(o) = old {
                    self.write(&o, ctx.at, ctx.session, w);
                }
                self.open = Some(Open {
                    start: ctx.at,
                    level: ctx.level,
                    zone_id: ctx.zone_id,
                    key,
                });
            }
        }
    }

    fn write(&self, o: &Open, end: i64, session: i64, w: &mut Rows) {
        let start = o.start.max(self.floor);
        if end <= start {
            return;
        }
        let k = &o.key;
        Ctx {
            session,
            at: start,
            level: o.level,
            zone_id: o.zone_id,
        }
        .begin(w, "group_spans");
        w.int("end", end)
            .opt("size", k.size)
            .opt_bool("raid", k.raid)
            .int("members_known", k.members_known)
            .text("classes", &k.classes)
            .int("tanks", k.tanks)
            .int("healers", k.healers)
            .int("damage", k.damage)
            .int("no_role", k.no_role)
            .opt("level_min", k.level_min)
            .opt("level_max", k.level_max)
            .int("guild_mates", k.guild_mates)
            .int("friends", k.friends)
            .opt("skinners", k.skinners);
        w.end();
    }

    /// One loot line: `LT` (another member received an item), `LD` (another player's roll), `RL` and `RW` (your own).
    pub fn on_loot(&mut self, code: &str, ctx: &Ctx, rule: &Rule, f: &[&str]) {
        match code {
            "LT" => self.on_lt(ctx, rule, f),
            "LD" => self.on_ld(ctx, rule, f),
            "RL" => {
                let (kind, item) = (roll_kind(num(f, 0)), num(f, 1));
                let line = Line {
                    joined: kind != Some("pass"),
                    passed: kind == Some("pass"),
                    kind,
                    ..Line::default()
                };
                self.own_roll(ctx, rule, item, line);
            }
            "RW" => {
                let item = num(f, 1);
                let kind = roll_kind(num(f, 2));
                let line = match field(f, 0) {
                    "o" => Line {
                        decide_ms: num(f, 4),
                        ..Line::default()
                    },
                    "w" => Line {
                        joined: true,
                        won: true,
                        winner: true,
                        closes: true,
                        kind,
                        number: num(f, 3),
                        ..Line::default()
                    },
                    "l" => Line {
                        joined: true,
                        winner: true,
                        closes: true,
                        kind,
                        number: num(f, 3),
                        ..Line::default()
                    },
                    _ => return,
                };
                self.own_roll(ctx, rule, item, line);
            }
            _ => {}
        }
    }

    fn on_lt(&mut self, ctx: &Ctx, rule: &Rule, f: &[&str]) {
        let kind = field(f, 0);
        let who = field(f, 1);
        let (Some(item), true) = (num(f, 2), matches!(kind, "l" | "p") && !who.is_empty()) else {
            return;
        };
        self.receipts.push(Receipt {
            at: ctx.at,
            level: ctx.level,
            zone_id: ctx.zone_id,
            kind: kind.to_string(),
            who: who.to_string(),
            item,
            quantity: num(f, 3).unwrap_or(1),
            rule: rule.clone(),
        });
    }

    fn on_ld(&mut self, ctx: &Ctx, rule: &Rule, f: &[&str]) {
        let Some(item) = num(f, 2) else {
            return;
        };
        let who = field(f, 1);
        let kind = roll_kind(num(f, 3));
        let number = num(f, 4);
        let line = match field(f, 0) {
            "a" => {
                let e = self.event_for(item, ctx.at, true);
                self.events[e].all_passed = true;
                self.events[e].last = ctx.at;
                self.events[e].closed_at = Some(ctx.at);
                return;
            }
            "r" | "s" => Line {
                joined: true,
                kind,
                number,
                ..Line::default()
            },
            "p" => Line {
                passed: true,
                kind: Some("pass"),
                ..Line::default()
            },
            "w" => Line {
                joined: true,
                won: true,
                winner: true,
                closes: true,
                kind,
                number,
                ..Line::default()
            },
            _ => return,
        };
        if who.is_empty() {
            return;
        }
        let mine = name_key(who) == self.own_key;
        let name = if mine {
            self.own.clone()
        } else {
            who.to_string()
        };
        self.roll(ctx, rule, &name, mine, item, line);
    }

    fn own_roll(&mut self, ctx: &Ctx, rule: &Rule, item: Option<i64>, line: Line) {
        if let Some(item) = item {
            let name = self.own.clone();
            self.roll(ctx, rule, &name, true, item, line);
        }
    }

    fn event_for(&mut self, item: i64, at: i64, closes: bool) -> usize {
        if let Some(&e) = self.item_event.get(&item) {
            let ev = &self.events[e];
            let over = at - ev.last > ROLL_WINDOW_MS
                || (!closes && ev.closed_at.is_some_and(|c| at - c > ROLL_ECHO_MS));
            if !over {
                return e;
            }
        }
        self.events.push(Event {
            no: self.events.len() as i64 + 1,
            last: at,
            closed_at: None,
            winner: false,
            all_passed: false,
        });
        let e = self.events.len() - 1;
        self.item_event.insert(item, e);
        e
    }

    fn roll(&mut self, ctx: &Ctx, rule: &Rule, who: &str, mine: bool, item: i64, line: Line) {
        let key = (name_key(who), item);
        let at = ctx.at;
        if let Some(&i) = self.live.get(&key) {
            let r = &self.rolls[i];
            let outcome = self.events[r.event].closed_at;
            if at - r.last > ROLL_WINDOW_MS
                || (!line.closes && outcome.is_some_and(|c| at - c > ROLL_ECHO_MS))
            {
                self.live.remove(&key);
            }
        }
        let i = match self.live.get(&key) {
            Some(&i) => i,
            None => {
                let event = self.event_for(item, at, line.closes);
                self.rolls.push(Roll {
                    event,
                    who: who.to_string(),
                    mine,
                    item,
                    at,
                    level: ctx.level,
                    zone_id: ctx.zone_id,
                    last: at,
                    joined: false,
                    passed: false,
                    won: false,
                    kind: None,
                    number: None,
                    decide_ms: None,
                    rule: rule.clone(),
                });
                self.live.insert(key, self.rolls.len() - 1);
                self.rolls.len() - 1
            }
        };
        let r = &mut self.rolls[i];
        r.last = at;
        r.joined |= line.joined;
        r.passed |= line.passed;
        r.won |= line.won;
        r.kind = line.kind.or(r.kind);
        r.number = line.number.or(r.number);
        r.decide_ms = r.decide_ms.or(line.decide_ms);
        let ev = &mut self.events[r.event];
        ev.last = at;
        ev.winner |= line.winner;
        if line.closes {
            ev.closed_at = Some(at);
        }
    }

    fn class_of(&self, who: &str, pn: &BTreeMap<String, String>) -> &str {
        let name = pn.get(who).map_or(who, String::as_str);
        self.classes.get(&name_key(name)).map_or("", String::as_str)
    }

    /// Close the span in force at the session's end and write the receipts and rolls.
    pub fn finish(&mut self, session: i64, end: i64, pn: &BTreeMap<String, String>, w: &mut Rows) {
        if let Some(o) = self.open.take() {
            self.write(&o, end, session, w);
        }
        for r in std::mem::take(&mut self.receipts) {
            Ctx {
                session,
                at: r.at,
                level: r.level,
                zone_id: r.zone_id,
            }
            .begin(w, "group_loot");
            w.str("kind", &r.kind)
                .str("who", &r.who)
                .text("class_file", self.class_of(&r.who, pn))
                .int("item_id", r.item)
                .int("quantity", r.quantity);
            r.rule.write(w);
            w.null("source_kind").null("source_id");
            w.end();
        }
        let mut order: Vec<usize> = (0..self.rolls.len()).collect();
        order.sort_by_key(|&i| (self.events[self.rolls[i].event].no, i));
        for i in order {
            let r = &self.rolls[i];
            let ev = &self.events[r.event];
            let outcome = if ev.all_passed {
                "all_passed"
            } else if r.won {
                "won"
            } else if r.joined && ev.winner {
                "lost"
            } else if r.passed && !r.joined {
                "passed"
            } else {
                ""
            };
            Ctx {
                session,
                at: r.at,
                level: r.level,
                zone_id: r.zone_id,
            }
            .begin(w, "loot_rolls");
            w.int("roll_no", ev.no)
                .int("item_id", r.item)
                .str("who", &r.who)
                .bool("mine", r.mine)
                .text("class_file", self.class_of(&r.who, pn))
                .text("roll_type", r.kind.unwrap_or(""))
                .opt("roll", r.number)
                .text("outcome", outcome)
                .opt("decide_ms", r.decide_ms);
            r.rule.write(w);
            w.end();
        }
    }
}
