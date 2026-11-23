//! The decoder: the parts of ONE session in, fact rows out (docs/ROWS.md).
//!
//! A pure function of those parts. Nothing from another session is read, so a
//! session can be decoded again at any time; the few rules that join sessions
//! (dungeon runs, the balance walk back) are for the ingest job and are in
//! ROWS.md.

use crate::baselines::{Baselines, Ctx as BaselineCtx};
use crate::bosses::{Bosses, Seen};
use crate::build::{at_near, parse_header};
use crate::camp::{Camp, Known};
use crate::class::{Class, Now, OPEN_WORLD, place_of};
use crate::death::Hit;
use crate::group::{GroupBook, Rule};
use crate::peer::Peers;
use crate::professions::{Here, Present, Professions, item_of};
use crate::reference::Reference;
use crate::rows::Rows;
use crate::social::Social;
use crate::tables::{Groups, NameRefs, leading_id};
use crate::talents::{Ctx, Talents};
use crate::tallies::SAME_DEATH_MS;
use std::collections::{BTreeMap, BTreeSet};

/// A dungeon left less than this long ago is the same run when it is entered again.
pub const RUN_REJOIN_MS: i64 = 30 * 60 * 1000;
/// The rule version of the decoded rows: bumped on any change to the rows the
/// decoder writes, so the stored rows are decoded again (`scarlet-part
/// decoder-version`).
pub const DECODER_VERSION: u32 = 18;

const HOUR_MS: i64 = 3_600_000;
const COPPER_MAX: i64 = 1_000_000_000_000_000;
const MAX_HOUR_CUTS: usize = 24 * 7;

/// XP a level needs to reach the next one, level 1 first (docs/OVERVIEW.md).
const NEED: [i64; 59] = [
    400, 900, 1400, 2100, 2800, 3600, 4500, 5400, 6500, 7600, 8800, 10100, 11400, 12900, 14400,
    16000, 17700, 19400, 21300, 23200, 25200, 27300, 29400, 31700, 34000, 36400, 38900, 41400,
    44300, 47400, 50800, 54500, 58600, 62800, 67100, 71600, 76100, 80800, 85700, 90700, 95800,
    101000, 106300, 111800, 117500, 123200, 129100, 135100, 141200, 147500, 153900, 160400, 167100,
    173900, 180800, 187900, 195000, 202300, 209800,
];

pub struct Summary {
    pub rows: usize,
    pub lines: usize,
    pub skipped: usize,
    pub session: i64,
    pub played_ms: i64,
}

struct DLine<'a> {
    t: i64,
    rank: u16,
    seq: u32,
    code: &'a str,
    rest: &'a str,
}

/// The order of lines recorded in the same millisecond, once their parts are
/// merged: by group (core first, so a level change comes before the lines that
/// follow it), `ES` last. Lines of one group keep their recorded order; the
/// recorded order between groups is not in the parts (docs/ROWS.md, Ties).
fn tie_rank(code: &str, group: usize) -> u16 {
    if code == "ES" { u16::MAX } else { group as u16 }
}

/// A whole number from a field; `?` and empty are none.
pub fn num(f: &[&str], i: usize) -> Option<i64> {
    let s = f.get(i)?.trim();
    s.parse::<i64>()
        .ok()
        .or_else(|| s.parse::<f64>().ok().map(|x| x.round() as i64))
}

fn level(f: &[&str], i: usize) -> Option<i64> {
    num(f, i).filter(|l| (1..=i64::from(i16::MAX)).contains(l))
}

pub(crate) fn field<'a>(f: &[&'a str], i: usize) -> &'a str {
    f.get(i).copied().unwrap_or("")
}

pub(crate) fn corpse_key<'a>(f: &[&'a str], i: usize) -> &'a str {
    let key = field(f, i);
    let hex = key.bytes().all(|b| matches!(b, b'0'..=b'9' | b'a'..=b'f'));
    if key.len() == 10 && hex { key } else { "" }
}

pub fn round_s(ms: i64) -> i64 {
    (ms + 500).div_euclid(1000)
}

#[derive(Clone, PartialEq, Eq)]
struct SpanKey {
    level: Option<i64>,
    zone: String,
    zone_id: Option<i64>,
    inst_type: String,
    inst_id: Option<i64>,
}

struct Span {
    key: SpanKey,
    start: i64,
    afk: Vec<(i64, i64)>,
    afk_since: Option<i64>,
}

#[derive(Default)]
struct Death {
    t: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    pos: Option<(i64, i64, i64)>,
    killer: Option<[String; 6]>,
    spell: Option<i64>,
    damage: Option<i64>,
    overkill: Option<i64>,
    hits: Option<i64>,
    event: String,
    environmental_type: String,
    npc_id: Option<i64>,
    recap: BTreeMap<i64, Hit>,
}

struct Run {
    inst: i64,
    name: String,
    inst_type: String,
    difficulty: Option<i64>,
    entered: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    left: Option<i64>,
    inside_ms: i64,
    entries: i64,
    bosses: BTreeSet<(Option<i64>, String)>,
    deaths: i64,
    group_size: Option<i64>,
    /// Start of the stay in progress.
    stay: i64,
    /// The session ended while inside.
    at_end: bool,
}

/// Instance id, instance type, difficulty and subzone of a zone's first `Z` line.
type ZoneInfo = (Option<i64>, String, Option<i64>, String);

#[derive(Default)]
struct ItemAcc {
    info: Vec<String>,
    quality: Option<i64>,
    tooltip: String,
}

#[derive(Clone)]
struct LootWindow {
    at: i64,
    kind: String,
    id: Option<i64>,
    first_read: Option<bool>,
    read: Option<&'static str>,
    key: String,
}

struct PendingPoint {
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    map: Option<i64>,
    x: Option<i64>,
    y: Option<i64>,
    facing: Option<i64>,
}

struct PendingFlight {
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    map: Option<i64>,
    x: Option<i64>,
    y: Option<i64>,
}

/// Which groups have a part in this call. Without one the counts it feeds are
/// unknown (null), not zero.
#[derive(Clone, Copy)]
struct Has {
    xp: bool,
    quests: bool,
    combat: bool,
    economy: bool,
    professions: bool,
    loot: bool,
}

