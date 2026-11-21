//! Talent rows of the decoder (docs/ROWS.md): `talent_changes`, `talent_snapshots`, `talent_respecs`, and the
//! layout rows `talent_trees` and `talent_nodes`. The state it keeps is one session's: the ranks of each talent
//! group as the `TA` lines left them.

use crate::decode::num;
use crate::rows::Rows;
use std::collections::BTreeMap;

const OFFER_TO_REFUND_MS: i64 = 10 * 60 * 1000;
const RESET_TO_REFUND_MS: i64 = 60 * 1000;

type Held = (i64, i64, Option<i64>, Option<i64>);
type Ranks = BTreeMap<i64, Held>;

pub struct Ctx {
    pub session: i64,
    pub at: i64,
    pub level: Option<i64>,
    pub zone_id: Option<i64>,
}

impl Ctx {
    fn begin(&self, w: &mut Rows, table: &str) {
        w.begin(table);
        w.int("session", self.session).int("at", self.at);
        w.opt("level", self.level).opt("zone_id", self.zone_id);
    }
}

struct Respec {
    index: i64,
    at: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    group: i64,
    kind: &'static str,
    cost: Option<i64>,
    moved: i64,
    before: String,
    before_points: i64,
    after: Option<String>,
}

#[derive(Default)]
pub struct Talents {
    build: String,
    ranks: BTreeMap<i64, BTreeMap<i64, Held>>,
    baselined: Vec<i64>,
    last_key: BTreeMap<i64, String>,
    offer: Option<(i64, i64)>,
    reset: Option<(i64, &'static str)>,
    open: BTreeMap<i64, Respec>,
    done: Vec<Respec>,
    next_index: i64,
    trees: BTreeMap<i64, String>,
}

fn hash(ranks: &Ranks) -> String {
    let mut h: u64 = 0xcbf2_9ce4_8422_2325;
    for (node, (entry, rank, ..)) in ranks {
        for b in format!("{node}:{entry}:{rank},").bytes() {
            h ^= u64::from(b);
            h = h.wrapping_mul(0x0100_0000_01b3);
        }
    }
    format!("{h:016x}")
}

fn points(ranks: &Ranks) -> i64 {
    ranks.values().map(|held| held.1).sum()
}

fn tab_with_most_points(tabs: &str) -> Option<i64> {
    let mut best: Option<(i64, i64)> = None;
    let mut tie = false;
    for tab in tabs.split(',') {
        let mut it = tab.split(':');
        let (Some(id), Some(spent)) = (
            it.next().and_then(|s| s.parse::<i64>().ok()),
            it.next().and_then(|s| s.parse::<i64>().ok()),
        ) else {
            continue;
        };
        match best {
            Some((_, p)) if spent == p => tie = true,
            Some((_, p)) if spent < p => {}
            _ => {
                best = Some((id, spent));
                tie = false;
            }
        }
    }
    best.filter(|(_, p)| *p > 0 && !tie).map(|(id, _)| id)
}

impl Talents {
    pub fn set_build(&mut self, build: &str) {
        self.build = build.trim().to_string();
    }

    fn current(&self, group: i64) -> Ranks {
        self.ranks.get(&group).cloned().unwrap_or_default()
    }

    pub fn on_tv(&mut self, c: &Ctx, w: &mut Rows, f: &[&str]) {
        let (Some(tree), Some(class), Some(count)) = (num(f, 0), num(f, 1), num(f, 2)) else {
            return;
        };
        let Some(layout) = f.get(3).map(|s| s.trim()).filter(|s| !s.is_empty()) else {
            return;
        };
        self.trees.insert(tree, layout.to_string());
        w.begin("talent_trees");
        w.int("session", c.session).text("build", &self.build);
        w.int("tree_id", tree)
            .int("class_id", class)
            .int("node_count", count)
            .str("layout_hash", layout);
        w.end();
    }

    pub fn on_td(&mut self, c: &Ctx, w: &mut Rows, f: &[&str]) {
        let (Some(tree), Some(node)) = (num(f, 0), num(f, 1)) else {
            return;
        };
        let Some(layout) = self.trees.get(&tree).cloned() else {
            return;
        };
        let text = |i: usize| f.get(i).map_or("", |s| s.trim());
        w.begin("talent_nodes");
        w.int("session", c.session).text("build", &self.build);
        w.int("tree_id", tree)
            .str("layout_hash", &layout)
            .int("node_id", node)
            .opt("pos_x", num(f, 2))
            .opt("pos_y", num(f, 3))
            .text("node_type", text(4))
            .opt("max_ranks", num(f, 5))
            .opt("tab_id", num(f, 6))
            .text("entries", text(7))
            .text("edges", text(8));
        w.end();
    }

    pub fn on_tq(&mut self, c: &Ctx, f: &[&str]) {
        match f.first().map(|s| s.trim()) {
            Some("p") => {
                if let Some(cost) = num(f, 1) {
                    self.offer = Some((c.at, cost));
                }
            }
            Some("r") if num(f, 1) != Some(1) => self.reset = Some((c.at, "reset")),
            Some("i") if num(f, 1) != Some(1) => self.reset = Some((c.at, "spec")),
            _ => {}
        }
    }

