//! The `item_flows` rows (docs/ROWS.md): what went into and out of the bags, and where it came from, read from the
//! `I` and `DI` lines.

use crate::auras::Recent;
use crate::class::Now;
use crate::decode::{field, num, prefix};
use crate::rows::Rows;
use std::collections::BTreeMap;

const CAST_BLAMED_FOR_BAG_CHANGE_MS: i64 = 1_500;
const DELETE_ABSORBS_BAG_CHANGE_MS: i64 = 5_000;

const OTHER: char = 'o';
const DESTROYED: char = 'x';

#[derive(PartialEq, Eq, PartialOrd, Ord)]
struct Key {
    item_id: i64,
    dir: i64,
    place: char,
    spell_id: Option<i64>,
    level: Option<i64>,
}

struct Row {
    at: i64,
    zone_id: Option<i64>,
    events: i64,
    quantity: i64,
}

#[derive(Default)]
pub struct Flows {
    rows: BTreeMap<Key, Row>,
    deleted: Vec<(i64, i64, i64)>,
}

fn place_of(where_: &str) -> char {
    let mut chars = where_.chars();
    match (chars.next(), chars.next()) {
        (Some(c), None) if c.is_ascii_lowercase() && c != DESTROYED => c,
        _ => OTHER,
    }
}

impl Flows {
    /// An `I` line. `auto` is the spell of the auto repeat that is running, if any.
    pub fn on_item(&mut self, now: &Now, f: &[&str], recent: &Recent, auto: Option<i64>) {
        let (Some(item), Some(delta)) = (num(f, 0), num(f, 1)) else {
            return;
        };
        if item <= 0 || delta == 0 {
            return;
        }
        let place = place_of(field(f, 2));
        if delta < 0 && place == OTHER {
            self.deleted
                .retain(|d| now.at - d.2 <= DELETE_ABSORBS_BAG_CHANGE_MS);
            if let Some(i) = self
                .deleted
                .iter()
                .position(|d| d.0 == item && d.1 == -delta)
            {
                self.deleted.remove(i);
                return;
            }
        }
        let spell = if place == OTHER {
            recent
                .latest(now.at - CAST_BLAMED_FOR_BAG_CHANGE_MS, true)
                .map(|c| c.spell)
                .or(auto)
        } else {
            None
        };
        self.add(now, item, delta.signum(), place, spell, delta.abs());
    }

    /// A `DI` line: the units are counted here, and the `I` line that follows them is not counted again.
    pub fn on_delete(&mut self, now: &Now, f: &[&str]) {
        let Some(item) = num(f, 0).filter(|i| *i > 0) else {
            return;
        };
        let count = num(f, 1).filter(|c| *c > 0).unwrap_or(1);
        self.add(now, item, -1, DESTROYED, None, count);
        self.deleted.push((item, count, now.at));
    }

    fn add(
        &mut self,
        now: &Now,
        item_id: i64,
        dir: i64,
        place: char,
        spell_id: Option<i64>,
        quantity: i64,
    ) {
        let key = Key {
            item_id,
            dir,
            place,
            spell_id,
            level: now.level,
        };
        let row = self.rows.entry(key).or_insert_with(|| Row {
            at: now.at,
            zone_id: now.zone_id,
            events: 0,
            quantity: 0,
        });
        row.events += 1;
        row.quantity += quantity;
    }

    pub fn finish(&self, session: i64, w: &mut Rows) {
        for (key, row) in &self.rows {
            prefix(w, "item_flows", session, row.at, key.level, row.zone_id);
            w.int("item_id", key.item_id)
                .int("dir", key.dir)
                .str("place", &key.place.to_string())
                .opt("spell_id", key.spell_id)
                .int("events", row.events)
                .int("quantity", row.quantity);
            w.end();
        }
    }
}