struct Dec<'o> {
    w: Rows<'o>,
    has: Has,
    session: i64,
    hour_cuts: usize,
    started: bool,
    ended: bool,
    start_ms: i64,
    end_ms: i64,
    len_ms: i64,
    // context
    level: i64,
    level_start: i64,
    zone: String,
    zone_id: Option<i64>,
    inst_id: Option<i64>,
    inst_type: String,
    place: char,
    dungeon: bool,
    group_size: Option<i64>,
    method: String,
    threshold: Option<i64>,
    last_lo: Option<LootWindow>,
    span: Option<Span>,
    last_level_t: i64,
    tp_total: Option<i64>,
    tp_level: Option<i64>,
    /// The first and the last `TP` read of the session: when (ms) and the total (s).
    tp_first: Option<(i64, i64)>,
    tp_last: Option<(i64, i64)>,
    // money
    balance: Option<i64>,
    money_start: Option<i64>,
    // signals
    s_fields: [String; 5],
    xp_bar: (Option<i64>, Option<i64>),
    gr: Option<(String, String)>,
    war: Option<String>,
    xp_off: Option<String>,
    game_mode: Option<i64>,
    mode_record: Option<i64>,
    server_id: Option<i64>,
    cd: Option<(String, String, String)>,
    signal_changed: bool,
    // counters
    kills: i64,
    deaths: i64,
    quests: i64,
    earned: i64,
    spent: i64,
    xp_total: i64,
    level_xp: BTreeMap<i64, i64>,
    levels_gained: i64,
    level_skip: bool,
    zones_seen: BTreeSet<String>,
    // collected for the end
    death_list: Vec<Death>,
    points: Vec<PendingPoint>,
    flight: Option<PendingFlight>,
    runs: Vec<Run>,
    run_inside: Option<usize>,
    run_last: Option<i64>,
    run_left: Option<i64>,
    run_count: i64,
    bosses: Bosses,
    zone_rows: BTreeMap<(i64, String), ZoneInfo>,
    names: BTreeMap<(u8, u64), String>,
    items: BTreeMap<u64, ItemAcc>,
    pn: BTreeMap<String, String>,
    talents: Talents,
    camp: Camp,
    class: Class,
    prof: Professions,
    social: Social,
    group: GroupBook,
    peers: Peers,
    baselines: Baselines,
    reference: Reference,
}

pub(crate) fn prefix(
    w: &mut Rows,
    table: &str,
    session: i64,
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
) {
    w.begin(table);
    w.int("session", session).int("at", at);
    w.opt("level", level).opt("zone_id", zone_id);
}

