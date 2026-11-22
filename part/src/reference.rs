//! Game reference lines and boss loot: what a recipe needs (`RQ`), what a spell is (`SI`) and says (`SD`), and who
//! received an item from a boss encounter (`EL`). docs/ROWS.md has the rows.
//!
//! A recipe or spell is the same for every uploader, so a session writes one row per id, the last line it read.

use crate::decode::{field, num};
use crate::rows::Rows;
use crate::talents::Ctx;
use std::collections::BTreeMap;

struct Recipe {
    at: i64,
    output_item_id: Option<i64>,
    min: Option<i64>,
    max: Option<i64>,
    reagents: String,
    optional: Option<i64>,
    kind: Option<i64>,
}

#[derive(Default)]
struct Spell {
    at: i64,
    info: Option<SpellInfo>,
    description: Option<String>,
}

struct SpellInfo {
    icon_id: Option<i64>,
    cast_ms: Option<i64>,
    min_range: Option<i64>,
    max_range: Option<i64>,
    costs: String,
    passive: Option<bool>,
}

#[derive(Default)]
pub struct Reference {
    recipes: BTreeMap<u64, Recipe>,
    spells: BTreeMap<u64, Spell>,
}

impl Reference {
    pub fn on_rq(&mut self, at: i64, f: &[&str]) {
        let Some(id) = crate::tables::leading_id(field(f, 0)) else {
            return;
        };
        self.recipes.insert(
            id,
            Recipe {
                at,
                output_item_id: num(f, 1),
                min: num(f, 2),
                max: num(f, 3),
                reagents: field(f, 4).to_string(),
                optional: num(f, 5),
                kind: num(f, 6),
            },
        );
    }

    pub fn on_si(&mut self, at: i64, id: u64, f: &[&str]) {
        let spell = self.spells.entry(id).or_default();
        spell.at = spell.at.max(at);
        spell.info = Some(SpellInfo {
            icon_id: num(f, 1),
            cast_ms: num(f, 2),
            min_range: num(f, 3),
            max_range: num(f, 4),
            costs: field(f, 5).to_string(),
            passive: match field(f, 6) {
                "1" => Some(true),
                "0" => Some(false),
                _ => None,
            },
        });
    }

    pub fn on_sd(&mut self, at: i64, id: u64, f: &[&str]) {
        let text = field(f, 1);
        if text.is_empty() {
            return;
        }
        let spell = self.spells.entry(id).or_default();
        spell.at = spell.at.max(at);
        spell.description = Some(text.chars().take(300).collect());
    }

    pub fn on_el(ctx: &Ctx, w: &mut Rows, f: &[&str]) {
        ctx.begin(w, "boss_loot");
        w.opt("encounter_id", num(f, 0))
            .opt("item_id", num(f, 1))
            .opt("quantity", num(f, 2))
            .text("who", field(f, 3))
            .text("class_file", field(f, 4));
        w.end();
    }

    pub fn finish(&mut self, w: &mut Rows, session: i64) {
        for (id, r) in std::mem::take(&mut self.recipes) {
            w.begin("recipes");
            w.int("session", session)
                .int("recipe_spell_id", id as i64)
                .int("at", r.at)
                .opt("output_item_id", r.output_item_id)
                .opt("min_quantity", r.min)
                .opt("max_quantity", r.max)
                .text("reagents", &r.reagents)
                .opt("optional_slots", r.optional)
                .opt("type", r.kind);
            w.end();
        }
        for (id, s) in std::mem::take(&mut self.spells) {
            w.begin("spells");
            w.int("session", session)
                .int("spell_id", id as i64)
                .int("at", s.at);
            match &s.info {
                Some(i) => {
                    w.opt("icon_id", i.icon_id)
                        .opt("cast_ms", i.cast_ms)
                        .opt("min_range", i.min_range)
                        .opt("max_range", i.max_range)
                        .text("costs", &i.costs)
                        .opt_bool("passive", i.passive);
                }
                None => {
                    w.null("icon_id")
                        .null("cast_ms")
                        .null("min_range")
                        .null("max_range")
                        .null("costs")
                        .null("passive");
                }
            }
            match &s.description {
                Some(text) => w.str("description", text),
                None => w.null("description"),
            };
            w.end();
        }
    }
}
