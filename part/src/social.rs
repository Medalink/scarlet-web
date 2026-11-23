//! The lines about other players: the server's hardcore death notice (`HD`), a /who row (`WH`),
//! the gear of an inspected player (`IG`), and a match with its scoreboard (`BM`, `BO`).
//! docs/ROWS.md has the rows.

use crate::decode::{field, num};
use crate::rows::Rows;
use crate::talents::Ctx;
use std::collections::HashMap;

const MATCH_END_TO_LAST_ROW_MS: i64 = 10 * 60 * 1000;
const LOOSE_ROWS_MAX_GAP_MS: i64 = 2 * 60 * 1000;
const SCOREBOARD_ROWS_MAX: usize = 20_000;

struct Open {
    instance: Option<i64>,
    zone: String,
    rated: Option<bool>,
    arena: Option<bool>,
}

#[derive(Default)]
pub struct Social {
    open: Option<Open>,
    /// The end of the last match: when (ms) and its instance.
    ended: Option<(i64, Option<i64>)>,
    /// The scoreboard of a match without an end line: when its first row came and the time of its latest.
    loose: Option<(i64, i64)>,
    /// Scoreboard rows already kept, per match of this decode.
    scores: HashMap<i64, usize>,
}

fn flag(s: &str) -> Option<bool> {
    match s {
        "1" => Some(true),
        "0" => Some(false),
        _ => None,
    }
}

impl Social {
    pub fn on_hd(ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        ctx.begin(w, "hardcore_deaths");
        w.text("who", field(f, 0))
            .opt("died_level", num(f, 1))
            .text("notice", field(f, 2));
        w.end();
    }

    pub fn on_wh(ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        ctx.begin(w, "who_results");
        w.text("name", field(f, 0))
            .opt("player_level", num(f, 1))
            .text("class_file", field(f, 2))
            .text("race", field(f, 3))
            .text("zone_name", field(f, 4))
            .text("guild", field(f, 5));
        w.end();
    }

    pub fn on_ig(ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        ctx.begin(w, "inspected_gear");
        w.text("name", field(f, 0)).text("items", field(f, 1));
        w.end();
    }

    pub fn on_bm(&mut self, ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        match field(f, 0) {
            "1" => {
                let zone = field(f, 2);
                let open = self.open.get_or_insert(Open {
                    instance: None,
                    zone: String::new(),
                    rated: None,
                    arena: None,
                });
                open.instance = num(f, 1);
                if !zone.is_empty() {
                    open.zone = zone.to_string();
                }
                open.rated = flag(field(f, 6));
                open.arena = flag(field(f, 8));
            }
            "0" => {
                let instance = num(f, 1);
                let open = self.open.take().filter(|o| o.instance == instance);
                ctx.begin(w, "battleground_matches");
                w.opt("instance_id", instance)
                    .text("zone_name", open.as_ref().map_or("", |o| o.zone.as_str()))
                    .opt_bool("rated", open.as_ref().and_then(|o| o.rated))
                    .opt_bool("arena", flag(field(f, 7)).or(open.and_then(|o| o.arena)))
                    .opt("team", num(f, 4))
                    .opt("winner", num(f, 2))
                    .opt("duration", num(f, 3))
                    .text("result", field(f, 5))
                    .opt_bool("affects_rating", flag(field(f, 6)));
                w.end();
                self.ended = Some((ctx.at, instance));
                self.loose = None;
            }
            _ => {}
        }
    }

    pub fn on_bo(&mut self, ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        let (match_at, instance) = self.scoreboard(ctx.at);
        let kept = self.scores.entry(match_at).or_insert(0);
        if *kept >= SCOREBOARD_ROWS_MAX {
            return;
        }
        *kept += 1;
        ctx.begin(w, "battleground_scores");
        w.int("match_at", match_at)
            .opt("instance_id", instance)
            .text("name", field(f, 0))
            .opt("faction", num(f, 1))
            .text("class_file", field(f, 2))
            .opt("killing_blows", num(f, 3))
            .opt("honorable_kills", num(f, 4))
            .opt("deaths", num(f, 5))
            .opt("honor_gained", num(f, 6))
            .opt("damage_done", num(f, 7))
            .opt("healing_done", num(f, 8))
            .text("stats", field(f, 9));
        w.end();
    }

    fn scoreboard(&mut self, at: i64) -> (i64, Option<i64>) {
        if let Some((ended, instance)) = self.ended
            && (0..=MATCH_END_TO_LAST_ROW_MS).contains(&(at - ended))
        {
            return (ended, instance);
        }
        let first = match self.loose {
            Some((first, last)) if (0..=LOOSE_ROWS_MAX_GAP_MS).contains(&(at - last)) => first,
            _ => at,
        };
        self.loose = Some((first, at));
        (first, None)
    }
}