impl Dec<'_> {
    fn lvl(&self) -> Option<i64> {
        (self.level > 0).then_some(self.level)
    }

    fn now(&self, at: i64) -> Now {
        Now {
            at,
            start: self.start_ms,
            len: self.len_ms,
            level: self.lvl(),
            zone_id: self.zone_id,
            place: self.place,
        }
    }

    fn fact(&mut self, table: &str, at: i64) {
        let (s, l, z) = (self.session, self.lvl(), self.zone_id);
        prefix(&mut self.w, table, s, at, l, z);
    }

    fn seen(&self, at: i64) -> Seen {
        Seen {
            at,
            level: self.lvl(),
            zone_id: self.zone_id,
            run: self.run_inside,
        }
    }

    fn flush_points_covered_by(&mut self, stretch: Option<(&str, i64, i64)>) {
        let s = self.session;
        for p in std::mem::take(&mut self.points) {
            prefix(&mut self.w, "path_points", s, p.at, p.level, p.zone_id);
            self.w
                .opt("map", p.map)
                .opt("x", p.x)
                .opt("y", p.y)
                .opt("facing", p.facing);
            match stretch.filter(|(_, from, until)| p.at >= *from && p.at <= *until) {
                Some((mode, _, _)) if !mode.is_empty() => self.w.str("mode", mode),
                _ => self.w.null("mode"),
            };
            self.w.end();
        }
    }

    fn baseline_ctx(&self, at: i64) -> BaselineCtx {
        BaselineCtx {
            session: self.session,
            at,
            level: self.lvl(),
            zone_id: self.zone_id,
        }
    }

    fn rule(&mut self) {
        let (m, t, g) = (self.method.clone(), self.threshold, self.group_size);
        self.w
            .str("loot_rule", &m)
            .opt("threshold", t)
            .opt("group_size", g);
    }

    fn ctx(&self, at: i64) -> Ctx {
        Ctx {
            session: self.session,
            at,
            level: self.lvl(),
            zone_id: self.zone_id,
        }
    }

    fn on_group_line(&mut self, code: &str, t: i64, f: &[&str]) {
        let ctx = self.ctx(t);
        match code {
            "GP" => self.group.on_gp(&ctx, &mut self.w, f),
            "GM" => self.group.on_gm(&ctx, &mut self.w, f),
            "GL" => self.group.on_gl(&ctx, &mut self.w, f),
            _ => {
                let rule = Rule {
                    method: self.method.clone(),
                    threshold: self.threshold,
                    size: self.group_size,
                };
                self.group.on_loot(code, &ctx, &rule, f);
            }
        }
    }

    fn span_key(&self) -> SpanKey {
        SpanKey {
            level: self.lvl(),
            zone: self.zone.clone(),
            zone_id: self.zone_id,
            inst_type: self.inst_type.clone(),
            inst_id: self.inst_id,
        }
    }

    /// Close the current stretch at `now` and open the next one when the state
    /// it is charged to changed.
    fn charge(&mut self, now: i64, force: bool) {
        let key = self.span_key();
        let Some(span) = self.span.as_mut() else {
            self.span = Some(Span {
                key,
                start: now,
                afk: Vec::new(),
                afk_since: None,
            });
            return;
        };
        if !force && span.key == key {
            return;
        }
        if let Some(since) = span.afk_since {
            span.afk.push((since, now));
            span.afk_since = Some(now);
        }
        let done = std::mem::replace(
            span,
            Span {
                key: key.clone(),
                start: now,
                afk: Vec::new(),
                afk_since: span.afk_since,
            },
        );
        self.write_spans(&done, now);
    }

    fn write_spans(&mut self, done: &Span, end: i64) {
        let mut a = done.start;
        while a < end {
            let next = (a.div_euclid(HOUR_MS) + 1)
                .checked_mul(HOUR_MS)
                .unwrap_or(i64::MAX);
            let b = if next < end && self.hour_cuts < MAX_HOUR_CUTS {
                self.hour_cuts += 1;
                next
            } else {
                end
            };
            let afk: i64 = done
                .afk
                .iter()
                .map(|&(since, until)| (until.min(b) - since.max(a)).max(0))
                .sum();
            let s = self.session;
            prefix(
                &mut self.w,
                "play_spans",
                s,
                a,
                done.key.level,
                done.key.zone_id,
            );
            self.w
                .int("end", b)
                .int("ms", b - a)
                .opt("afk_ms", self.has.xp.then_some(afk))
                .text("zone", &done.key.zone)
                .text("instance_type", &done.key.inst_type)
                .opt("instance_id", done.key.inst_id);
            self.w.end();
            a = b;
        }
    }

    fn line(&mut self, l: &DLine) {
        let f: Vec<&str> = if l.rest.is_empty() {
            Vec::new()
        } else {
            l.rest.split('\t').collect()
        };
        let (t, code) = (l.t, l.code);
        if code == "pn" {
            if let (Some(p), Some(n)) = (f.first(), f.get(1)) {
                self.pn.insert(p.to_string(), n.to_string());
            }
            return;
        }
        if self.started && code != "TP" && code != "DP" {
            self.end_ms = self.end_ms.max(t);
        }
        if let Some(kind) = NameRefs::get().def_kind(code) {
            self.define(code, kind, t, &f);
            return;
        }
        // A login's first lines come before its `S` line. Only the lines that
        // set the ruleset and the group context are read from them; nothing is counted.
        if !self.started
            && code != "S"
            && !matches!(
                code,
                "GR" | "PB" | "XD" | "GA" | "RU" | "CD" | "GP" | "GM" | "GL" | "LM"
            )
        {
            return;
        }
        if self.started {
            let now = self.now(t);
            self.class.on_line(&now, code, &f);
        }
        let here = Here {
            level: self.lvl(),
            zone_id: self.zone_id,
        };
        let crafted = self.prof.on_line(t, code, &f, here);
        match code {
            "S" => self.on_s(t, &f),
            "ES" => {
                self.ended = true;
                self.charge(self.end_ms, true);
            }
            "L" => self.on_level(t, &f),
            "Z" => self.on_zone(t, &f),
            "AFK" => {
                self.camp.on_afk(t, field(&f, 0) == "1");
                if let Some(span) = self.span.as_mut() {
                    if field(&f, 0) == "1" {
                        span.afk_since.get_or_insert(t);
                    } else if let Some(since) = span.afk_since.take() {
                        span.afk.push((since, t));
                    }
                }
            }
            "X" => self.on_xp(t, &f),
            "K" => {
                self.kills += 1;
                self.fact("kills", t);
                let npc = leading_id(field(&f, 0)).map(|n| n as i64);
                let ruled = self.has.loot;
                self.w
                    .opt("npc_id", npc)
                    .bool("player", field(&f, 0) == "p")
                    .opt("by", num(&f, 1))
                    .text("loot_rule", if ruled { &self.method } else { "" })
                    .opt("group_size", self.group_size.filter(|_| ruled))
                    .opt("group_at", self.group.at())
                    .text("corpse_key", corpse_key(&f, 2));
                self.w.end();
            }
            "D" => {
                if self
                    .death_list
                    .last()
                    .is_some_and(|d| t - d.t <= SAME_DEATH_MS)
                {
                    return;
                }
                self.deaths += 1;
                self.camp.on_death(t);
                let d = Death {
                    t,
                    level: self.lvl(),
                    zone_id: self.zone_id,
                    ..Death::default()
                };
                self.death_list.push(d);
                if let Some(r) = self.run_inside.and_then(|i| self.runs.get_mut(i)) {
                    r.deaths += 1;
                }
            }
            "DL" => {
                if let Some(d) = self.death_list.last_mut()
                    && d.pos.is_none()
                    && t - d.t <= 60_000
                    && let (Some(m), Some(x), Some(y)) = (num(&f, 0), num(&f, 1), num(&f, 2))
                {
                    d.pos = Some((m, x, y));
                }
            }
            "DR" => {
                if let Some(d) = self.death_list.last_mut()
                    && t - d.t <= 60_000
                    && let Some(hit) = Hit::parse(&f)
                {
                    if hit.pos == 1 && d.killer.is_none() {
                        let g = |i: usize| field(&f, i).to_string();
                        d.killer = Some([g(2), g(10), g(11), g(12), g(13), g(14)]);
                        d.spell = num(&f, 0);
                        d.damage = num(&f, 1);
                        d.overkill = num(&f, 5);
                        d.hits = num(&f, 15);
                        d.event = hit.event.clone();
                        d.environmental_type = hit.environmental_type.clone();
                        d.npc_id = num(&f, 22);
                    }
                    d.recap.entry(hit.pos).or_insert(hit);
                }
            }
            "Q" => {
                let event = match field(&f, 0) {
                    "1" => "accepted",
                    "2" => "turned_in",
                    "3" => "abandoned",
                    _ => return,
                };
                if event == "turned_in" {
                    self.quests += 1;
                }
                self.fact("quest_events", t);
                self.w.opt("quest_id", num(&f, 1)).str("event", event);
                self.w.opt("xp", num(&f, 2)).opt("copper", num(&f, 3));
                self.w.end();
            }
            "M" => self.on_money(t, &f),
            "ST" => {
                if let Some(c) = num(&f, 1).map(|c| c.clamp(-COPPER_MAX, COPPER_MAX)) {
                    self.balance = Some(c);
                    self.money_start.get_or_insert(c);
                }
            }
            "GP" => {
                self.group_size = if field(&f, 0) == "1" {
                    num(&f, 1)
                } else {
                    num(&f, 1).or(Some(1))
                };
                self.on_group_line(code, t, &f);
            }
            "GM" | "GL" | "LT" | "LD" | "RW" | "RL" => self.on_group_line(code, t, &f),
            "LM" => {
                self.method = field(&f, 0).to_string();
                self.threshold = num(&f, 1);
                if let Some(s) = num(&f, 3) {
                    self.group_size = Some(s);
                }
            }
            "LO" => self.on_loot_window(t, &f),
            "LW" => self.on_peer_window(t, &f),
            "O" => {
                let src = self.last_lo.clone().filter(|w| t - w.at <= 5_000);
                self.fact("loot_drops", t);
                self.w.str("via", "receipt");
                match &src {
                    Some(w) => {
                        self.w.str("source_kind", &w.kind).opt("source_id", w.id);
                        match w.first_read {
                            Some(b) => self.w.bool("first_read", b),
                            None => self.w.null("first_read"),
                        };
                    }
                    None => {
                        self.w
                            .null("source_kind")
                            .null("source_id")
                            .null("first_read");
                    }
                }
                self.w
                    .opt("item_id", num(&f, 0))
                    .int("quantity", num(&f, 1).unwrap_or(1));
                self.rule();
                let key = src.as_ref().map_or("", |w| w.key.as_str());
                self.w
                    .text(
                        "read",
                        src.as_ref().and_then(|w| w.read).unwrap_or_default(),
                    )
                    .text("corpse_key", key);
                self.w.end();
            }
            "SK" => {
                self.fact("skill_ups", t);
                self.w
                    .str("kind", "SK")
                    .text("skill", field(&f, 0))
                    .null("skill_id")
                    .opt("rank", num(&f, 1))
                    .null("max")
                    .null("baseline");
                self.w.end();
            }
            "SL" => {
                self.fact("skill_ups", t);
                self.w
                    .str("kind", "SL")
                    .null("skill")
                    .opt("skill_id", num(&f, 0))
                    .opt("rank", num(&f, 1))
                    .opt("max", num(&f, 2))
                    .bool("baseline", field(&f, 4) == "s");
                self.w.end();
                let (ctx, skinning) = (self.ctx(t), self.prof.has_skinning());
                self.group
                    .on_skill(&ctx, &mut self.w, skinning, field(&f, 4) == "s");
            }
            "A" => {
                let (level, zone_id) = (self.lvl(), self.zone_id);
                self.camp.on_aura(t, &f, level, zone_id);
            }
            "C" => self.camp.on_cast(t, &f),
            "QB" => {
                let ctx = self.baseline_ctx(t);
                self.baselines.on_qb(ctx, &mut self.w, &f);
            }
            "KT" => {
                let ctx = self.baseline_ctx(t);
                self.baselines.on_kt(ctx, &mut self.w, &f);
            }
            "EB" => {
                let ctx = self.baseline_ctx(t);
                self.baselines.on_eb(ctx, &mut self.w, &f);
            }
            "IS" => {
                let ctx = self.baseline_ctx(t);
                self.baselines.on_is(ctx, &mut self.w, &f);
            }
            "CR" if crafted => self.camp.on_craft(t),
            "P" => {
                self.camp.on_pos(&f);
                self.points.push(PendingPoint {
                    at: t,
                    level: self.lvl(),
                    zone_id: self.zone_id,
                    map: num(&f, 0),
                    x: num(&f, 1),
                    y: num(&f, 2),
                    facing: num(&f, 3),
                });
            }
            "W" => {
                let mode = field(&f, 0).to_string();
                match field(&f, 2).trim().parse::<f64>() {
                    Ok(s) => {
                        let ms = (s * 1000.0).round() as i64;
                        self.flush_points_covered_by(Some((&mode, t - ms - 1000, t)));
                    }
                    Err(_) => self.flush_points_covered_by(None),
                }
                self.fact("movement_stretches", t);
                self.w
                    .text("mode", field(&f, 0))
                    .opt("yards", num(&f, 1))
                    .opt("seconds", num(&f, 2))
                    .opt("jumps", num(&f, 3));
                self.w.end();
            }
            "T" => match field(&f, 0) {
                "1" => {
                    self.flight = Some(PendingFlight {
                        at: t,
                        level: self.lvl(),
                        zone_id: self.zone_id,
                        map: num(&f, 1),
                        x: num(&f, 2),
                        y: num(&f, 3),
                    });
                }
                "0" => {
                    if let Some(fl) = self.flight.take() {
                        let s = self.session;
                        prefix(&mut self.w, "flights", s, fl.at, fl.level, fl.zone_id);
                        self.w
                            .opt("map", fl.map)
                            .opt("x", fl.x)
                            .opt("y", fl.y)
                            .opt("to_map", num(&f, 1))
                            .opt("to_x", num(&f, 2))
                            .opt("to_y", num(&f, 3))
                            .int("ms", t - fl.at);
                        self.w.end();
                    }
                }
                _ => {}
            },
            "EM" => {
                self.fact("emotes", t);
                self.w.text("token", field(&f, 0));
                self.w.end();
            }
            "RQ" => self.reference.on_rq(t, &f),
            "EL" => {
                let ctx = Ctx {
                    session: self.session,
                    at: t,
                    level: self.lvl(),
                    zone_id: self.zone_id,
                };
                Reference::on_el(&ctx, &mut self.w, &f);
            }
            "HD" | "WH" | "IG" | "BM" | "BO" => {
                let ctx = Ctx {
                    session: self.session,
                    at: t,
                    level: self.lvl(),
                    zone_id: self.zone_id,
                };
                match code {
                    "HD" => Social::on_hd(&ctx, &mut self.w, &f),
                    "WH" => Social::on_wh(&ctx, &mut self.w, &f),
                    "IG" => Social::on_ig(&ctx, &mut self.w, &f),
                    "BM" => self.social.on_bm(&ctx, &mut self.w, &f),
                    _ => self.social.on_bo(&ctx, &mut self.w, &f),
                }
            }
            "AQ" => {
                let server_id = self.server_id;
                self.fact("auction_prices", t);
                self.w
                    .text("kind", field(&f, 0))
                    .opt("item_id", num(&f, 1))
                    .opt("price", num(&f, 2))
                    .opt("quantity", num(&f, 3))
                    .opt("listings", num(&f, 4))
                    .opt("item_level", num(&f, 5))
                    .opt("suffix", num(&f, 6))
                    .opt("realm_id", server_id);
                self.w.end();
            }
            "NS" => {
                self.fact("creature_sightings", t);
                self.w
                    .opt("npc_id", num(&f, 0))
                    .opt("npc_level", num(&f, 1))
                    .text("classification", field(&f, 2))
                    .text("creature_type", field(&f, 3))
                    .text("family", field(&f, 5))
                    .opt("reaction", num(&f, 7))
                    .opt("map", num(&f, 8))
                    .opt("cell_x", num(&f, 9))
                    .opt("cell_y", num(&f, 10));
                self.w.end();
            }
            "B" => {
                let seen = self.seen(t);
                self.bosses.on_b(seen, &f);
            }
            "E" => {
                let seen = self.seen(t);
                self.bosses.on_e(seen, &f);
            }
            "TA" | "TG" | "TQ" | "TV" | "TD" => self.on_talent(code, t, &f),
            "TP" => {
                self.tp_total = num(&f, 0);
                self.tp_level = num(&f, 1);
                if let Some(total) = self.tp_total {
                    self.tp_first.get_or_insert((t, total));
                    self.tp_last = Some((t, total));
                }
            }
            "GR" => {
                let v = (field(&f, 0).to_string(), field(&f, 1).to_string());
                match &self.gr {
                    None => self.gr = Some(v),
                    Some(old) => self.signal_changed |= *old != v,
                }
            }
            "PB" => {
                let v = match field(&f, 2) {
                    "1" => "on",
                    "0" => "off",
                    _ => "unknown",
                };
                match &self.war {
                    None => self.war = Some(v.to_string()),
                    Some(old) => self.signal_changed |= old != v && v != "unknown",
                }
            }
            "XD" => {
                let v = field(&f, 0).to_string();
                match &self.xp_off {
                    None => self.xp_off = Some(v),
                    Some(old) => self.signal_changed |= *old != v,
                }
            }
            "GA" => match (&self.game_mode, num(&f, 0)) {
                (None, v) => self.game_mode = v,
                (Some(old), Some(v)) => self.signal_changed |= *old != v,
                _ => {}
            },
            "RU" => {
                match (&self.mode_record, num(&f, 0)) {
                    (None, v) => self.mode_record = v,
                    (Some(old), Some(v)) => self.signal_changed |= *old != v,
                    _ => {}
                }
                if self.server_id.is_none() {
                    self.server_id = num(&f, 8);
                }
            }
            "CD" if self.cd.is_none() => {
                self.cd = Some((
                    field(&f, 0).to_string(),
                    field(&f, 1).to_string(),
                    field(&f, 2).to_string(),
                ));
            }
            _ => {}
        }
    }

    fn on_talent(&mut self, code: &str, t: i64, f: &[&str]) {
        let ctx = Ctx {
            session: self.session,
            at: t,
            level: self.lvl(),
            zone_id: self.zone_id,
        };
        match code {
            "TA" => self.talents.on_ta(&ctx, &mut self.w, f),
            "TG" => self.talents.on_tg(&ctx, &mut self.w, f),
            "TQ" => self.talents.on_tq(&ctx, f),
            "TV" => self.talents.on_tv(&ctx, &mut self.w, f),
            _ => self.talents.on_td(f),
        }
    }

    fn define(&mut self, code: &str, kind: u8, t: i64, f: &[&str]) {
        let Some(id) = leading_id(field(f, 0)) else {
            return;
        };
        match code {
            "II" => {
                let acc = self.items.entry(id).or_default();
                acc.info = f.iter().skip(1).map(|s| s.to_string()).collect();
            }
            "IQ" => self.items.entry(id).or_default().quality = num(f, 1),
            "IT" => self.items.entry(id).or_default().tooltip = field(f, 1).to_string(),
            "SI" => self.reference.on_si(t, id, f),
            "SD" => self.reference.on_sd(t, id, f),
            _ => {
                self.names
                    .entry((kind, id))
                    .or_insert_with(|| field(f, 1).to_string());
            }
        }
    }

    fn on_s(&mut self, t: i64, f: &[&str]) {
        if self.started {
            return;
        }
        self.started = true;
        self.talents.set_build(field(f, 0));
        self.group.on_started(t);
        self.start_ms = t;
        self.end_ms = self.end_ms.max(t);
        for (slot, i) in self.s_fields.iter_mut().zip([0, 1, 2, 4, 5]) {
            *slot = field(f, i).to_string();
        }
        self.level = level(f, 3).unwrap_or(0);
        self.level_start = self.level;
        self.last_level_t = t;
        self.xp_bar = (num(f, 10), num(f, 11));
        let money = num(f, 12).map(|m| m.clamp(-COPPER_MAX, COPPER_MAX));
        self.balance = money;
        self.money_start = money;
        if money.is_some() && self.has.economy {
            self.fact("money_changes", t);
            self.w
                .str("src", "S")
                .int("copper", 0)
                .int("transfer", 0)
                .int("earned", 0)
                .int("spent", 0)
                .opt("balance", money);
            self.w.end();
        }
        self.charge(t, true);
    }

    fn on_level(&mut self, t: i64, f: &[&str]) {
        let Some(l) = level(f, 0) else {
            return;
        };
        self.charge(t, false);
        let since = t - self.last_level_t;
        // An `L` line that repeats the level (an import writes one at its start, the client may after a
        // reload) gains nothing, so it must not count toward the "levels too fast" flag.
        let ding = self.level > 0 && l > self.level;
        if ding {
            self.levels_gained += l - self.level;
            if l > self.level + 1 {
                self.level_skip = true;
            }
        }
        self.level = l;
        self.last_level_t = t;
        self.charge(t, false);
        self.fact("level_ups", t);
        let tpl = self.tp_level;
        self.w
            .int("reached", l)
            .int("since_ms", since)
            .opt("tp_level_s", tpl)
            .bool("ding", ding);
        self.w.end();
    }

    fn on_zone(&mut self, t: i64, f: &[&str]) {
        let name = field(f, 4);
        let party = matches!(field(f, 2), "party" | "raid");
        let map = num(f, 0);
        self.charge(t, false);
        self.dungeon = party;
        self.inst_id = num(f, 1);
        self.inst_type = field(f, 2).to_string();
        self.place = place_of(&self.inst_type);
        // Instances have no uiMapID: their zone id is the instance map id, negated.
        let map = map.or(num(f, 1).map(|i| -i));
        if !name.is_empty() {
            self.zone = name.to_string();
            self.zone_id = map;
            self.zones_seen.insert(name.to_string());
        }
        if let Some(m) = map {
            self.zone_rows
                .entry((m, name.to_string()))
                .or_insert_with(|| {
                    (
                        num(f, 1),
                        field(f, 2).to_string(),
                        num(f, 3),
                        field(f, 5).to_string(),
                    )
                });
        }
        self.charge(t, false);
        // Dungeon runs (docs/OVERVIEW.md).
        if !party {
            if let Some(i) = self.run_inside.take() {
                self.leave_run(i, t);
            }
            return;
        }
        let Some(id) = num(f, 1) else { return };
        if self.run_inside.is_some_and(|i| self.runs[i].inst == id) {
            return;
        }
        if let Some(i) = self.run_inside.take() {
            // A direct move to another dungeon: the old stay ends, no leave time.
            self.runs[i].inside_ms += t - self.runs[i].stay;
        }
        let same =
            self.run_last == Some(id) && self.run_left.is_some_and(|l| t - l < RUN_REJOIN_MS);
        self.run_left = None;
        self.run_last = Some(id);
        if same && let Some(i) = self.runs.iter().rposition(|r| r.inst == id) {
            self.runs[i].entries += 1;
            self.runs[i].left = None;
            self.runs[i].stay = t;
            self.run_inside = Some(i);
            return;
        }
        let run = Run {
            inst: id,
            name: name.to_string(),
            inst_type: field(f, 2).to_string(),
            difficulty: num(f, 3),
            entered: t,
            level: self.lvl(),
            zone_id: self.zone_id,
            left: None,
            inside_ms: 0,
            entries: 1,
            bosses: BTreeSet::new(),
            deaths: 0,
            group_size: self.group_size,
            stay: t,
            at_end: false,
        };
        self.runs.push(run);
        let i = self.runs.len() - 1;
        self.run_inside = Some(i);
    }

    fn leave_run(&mut self, i: usize, t: i64) {
        self.runs[i].inside_ms += t - self.runs[i].stay;
        self.runs[i].left = Some(t);
        self.run_left = Some(t);
    }

    fn on_xp(&mut self, t: i64, f: &[&str]) {
        let amount = num(f, 0).unwrap_or(0);
        let source = match field(f, 3) {
            "k" if self.dungeon => "d",
            "k" => "k",
            "q" => "q",
            "e" => "e",
            _ => "o",
        };
        self.camp.on_xp(t, amount);
        self.xp_total += amount;
        *self.level_xp.entry(self.level).or_default() += amount;
        self.fact("xp_gains", t);
        let id = matches!(field(f, 3), "k" | "q")
            .then(|| leading_id(field(f, 4)).map(|n| n as i64))
            .flatten();
        self.w
            .int("amount", amount)
            .str("source", source)
            .opt("source_id", id)
            .opt("xp", num(f, 1))
            .opt("xp_max", num(f, 2));
        self.w.end();
        if let (Some(x), Some(m)) = (num(f, 1), num(f, 2)) {
            self.xp_bar = (Some(x), Some(m));
        }
    }

    fn on_money(&mut self, t: i64, f: &[&str]) {
        let copper = num(f, 0).unwrap_or(0).clamp(-COPPER_MAX, COPPER_MAX);
        let transfer = num(f, 1).unwrap_or(0).clamp(-COPPER_MAX, COPPER_MAX);
        let net = copper - transfer;
        let (earned, spent) = if net > 0 { (net, 0) } else { (0, -net) };
        self.earned = self.earned.saturating_add(earned);
        self.spent = self.spent.saturating_add(spent);
        match num(f, 2).map(|b| b.clamp(-COPPER_MAX, COPPER_MAX)) {
            Some(b) => self.balance = Some(b),
            None => self.balance = self.balance.map(|c| c.saturating_add(copper)),
        }
        self.fact("money_changes", t);
        let b = self.balance;
        self.w
            .str("src", "M")
            .int("copper", copper)
            .int("transfer", transfer)
            .int("earned", earned)
            .int("spent", spent)
            .opt("balance", b);
        self.w.end();
    }

    fn on_loot_window(&mut self, t: i64, f: &[&str]) {
        let kind = field(f, 0).to_string();
        let id = leading_id(field(f, 1)).map(|n| n as i64);
        let first = match field(f, 4) {
            "0" => Some(true),
            "1" => Some(false),
            _ => None,
        };
        let read = self.loot_read(&kind);
        let key = corpse_key(f, 5);
        self.last_lo = Some(LootWindow {
            at: t,
            kind: kind.clone(),
            id,
            first_read: first,
            read,
            key: key.to_string(),
        });
        let list = field(f, 3);
        let items = window_items(list);
        let [map, x, y] = self.prof.pos().unwrap_or([None; 3]);
        self.fact("loot_opens", t);
        self.w.str("source_kind", &kind).opt("source_id", id);
        match first {
            Some(b) => self.w.bool("first_read", b),
            None => self.w.null("first_read"),
        };
        self.w
            .opt("slots", num(f, 2))
            .bool("has_items", !list.is_empty());
        self.rule();
        self.w
            .text("read", read.unwrap_or_default())
            .opt(
                "item_rows",
                (!list.is_empty()).then_some(items.len() as i64),
            )
            .opt("map", map)
            .opt("x", x)
            .opt("y", y)
            .opt("group_at", self.group.at())
            .text("corpse_key", key);
        self.w.end();
        for (item, qty) in items {
            self.fact("loot_drops", t);
            self.w
                .str("via", "window")
                .str("source_kind", &kind)
                .opt("source_id", id);
            match first {
                Some(b) => self.w.bool("first_read", b),
                None => self.w.null("first_read"),
            };
            self.w.int("item_id", item).int("quantity", qty);
            self.rule();
            self.w
                .text("read", read.unwrap_or_default())
                .text("corpse_key", key);
            self.w.end();
        }
    }

    fn on_peer_window(&mut self, t: i64, f: &[&str]) {
        let ctx = self.ctx(t);
        let rule = Rule {
            method: self.method.clone(),
            threshold: self.threshold,
            size: self.group_size,
        };
        let group_at = self.group.at();
        self.peers.on_lw(&ctx, &mut self.w, &rule, group_at, f);
    }

    fn loot_read(&self, kind: &str) -> Option<&'static str> {
        if !self.has.combat {
            return None;
        }
        Some(match kind {
            "c" | "g" => self.prof.gathered().unwrap_or("loot"),
            "i" => "bag",
            _ => "other",
        })
    }
}

