//! The spell casts, damage-meter totals and top spells of a session (docs/ROWS.md, `spell_casts`, `meter_totals` and
//! `meter_spells`), read from the `C`, `CH`, `CI`, `DM`, `DP` and `DS` lines.

use crate::class::{Now, seconds_ms};
use crate::decode::{field, num, prefix};
use crate::rows::Rows;
use std::collections::BTreeMap;

const SAME_REPORT_MS: i64 = 1_000;

struct Stamp {
    at: i64,
    zone_id: Option<i64>,
}

impl Stamp {
    fn of(now: &Now) -> Self {
        Stamp {
            at: now.at,
            zone_id: now.zone_id,
        }
    }
}

struct Cast {
    stamp: Stamp,
    casts: i64,
    cast_ms: i64,
    fight_casts: i64,
    channel_ms: i64,
    interrupts: i64,
    streak: i64,
}

struct Meter {
    stamp: Stamp,
    total: i64,
    best: i64,
    fights: i64,
    fight_ms: i64,
    group_total: i64,
    group_own: i64,
    group_fights: i64,
}

struct Top {
    stamp: Stamp,
    amount: i64,
    fights: i64,
}

#[derive(Clone, Copy, PartialEq, Eq, PartialOrd, Ord)]
struct MeterKey {
    meter: char,
    level: Option<i64>,
    place: char,
}

struct Report {
    started: i64,
    now: Now,
    own: Option<i64>,
    others: i64,
    peers: i64,
}

#[derive(Default)]
pub struct Combat {
    casts: BTreeMap<(i64, Option<i64>), Cast>,
    last_cast: Option<i64>,
    run: i64,
    meters: BTreeMap<MeterKey, Meter>,
    reports: BTreeMap<char, Report>,
    tops: BTreeMap<(char, i64, Option<i64>), Top>,
}

fn meter_letter(code: &str) -> Option<char> {
    match code {
        "d" | "h" | "t" | "a" | "i" | "x" => code.chars().next(),
        _ => None,
    }
}

impl Combat {
    fn cast_row(&mut self, now: &Now, spell: i64) -> &mut Cast {
        self.casts
            .entry((spell, now.level))
            .or_insert_with(|| Cast {
                stamp: Stamp::of(now),
                casts: 0,
                cast_ms: 0,
                fight_casts: 0,
                channel_ms: 0,
                interrupts: 0,
                streak: 0,
            })
    }

    pub fn on_cast(&mut self, now: &Now, fighting: bool, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        self.run = if self.last_cast == Some(spell) {
            self.run + 1
        } else {
            1
        };
        self.last_cast = Some(spell);
        let run = self.run;
        let cast = self.cast_row(now, spell);
        cast.casts += 1;
        cast.cast_ms += seconds_ms(f, 1);
        cast.fight_casts += i64::from(fighting);
        cast.streak = cast.streak.max(run);
    }

    pub fn on_channel(&mut self, now: &Now, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        self.cast_row(now, spell).channel_ms += seconds_ms(f, 1);
    }

    pub fn on_interrupt(&mut self, now: &Now, f: &[&str]) {
        let Some(spell) = num(f, 0) else { return };
        self.cast_row(now, spell).interrupts += 1;
    }

    fn report(&mut self, meter: char, now: &Now, own_line: bool) -> &mut Report {
        let stale = self
            .reports
            .get(&meter)
            .is_some_and(|r| now.at - r.started > SAME_REPORT_MS || (own_line && r.own.is_some()));
        if stale {
            self.close_report(meter);
        }
        self.reports.entry(meter).or_insert_with(|| Report {
            started: now.at,
            now: *now,
            own: None,
            others: 0,
            peers: 0,
        })
    }

    fn meter_row(&mut self, meter: char, now: &Now) -> &mut Meter {
        let key = MeterKey {
            meter,
            level: now.level,
            place: now.place,
        };
        self.meters.entry(key).or_insert_with(|| Meter {
            stamp: Stamp::of(now),
            total: 0,
            best: 0,
            fights: 0,
            fight_ms: 0,
            group_total: 0,
            group_own: 0,
            group_fights: 0,
        })
    }

    fn close_report(&mut self, meter: char) {
        let Some(report) = self.reports.remove(&meter) else {
            return;
        };
        if report.peers == 0 {
            return;
        }
        let own = report.own.unwrap_or(0);
        let row = self.meter_row(meter, &report.now);
        row.group_total += own + report.others;
        row.group_own += own;
        row.group_fights += 1;
    }

    /// A line with an unknown meter letter or a total that is not a number writes nothing.
    pub fn on_meter(&mut self, now: &Now, f: &[&str]) {
        let (Some(meter), Some(total)) = (meter_letter(field(f, 0)), num(f, 1)) else {
            return;
        };
        let row = self.meter_row(meter, now);
        row.total += total;
        row.best = row.best.max(total);
        row.fights += 1;
        row.fight_ms += seconds_ms(f, 3);
        let report = self.report(meter, now, true);
        report.own = Some(total);
        report.now = *now;
    }

    /// A group member's total. The addon writes the uploader's own total as `DM`, never as `DP`.
    pub fn on_peer(&mut self, now: &Now, f: &[&str]) {
        let (Some(meter), Some(total)) = (meter_letter(field(f, 0)), num(f, 3)) else {
            return;
        };
        let report = self.report(meter, now, false);
        report.others += total;
        report.peers += 1;
    }

    pub fn on_top_spell(&mut self, now: &Now, f: &[&str]) {
        let (Some(meter), Some(spell), Some(amount)) =
            (meter_letter(field(f, 0)), num(f, 1), num(f, 2))
        else {
            return;
        };
        let top = self
            .tops
            .entry((meter, spell, now.level))
            .or_insert_with(|| Top {
                stamp: Stamp::of(now),
                amount: 0,
                fights: 0,
            });
        top.amount += amount;
        top.fights += 1;
    }

    pub fn finish(&mut self, session: i64, w: &mut Rows) {
        let open: Vec<char> = self.reports.keys().copied().collect();
        for meter in open {
            self.close_report(meter);
        }
        for ((spell, level), cast) in &self.casts {
            prefix(
                w,
                "spell_casts",
                session,
                cast.stamp.at,
                *level,
                cast.stamp.zone_id,
            );
            w.int("spell_id", *spell)
                .int("casts", cast.casts)
                .int("cast_ms", cast.cast_ms)
                .int("fight_casts", cast.fight_casts)
                .int("channel_ms", cast.channel_ms)
                .int("interrupts", cast.interrupts)
                .int("streak", cast.streak);
            w.end();
        }
        for (key, meter) in &self.meters {
            prefix(
                w,
                "meter_totals",
                session,
                meter.stamp.at,
                key.level,
                meter.stamp.zone_id,
            );
            w.str("meter", &key.meter.to_string())
                .str("place", &key.place.to_string())
                .int("total", meter.total)
                .int("best", meter.best)
                .int("fights", meter.fights)
                .int("fight_ms", meter.fight_ms)
                .int("group_total", meter.group_total)
                .int("group_own", meter.group_own)
                .int("group_fights", meter.group_fights);
            w.end();
        }
        for ((meter, spell, level), top) in &self.tops {
            prefix(
                w,
                "meter_spells",
                session,
                top.stamp.at,
                *level,
                top.stamp.zone_id,
            );
            w.str("meter", &meter.to_string())
                .int("spell_id", *spell)
                .int("amount", top.amount)
                .int("fights", top.fights);
            w.end();
        }
    }
}
