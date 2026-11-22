//! The spell casts and damage-meter totals of a session (docs/ROWS.md, `spell_casts` and `meter_totals`),
//! read from the `C` and `DM` lines.

use crate::decode::{field, num, prefix};
use crate::rows::Rows;
use std::collections::BTreeMap;

#[derive(Default)]
struct Cast {
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    casts: i64,
    cast_ms: i64,
}

#[derive(Default)]
struct Meter {
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    total: i64,
    best: i64,
    fights: i64,
    fight_ms: i64,
}

#[derive(Default)]
pub struct Combat {
    casts: BTreeMap<i64, Cast>,
    meters: BTreeMap<char, Meter>,
}

fn seconds_ms_or_zero(f: &[&str], i: usize) -> i64 {
    f.get(i)
        .and_then(|v| v.trim().parse::<f64>().ok())
        .filter(|s| s.is_finite())
        .map_or(0, |s| (s * 1000.0).round() as i64)
}

fn meter_letter(code: &str) -> Option<char> {
    match code {
        "d" | "h" | "t" | "a" | "i" | "x" => code.chars().next(),
        _ => None,
    }
}

impl Combat {
    pub fn on_cast(&mut self, at: i64, level: Option<i64>, zone_id: Option<i64>, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        let cast = self.casts.entry(spell).or_insert_with(|| Cast {
            at,
            level,
            zone_id,
            ..Cast::default()
        });
        cast.casts += 1;
        cast.cast_ms += seconds_ms_or_zero(f, 1);
    }

    /// A line with an unknown meter letter or a total that is not a number writes nothing.
    pub fn on_meter(&mut self, at: i64, level: Option<i64>, zone_id: Option<i64>, f: &[&str]) {
        let (Some(meter), Some(total)) = (meter_letter(field(f, 0)), num(f, 1)) else {
            return;
        };
        let meter = self.meters.entry(meter).or_insert_with(|| Meter {
            at,
            level,
            zone_id,
            ..Meter::default()
        });
        meter.total += total;
        meter.best = meter.best.max(total);
        meter.fights += 1;
        meter.fight_ms += seconds_ms_or_zero(f, 3);
    }

    pub fn finish(&self, session: i64, w: &mut Rows) {
        for (spell, cast) in &self.casts {
            prefix(w, "spell_casts", session, cast.at, cast.level, cast.zone_id);
            w.int("spell_id", *spell)
                .int("casts", cast.casts)
                .int("cast_ms", cast.cast_ms);
            w.end();
        }
        for (letter, meter) in &self.meters {
            prefix(
                w,
                "meter_totals",
                session,
                meter.at,
                meter.level,
                meter.zone_id,
            );
            w.str("meter", &letter.to_string())
                .int("total", meter.total)
                .int("best", meter.best)
                .int("fights", meter.fights)
                .int("fight_ms", meter.fight_ms);
            w.end();
        }
    }
}
