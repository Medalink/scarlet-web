//! Loot windows a group member's Scarlet read and sent to the group (`LW`): the `peer_opens` and `peer_drops` rows, and
//! the source a member's receipt (`group_loot`) came from. docs/ROWS.md has the rows.

use crate::decode::{corpse_key, field, num, window_items};
use crate::group::{Rule, name_key};
use crate::rows::Rows;
use crate::tables::leading_id;
use crate::talents::Ctx;

const MATCH_MS: i64 = 60_000;
const FISHING_BOBBER: i64 = 35591;
const MAX_COLUMN: i64 = i32::MAX as i64;
const MAX_LATE_S: i64 = 86_400;
const MAX_WHO: usize = 64;

struct Window {
    who: String,
    read_at: i64,
    kind: String,
    id: i64,
    items: Vec<(i64, i64)>,
}

#[derive(Default)]
pub struct Peers {
    windows: Vec<Window>,
}

impl Peers {
    /// One `LW` line: who, kind, id, slots, items, again, late, key. A line that is not of kind `c` or `g`, or names
    /// no sender, is not a window.
    pub fn on_lw(
        &mut self,
        ctx: &Ctx,
        w: &mut Rows,
        rule: &Rule,
        group_at: Option<i64>,
        f: &[&str],
    ) {
        let (who, kind) = (field(f, 0), field(f, 1));
        if who.is_empty() || who.len() > MAX_WHO || !matches!(kind, "c" | "g") {
            return;
        }
        let id = leading_id(field(f, 2))
            .and_then(|n| i64::try_from(n).ok())
            .filter(|&n| n <= MAX_COLUMN);
        let first = match field(f, 5) {
            "0" => Some(true),
            "1" => Some(false),
            _ => None,
        };
        let list = field(f, 4);
        let late = num(f, 6).filter(|s| (0..=MAX_LATE_S).contains(s));
        let slots = num(f, 3).filter(|s| (0..=i64::from(i16::MAX)).contains(s));
        let key = corpse_key(f, 7);
        let mut items = window_items(list);
        items.retain(|&(item, qty)| item <= MAX_COLUMN && qty <= MAX_COLUMN);
        let read = if kind == "g" && id == Some(FISHING_BOBBER) {
            "fish"
        } else {
            ""
        };

        ctx.begin(w, "peer_opens");
        w.str("source_kind", kind).opt("source_id", id);
        match first {
            Some(b) => w.bool("first_read", b),
            None => w.null("first_read"),
        };
        w.opt("slots", slots).bool("has_items", !list.is_empty());
        rule.write(w);
        w.text("read", read)
            .opt(
                "item_rows",
                (!list.is_empty()).then_some(items.len() as i64),
            )
            .opt("group_at", group_at)
            .str("who", who)
            .opt("late_s", late)
            .text("corpse_key", key);
        w.end();

        for &(item, qty) in &items {
            ctx.begin(w, "peer_drops");
            w.str("source_kind", kind).opt("source_id", id);
            match first {
                Some(b) => w.bool("first_read", b),
                None => w.null("first_read"),
            };
            w.int("item_id", item).int("quantity", qty);
            rule.write(w);
            w.text("read", read)
                .str("who", who)
                .opt("late_s", late)
                .text("corpse_key", key);
            w.end();
        }

        if let (Some(id), false) = (id, items.is_empty()) {
            self.windows.push(Window {
                who: name_key(who),
                read_at: ctx.at - late.unwrap_or(0) * 1000,
                kind: kind.to_string(),
                id,
                items,
            });
        }
    }

    /// The source of what `who` received at `at`: the window of the same member that lists the item and was read
    /// closest to `at`, within a minute of it. A window is not used up by a receipt, so two receipts of one item can
    /// name one source.
    pub fn source(&self, who: &str, item: i64, at: i64) -> Option<(&str, i64)> {
        let who = name_key(who);
        self.windows
            .iter()
            .filter(|w| w.who == who && w.items.iter().any(|&(i, _)| i == item))
            .map(|w| (w, (at - w.read_at).abs()))
            .filter(|&(_, gap)| gap <= MATCH_MS)
            .min_by_key(|&(_, gap)| gap)
            .map(|(w, _)| (w.kind.as_str(), w.id))
    }
}