    pub fn on_ta(&mut self, c: &Ctx, w: &mut Rows, f: &[&str]) {
        let (Some(group), Some(node), Some(rank)) = (num(f, 0), num(f, 1), num(f, 3)) else {
            return;
        };
        let entry = num(f, 2).unwrap_or(0);
        let why = f.get(8).map_or("", |s| s.trim());
        if why == "s" {
            self.apply(group, node, (entry, rank, num(f, 6), num(f, 7)));
            if !self.baselined.contains(&group) {
                self.baselined.push(group);
            }
            return;
        }
        let before = self.current(group);
        let old = num(f, 4).unwrap_or_else(|| before.get(&node).map_or(0, |held| held.1));
        let kind = match rank.cmp(&old) {
            std::cmp::Ordering::Greater => "pick",
            std::cmp::Ordering::Less => "refund",
            std::cmp::Ordering::Equal => "swap",
        };
        let hash_before = hash(&before);
        let points_before = points(&before);
        self.apply(group, node, (entry, rank, num(f, 6), num(f, 7)));
        let now = self.current(group);
        let total = points(&now);
        let index = match kind {
            "refund" => {
                Some(self.open_or_join_respec(c, group, old - rank, hash_before, points_before))
            }
            _ => self.respec_being_rebuilt(c, group, kind == "pick", total, &now, &hash_before),
        };
        let point_no = (kind == "pick" && self.baselined.contains(&group)).then_some(total);
        c.begin(w, "talent_changes");
        w.int("spec_group", group)
            .int("node_id", node)
            .opt("entry_id", num(f, 2))
            .int("rank", rank)
            .int("old_rank", old)
            .opt("max_ranks", num(f, 5))
            .opt("spell_id", num(f, 6))
            .str("kind", kind)
            .opt("point_no", point_no)
            .opt("respec_index", index);
        w.end();
    }

    fn apply(&mut self, group: i64, node: i64, held: Held) {
        let map = self.ranks.entry(group).or_default();
        if held.1 > 0 {
            map.insert(node, held);
            return;
        }
        map.remove(&node);
    }

    fn open_or_join_respec(
        &mut self,
        c: &Ctx,
        group: i64,
        moved: i64,
        before: String,
        before_points: i64,
    ) -> i64 {
        if let Some(open) = self.open.get_mut(&group)
            && open.at == c.at
        {
            open.moved += moved;
            return open.index;
        }
        self.close(group, Some(before.clone()));
        let kind = match self.reset {
            Some((at, kind)) if c.at - at <= RESET_TO_REFUND_MS && c.at >= at => kind,
            _ => "refund",
        };
        let cost = match self.offer {
            Some((at, cost)) if c.at - at <= OFFER_TO_REFUND_MS && c.at >= at => Some(cost),
            _ => None,
        };
        let index = self.next_index;
        self.next_index += 1;
        self.open.insert(
            group,
            Respec {
                index,
                at: c.at,
                level: c.level,
                zone_id: c.zone_id,
                group,
                kind,
                cost,
                moved,
                before,
                before_points,
                after: None,
            },
        );
        index
    }

    fn respec_being_rebuilt(
        &mut self,
        c: &Ctx,
        group: i64,
        pick: bool,
        total: i64,
        now: &Ranks,
        before: &str,
    ) -> Option<i64> {
        let open = self.open.get(&group)?;
        if open.level != c.level {
            self.close(group, Some(before.to_string()));
            return None;
        }
        let index = open.index;
        if pick && total >= open.before_points {
            self.close(group, Some(hash(now)));
        }
        Some(index)
    }

    fn close(&mut self, group: i64, after: Option<String>) {
        if let Some(mut open) = self.open.remove(&group) {
            open.after = after;
            self.done.push(open);
        }
    }

    pub fn on_tg(&mut self, c: &Ctx, w: &mut Rows, f: &[&str]) {
        let (Some(group), Some(tree)) = (num(f, 1), num(f, 5)) else {
            return;
        };
        let ranks = self.current(group);
        let build_hash = hash(&ranks);
        let key = format!("{build_hash}|{}", f.get(10).map_or("", |s| s.trim()));
        if self.last_key.get(&group) == Some(&key) {
            return;
        }
        self.last_key.insert(group, key);
        let list: Vec<String> = ranks
            .iter()
            .map(|(node, (entry, rank, spell, tab))| {
                let id = |v: &Option<i64>| v.map_or(String::new(), |v| v.to_string());
                format!("{node}:{entry}:{rank}:{}:{}", id(spell), id(tab))
            })
            .collect();
        let text = |i: usize| f.get(i).map_or("", |s| s.trim());
        c.begin(w, "talent_snapshots");
        w.int("spec_group", group)
            .opt("groups", num(f, 2))
            .opt("spec_id", num(f, 3))
            .text("spec", text(4))
            .int("tree_id", tree)
            .text("tab_points", text(6))
            .opt("spec_tab_id", tab_with_most_points(text(6)))
            .opt("class_points", num(f, 7))
            .opt("spec_points", num(f, 8))
            .opt("config_id", num(f, 9))
            .str("build_hash", &build_hash)
            .str("ranks", &list.join(","))
            .text("loadout", text(10))
            .str("why", text(0));
        w.end();
    }

    pub fn finish(&mut self, session: i64, w: &mut Rows) {
        let groups: Vec<i64> = self.open.keys().copied().collect();
        for group in groups {
            let after = hash(&self.current(group));
            self.close(group, Some(after));
        }
        self.done.sort_by_key(|r| r.index);
        for r in std::mem::take(&mut self.done) {
            Ctx {
                session,
                at: r.at,
                level: r.level,
                zone_id: r.zone_id,
            }
            .begin(w, "talent_respecs");
            w.int("spec_group", r.group)
                .str("kind", r.kind)
                .opt("cost_copper", r.cost)
                .int("points_moved", r.moved)
                .str("hash_before", &r.before)
                .text("hash_after", r.after.as_deref().unwrap_or(""))
                .int("respec_index", r.index);
            w.end();
        }
    }
}
