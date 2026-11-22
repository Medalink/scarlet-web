//! The hits of a death's recap (docs/ROWS.md, `death_hits`), read from `DR` lines.

use crate::decode::{num, prefix};
use crate::rows::Rows;

pub const MAX_POS: i64 = 20;

pub struct Hit {
    pub pos: i64,
    spell: Option<i64>,
    amount: Option<i64>,
    pub source: String,
    environmental: bool,
    pub environmental_type: String,
    pub event: String,
    overkill: Option<i64>,
    hp: Option<i64>,
    max_hp: Option<i64>,
    before_ms: Option<i64>,
    school: Option<i64>,
    absorbed: Option<i64>,
    resisted: Option<i64>,
    blocked: Option<i64>,
    flags: String,
}

fn known(f: &[&str], i: usize) -> String {
    match f.get(i).map_or("", |v| v.trim()) {
        "?" => String::new(),
        v => v.to_string(),
    }
}

impl Hit {
    /// None when the line has no position (lines written before it existed) or one outside 1 to 20.
    pub fn parse(f: &[&str]) -> Option<Hit> {
        let pos = num(f, 4).filter(|p| (1..=MAX_POS).contains(p))?;
        Some(Hit {
            pos,
            spell: num(f, 0),
            amount: num(f, 1),
            source: known(f, 2),
            environmental: f.get(3).is_some_and(|v| v.trim() == "1"),
            environmental_type: known(f, 17),
            event: known(f, 16),
            overkill: num(f, 5),
            hp: num(f, 6),
            max_hp: num(f, 7),
            before_ms: f
                .get(8)
                .and_then(|v| v.trim().parse::<f64>().ok())
                .filter(|s| s.is_finite())
                .map(|s| (s * 1000.0).round() as i64),
            school: num(f, 9),
            absorbed: num(f, 18),
            resisted: num(f, 19),
            blocked: num(f, 20),
            flags: known(f, 21),
        })
    }

    pub fn write(
        &self,
        w: &mut Rows,
        session: i64,
        at: i64,
        level: Option<i64>,
        zone: Option<i64>,
    ) {
        prefix(w, "death_hits", session, at, level, zone);
        w.int("pos", self.pos)
            .opt("spell_id", self.spell)
            .opt("amount", self.amount)
            .text("source", &self.source)
            .bool("environmental", self.environmental)
            .text("environmental_type", &self.environmental_type)
            .text("event", &self.event)
            .opt("overkill", self.overkill)
            .opt("hp", self.hp)
            .opt("max_hp", self.max_hp)
            .opt("before_ms", self.before_ms)
            .opt("school", self.school)
            .opt("absorbed", self.absorbed)
            .opt("resisted", self.resisted)
            .opt("blocked", self.blocked)
            .text("flags", &self.flags);
        w.end();
    }
}