pub(crate) fn window_items(list: &str) -> Vec<(i64, i64)> {
    let mut items: Vec<(i64, i64)> = Vec::new();
    for (item, qty) in list.split(',').filter_map(item_of) {
        match items.iter_mut().find(|(i, _)| *i == item) {
            Some((_, total)) => *total += qty,
            None => items.push((item, qty)),
        }
    }
    items
}

impl Dec<'_> {
    fn finish(mut self, lines: usize, groups: &[&str], backsteps: usize) -> (usize, i64) {
        self.charge(self.end_ms, true);
        if let Some(i) = self.run_inside.take() {
            let end = self.end_ms;
            self.runs[i].at_end = true;
            self.leave_run(i, end);
        }
        let s = self.session;
        self.talents.finish(s, &mut self.w);
        self.group
            .finish(s, self.end_ms, &self.pn, &self.peers, &mut self.w);
        let end = self.now(self.end_ms);
        self.class.finish(s, &end, self.has.combat, &mut self.w);
        let known = Known {
            afk: self.has.xp,
            crafts: self.has.professions,
            xp: self.has.xp,
        };
        std::mem::take(&mut self.camp).finish(s, self.end_ms, known, &mut self.w);
        self.baselines.finish(&mut self.w);
        let present = Present {
            professions: self.has.professions,
            loot: self.has.loot,
        };
        std::mem::take(&mut self.prof).finish(s, present, &mut self.w);
        for d in std::mem::take(&mut self.death_list) {
            prefix(&mut self.w, "deaths", s, d.t, d.level, d.zone_id);
            let (m, x, y) = d
                .pos
                .map_or((None, None, None), |(m, x, y)| (Some(m), Some(x), Some(y)));
            self.w.opt("map_id", m).opt("x", x).opt("y", y);
            match &d.killer {
                Some(k) => {
                    self.w
                        .text("killer_kind", &k[1])
                        .text("killer", &k[0])
                        .opt("killer_level", num(&[k[2].as_str()], 0))
                        .text("killer_class", &k[3])
                        .text("killer_race", &k[4])
                        .text("killer_guild", &k[5]);
                }
                None => {
                    self.w
                        .null("killer_kind")
                        .null("killer")
                        .null("killer_level")
                        .null("killer_class")
                        .null("killer_race")
                        .null("killer_guild");
                }
            }
            self.w
                .opt("spell_id", d.spell)
                .opt("damage", d.damage)
                .opt("overkill", d.overkill)
                .opt("hits", d.hits)
                .text("event", &d.event)
                .text("environmental_type", &d.environmental_type)
                .opt("killer_npc_id", d.npc_id);
            self.w.end();
            for hit in d.recap.values() {
                hit.write(&mut self.w, s, d.t, d.level, d.zone_id);
            }
        }
        let kills = std::mem::take(&mut self.bosses).finish();
        for k in &kills {
            if let Some(r) = k.run().and_then(|i| self.runs.get_mut(i)) {
                r.bosses.insert(k.boss());
            }
        }
        let runs = std::mem::take(&mut self.runs);
        // Without combat parts the bosses and deaths of a run are unknown: no rows.
        let runs = if self.has.combat { runs } else { Vec::new() };
        let end = self.end_ms;
        self.run_count = runs.len() as i64;
        for (i, r) in runs.iter().enumerate() {
            prefix(
                &mut self.w,
                "dungeon_runs",
                s,
                r.entered,
                r.level,
                r.zone_id,
            );
            self.w
                .int("index", i as i64)
                .int("instance_id", r.inst)
                .text("name", &r.name)
                .text("instance_type", &r.inst_type)
                .opt("difficulty_id", r.difficulty)
                .opt("left_at", r.left)
                .bool("left_by_session_end", r.left == Some(end) && r.at_end)
                .int("inside_ms", r.inside_ms)
                .int("entries", r.entries)
                .int("bosses", r.bosses.len() as i64)
                .int("deaths", r.deaths)
                .opt("group_size", r.group_size);
            self.w.end();
        }
        if self.has.combat {
            for k in &kills {
                k.write(s, k.run().map(|i| runs[i].inst), &mut self.w);
            }
        }
        let zones = std::mem::take(&mut self.zone_rows);
        let start = self.start_ms;
        for ((id, name), (inst, itype, diff, sub)) in zones {
            prefix(&mut self.w, "zones", s, start, None, None);
            self.w
                .int("id", id)
                .text("name", &name)
                .text("subzone", &sub)
                .opt("instance_id", inst)
                .text("instance_type", &itype)
                .opt("difficulty_id", diff);
            self.w.end();
        }
        let kinds = &NameRefs::get().kinds;
        for ((kind, id), name) in std::mem::take(&mut self.names) {
            self.w.begin("names");
            self.w
                .int("session", s)
                .str("kind", &kinds[kind as usize])
                .int("id", id as i64)
                .str("name", &name);
            self.w.end();
        }
        for (id, it) in std::mem::take(&mut self.items) {
            self.w.begin("items");
            self.w.int("session", s).int("item_id", id as i64);
            let g = |i: usize| it.info.get(i).map(String::as_str).unwrap_or("");
            let n = |i: usize| num(&[g(i)], 0);
            self.w
                .opt("class_id", n(0))
                .opt("subclass_id", n(1))
                .text("equip_loc", g(2))
                .opt("item_level", n(3))
                .opt("required_level", n(4))
                .opt("sell_price", n(5))
                .opt("bind_type", n(6))
                .opt("stack", n(7))
                .opt("quality", it.quality)
                .text("tooltip", &it.tooltip);
            self.w.end();
        }
        self.reference.finish(&mut self.w, s);
        for (p, name) in std::mem::take(&mut self.pn) {
            self.w.begin("pn");
            self.w
                .int("session", s)
                .str("pseudonym", &p)
                .str("name", &name);
            self.w.end();
        }
        self.flush_points_covered_by(None);
        self.session_row(lines, groups, backsteps);
        (self.w.count, self.end_ms.max(self.start_ms) - self.start_ms)
    }

    fn session_row(&mut self, lines: usize, groups: &[&str], backsteps: usize) {
        let s = self.session;
        let start = self.start_ms;
        let end = self.end_ms.max(start);
        let played_ms = end - start;
        let mut flags: Vec<&str> = Vec::new();
        if backsteps > 0 {
            flags.push("time_order");
        }
        if played_ms > 86_400_000 {
            flags.push("session_over_24h");
        }
        if self
            .level_xp
            .iter()
            .any(|(l, x)| (1..60).contains(l) && *x > NEED[(*l - 1) as usize] * 3 / 2)
        {
            flags.push("xp_over_level");
        }
        if self.level_skip {
            flags.push("level_skip");
        }
        if self.levels_gained > 0 && played_ms / self.levels_gained < 20_000 {
            flags.push("levels_too_fast");
        }
        if self.signal_changed {
            flags.push("ruleset_changed");
        }
        let flag = |v: Option<&str>| match v {
            Some("1") => Some(true),
            Some("0") => Some(false),
            _ => None,
        };
        let hardcore = flag(self.gr.as_ref().map(|g| g.1.as_str()));
        let xp_off = flag(self.xp_off.as_deref());
        let preset = self.gr.as_ref().and_then(|g| num(&[g.0.as_str()], 0));
        let war = self.war.clone().unwrap_or_else(|| "unknown".into());
        let sf = self.s_fields.clone();
        let cd = self.cd.clone().unwrap_or_default();
        let level_end = self.lvl();
        // The client's played counter (ms) at the start and the end of the session, from the reads in it.
        let counter_start = self
            .tp_first
            .map(|(at, total)| total * 1000 - (at - start).max(0));
        let counter_end = self
            .tp_last
            .map(|(at, total)| total * 1000 + (end - at).max(0));
        let xp_bar = self.xp_bar;
        let l0 = self.level_start;
        self.w.begin("sessions");
        self.w
            .int("session", s)
            .int("start", start)
            .int("end", end)
            .bool("has_start", self.started)
            .bool("ended", self.ended)
            .int("played_ms", played_ms)
            .int("played_s", round_s(played_ms))
            .int("lines", lines as i64)
            .int("decoder_version", i64::from(DECODER_VERSION))
            .strs("groups", groups)
            .text("build", &sf[0])
            .text("class", &sf[1])
            .text("race", &sf[2])
            .text("faction", &sf[3])
            .text("addon_version", &sf[4])
            .opt("level_start", (l0 > 0).then_some(l0))
            .opt("level_end", level_end)
            .opt("xp", xp_bar.0)
            .opt("xp_max", xp_bar.1)
            .opt("money_start", self.money_start)
            .opt(
                "money_end",
                self.has.economy.then_some(self.balance).flatten(),
            )
            .opt("kills", self.has.combat.then_some(self.kills))
            .opt("deaths", self.has.combat.then_some(self.deaths))
            .opt("quests_done", self.has.quests.then_some(self.quests))
            .opt("copper_earned", self.has.economy.then_some(self.earned))
            .opt("copper_spent", self.has.economy.then_some(self.spent))
            .opt("xp_total", self.has.xp.then_some(self.xp_total))
            .int("zones", self.zones_seen.len() as i64)
            .opt("dungeon_runs", self.has.combat.then_some(self.run_count))
            .opt("tp_total_s", self.tp_total)
            .opt("tp_level_s", self.tp_level)
            .opt("counter_start_ms", counter_start)
            .opt("counter_end_ms", counter_end)
            .opt("gr_preset", preset);
        match hardcore {
            Some(b) => self.w.bool("gr_hardcore", b),
            None => self.w.null("gr_hardcore"),
        };
        self.w.str("war_mode", &war);
        match xp_off {
            Some(b) => self.w.bool("xp_off", b),
            None => self.w.null("xp_off"),
        };
        self.w
            .opt("game_mode", self.game_mode)
            .opt("mode_record", self.mode_record)
            .opt("server_id", self.server_id)
            .opt("sex", num(&[cd.0.as_str()], 0))
            .opt("class_id", num(&[cd.1.as_str()], 0))
            .opt("race_id", num(&[cd.2.as_str()], 0))
            .strs("flags", &flags);
        self.w.end();
    }
}

