//! Lifetime baseline rows of the decoder (docs/ROWS.md): `quest_baselines` (`QB`), `title_baselines` (`KT`),
//! `explored_baselines` (`EB`), `instance_locks` (`IS`) and `reputation_standings` (`RF`). A read that spans several
//! lines of one code is one row.

use crate::decode::{num, prefix};
use crate::rows::Rows;

const EXPLORED_READ_GAP_MS: i64 = 5_000;

#[derive(Clone, Copy)]
pub struct Ctx {
    pub session: i64,
    pub at: i64,
    pub level: Option<i64>,
    pub zone_id: Option<i64>,
}

struct QuestRead {
    ctx: Ctx,
    total: i64,
    ids: Vec<i64>,
}

struct ExploredRead {
    ctx: Ctx,
    last_at: i64,
    maps: Vec<(i64, i64)>,
}

#[derive(Default)]
pub struct Baselines {
    quests: Option<QuestRead>,
    explored: Option<ExploredRead>,
}

fn field<'a>(f: &[&'a str], i: usize) -> &'a str {
    f.get(i).copied().unwrap_or("").trim()
}

fn ids(list: &str) -> Vec<i64> {
    list.split(',')
        .filter_map(|id| id.trim().parse::<i64>().ok())
        .collect()
}

fn joined(ids: &[i64]) -> String {
    ids.iter().map(i64::to_string).collect::<Vec<_>>().join(",")
}

impl Baselines {
    pub fn on_qb(&mut self, c: Ctx, w: &mut Rows, f: &[&str]) {
        let Some(total) = num(f, 0) else {
            return;
        };
        if self.quests.as_ref().is_some_and(|read| read.total != total) {
            self.close_quests(w);
        }
        let read = self.quests.get_or_insert_with(|| QuestRead {
            ctx: c,
            total,
            ids: Vec::new(),
        });
        read.ids.extend(ids(field(f, 1)));
        if i64::try_from(read.ids.len()).is_ok_and(|held| held >= total) {
            self.close_quests(w);
        }
    }

    pub fn on_kt(&mut self, c: Ctx, w: &mut Rows, f: &[&str]) {
        let Some(total) = num(f, 0) else {
            return;
        };
        prefix(w, "title_baselines", c.session, c.at, c.level, c.zone_id);
        w.int("total", total).str("ids", &joined(&ids(field(f, 1))));
        w.end();
    }

    pub fn on_rf(&mut self, c: Ctx, w: &mut Rows, f: &[&str]) {
        let Some(faction_id) = num(f, 0) else {
            return;
        };
        let flag = |i: usize| field(f, i) == "1";
        prefix(w, "reputation_standings", c.session, c.at, c.level, c.zone_id);
        w.int("faction_id", faction_id)
            .opt("reaction", num(f, 1))
            .opt("standing", num(f, 2))
            .opt("floor", num(f, 3))
            .opt("ceiling", num(f, 4))
            .bool("at_war", flag(5))
            .bool("account_wide", flag(6))
            .bool("baseline", field(f, 7) == "s");
        w.end();
    }

    pub fn on_eb(&mut self, c: Ctx, w: &mut Rows, f: &[&str]) {
        if self
            .explored
            .as_ref()
            .is_some_and(|read| c.at - read.last_at > EXPLORED_READ_GAP_MS)
        {
            self.close_explored(w);
        }
        let read = self.explored.get_or_insert_with(|| ExploredRead {
            ctx: c,
            last_at: c.at,
            maps: Vec::new(),
        });
        read.last_at = c.at;
        for pair in field(f, 0).split(',') {
            let Some((map, overlays)) = pair.split_once(':') else {
                continue;
            };
            if let (Ok(map), Ok(overlays)) =
                (map.trim().parse::<i64>(), overlays.trim().parse::<i64>())
            {
                read.maps.push((map, overlays));
            }
        }
    }

    pub fn on_is(&mut self, c: Ctx, w: &mut Rows, f: &[&str]) {
        let flag = |i: usize| field(f, i) == "1";
        prefix(w, "instance_locks", c.session, c.at, c.level, c.zone_id);
        w.text("name", field(f, 0))
            .opt("instance_id", num(f, 1))
            .opt("difficulty_id", num(f, 2))
            .opt("reset_seconds", num(f, 3))
            .bool("locked", flag(4))
            .bool("extended", flag(5))
            .int("raid", num(f, 6).unwrap_or(0))
            .opt("max_players", num(f, 7))
            .opt("done", num(f, 8))
            .opt("total", num(f, 9))
            .text("why", field(f, 10));
        w.end();
    }

    pub fn finish(&mut self, w: &mut Rows) {
        self.close_quests(w);
        self.close_explored(w);
    }

    fn close_quests(&mut self, w: &mut Rows) {
        let Some(read) = self.quests.take() else {
            return;
        };
        let c = read.ctx;
        prefix(w, "quest_baselines", c.session, c.at, c.level, c.zone_id);
        w.int("total", read.total).str("ids", &joined(&read.ids));
        w.end();
    }

    fn close_explored(&mut self, w: &mut Rows) {
        let Some(mut read) = self.explored.take() else {
            return;
        };
        read.maps
            .sort_unstable_by(|a, b| a.0.cmp(&b.0).then(b.1.cmp(&a.1)));
        read.maps.dedup_by_key(|(map, _)| *map);
        let c = read.ctx;
        let zones = read
            .maps
            .iter()
            .filter(|(_, overlays)| *overlays > 0)
            .count();
        let maps = read
            .maps
            .iter()
            .map(|(map, overlays)| format!("{map}:{overlays}"))
            .collect::<Vec<_>>()
            .join(",");
        prefix(w, "explored_baselines", c.session, c.at, c.level, c.zone_id);
        w.int("zones", i64::try_from(zones).unwrap_or(i64::MAX))
            .str("maps", &maps);
        w.end();
    }
}