/// Decode the parts of one session (any subset of its groups) and append the
/// fact rows as newline-delimited JSON to `out`.
pub fn decode_session(parts: &[&str], out: &mut String) -> Result<Summary, String> {
    let groups = Groups::get();
    let mut lines: Vec<DLine> = Vec::new();
    let mut ident: Option<(&str, i64)> = None;
    let mut keys: BTreeSet<usize> = BTreeSet::new();
    let (mut seq, mut count, mut backsteps) = (0u32, 0usize, 0usize);
    let mut skipped = 0usize;
    for text in parts {
        let (character, session, group) =
            parse_header(text).ok_or_else(|| "a part has no valid header".to_string())?;
        match ident {
            None => ident = Some((character, session)),
            Some(id) if id != (character, session) => {
                return Err("parts of different sessions or characters".into());
            }
            _ => {}
        }
        let gi = groups
            .index(group)
            .ok_or_else(|| format!("unknown group {group}"))?;
        keys.insert(gi);
        let skip = group == "addons"
            || group == crate::imports::GROUP
            || group == crate::fishing::GROUP
            || group == crate::counts::GROUP;
        let mut t = session * 1000;
        let mut after_line = false;
        for line in text.split('\n').skip(1) {
            if line.is_empty() {
                continue;
            }
            count += 1;
            let mut it = line.splitn(3, '\t');
            let dt = it.next().and_then(|d| d.parse::<i64>().ok());
            let code = it.next();
            let (Some(dt), Some(code)) = (dt, code) else {
                skipped += 1;
                continue;
            };
            if skip {
                continue;
            }
            // The first line of a part counts from the session's start, not from a line before it: a line
            // the addon wrote just before the session began (a statistic after the last session's end)
            // has a negative first `dt` and is not a step back.
            if dt < 0 && after_line {
                backsteps += 1;
            }
            after_line = code != "pn";
            let Some(next) = t.checked_add(dt).filter(|t| at_near(session * 1000, *t)) else {
                skipped += 1;
                continue;
            };
            t = next;
            lines.push(DLine {
                t,
                rank: tie_rank(code, gi),
                seq,
                code,
                rest: it.next().unwrap_or(""),
            });
            seq += 1;
        }
    }
    let Some((character, session)) = ident else {
        return Err("no parts".into());
    };
    lines.sort_unstable_by_key(|l| (l.t, l.rank, l.seq));
    let first_t = lines.first().map_or(session * 1000, |l| l.t);
    let began = lines
        .iter()
        .find(|l| l.code == "S")
        .map_or(first_t, |l| l.t);
    let last = lines.iter().find(|l| l.code == "ES").or(lines.last());
    let len_ms = last.map_or(0, |l| (l.t - began).max(0));
    let has_group = |k: &str| groups.index(k).is_some_and(|i| keys.contains(&i));
    let mut d = Dec {
        w: Rows::new(out),
        has: Has {
            xp: has_group("xp"),
            quests: has_group("quests"),
            combat: has_group("combat"),
            economy: has_group("economy"),
            professions: has_group("professions"),
            loot: has_group("loot"),
        },
        session,
        hour_cuts: 0,
        started: false,
        ended: false,
        start_ms: first_t,
        end_ms: first_t,
        len_ms,
        level: 0,
        level_start: 0,
        zone: String::new(),
        zone_id: None,
        inst_id: None,
        inst_type: String::new(),
        place: OPEN_WORLD,
        dungeon: false,
        group_size: None,
        method: "unknown".into(),
        threshold: None,
        last_lo: None,
        span: None,
        last_level_t: first_t,
        tp_total: None,
        tp_level: None,
        tp_first: None,
        tp_last: None,
        balance: None,
        money_start: None,
        s_fields: Default::default(),
        xp_bar: (None, None),
        gr: None,
        war: None,
        xp_off: None,
        game_mode: None,
        mode_record: None,
        server_id: None,
        cd: None,
        signal_changed: false,
        kills: 0,
        deaths: 0,
        quests: 0,
        earned: 0,
        spent: 0,
        xp_total: 0,
        level_xp: BTreeMap::new(),
        levels_gained: 0,
        level_skip: false,
        zones_seen: BTreeSet::new(),
        death_list: Vec::new(),
        points: Vec::new(),
        flight: None,
        runs: Vec::new(),
        run_inside: None,
        run_last: None,
        run_left: None,
        run_count: 0,
        bosses: Bosses::default(),
        zone_rows: BTreeMap::new(),
        names: BTreeMap::new(),
        items: BTreeMap::new(),
        pn: BTreeMap::new(),
        talents: Talents::default(),
        camp: Camp::default(),
        class: Class::default(),
        prof: Professions::default(),
        social: Social::default(),
        group: GroupBook::new(character, has_group("professions")),
        peers: Peers::default(),
        baselines: Baselines::default(),
        reference: Reference::default(),
    };
    for l in &lines {
        d.line(l);
        if d.ended {
            break;
        }
    }
    let names: Vec<&str> = keys.iter().map(|i| groups.key(*i)).collect();
    let (rows, played_ms) = d.finish(count, &names, backsteps);
    Ok(Summary {
        rows,
        lines: count,
        skipped,
        session,
        played_ms,
    })
}
