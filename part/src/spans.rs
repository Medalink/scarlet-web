//! The `state_times` rows (docs/ROWS.md): time the character spent in a state, split by level, with the part of it
//! inside fights. Machines open a span when a state starts and end it when it stops; the ledger credits the time and
//! splits every open span at a level-up.

use crate::class::{Now, TRACKED_MAX, clip, is_id};
use crate::rows::Rows;
use std::collections::BTreeMap;

pub type SpanId = u64;

const KINDS_WITH_KNOWN_FIGHT_MS: &str = "abxrtf";

fn within(value: i64, most: i64) -> i64 {
    value.max(0).min(most.max(0))
}

/// The time of the session's fights, to ask how much of any stretch lay inside one.
#[derive(Default)]
pub struct FightClock {
    fights: Vec<(i64, i64)>,
    totals: Vec<i64>,
    open: Option<i64>,
}

impl FightClock {
    pub fn open(&self) -> Option<i64> {
        self.open
    }

    pub fn start(&mut self, t: i64) -> bool {
        if self.open.is_some() {
            return false;
        }
        self.open = Some(t);
        true
    }

    pub fn end(&mut self, t: i64) -> bool {
        let Some(start) = self.open.take() else {
            return false;
        };
        let end = t.max(start);
        let before = self.totals.last().copied().unwrap_or(0);
        self.fights.push((start, end));
        self.totals.push(before + end - start);
        true
    }

    /// Fight milliseconds from the session's start up to `t`.
    pub fn at(&self, t: i64) -> i64 {
        let k = self.fights.partition_point(|f| f.1 <= t);
        let mut total = k.checked_sub(1).map_or(0, |i| self.totals[i]);
        match self.fights.get(k) {
            Some(&(start, _)) if start < t => total += t - start,
            Some(_) => {}
            None => {
                if let Some(start) = self.open.filter(|s| *s < t) {
                    total += t - start;
                }
            }
        }
        total
    }
}

#[derive(PartialEq, Eq, PartialOrd, Ord, Clone)]
struct Key {
    kind: char,
    ref_id: i64,
    name: String,
    level: Option<i64>,
}

struct Row {
    at: i64,
    zone_id: Option<i64>,
    extra: String,
    starts: i64,
    ms: i64,
    fight_ms: i64,
}

struct Open {
    kind: char,
    ref_id: i64,
    name: String,
    extra: String,
    since: i64,
    credited: i64,
    deadline: Option<i64>,
}

/// A state that starts now, or at `since` when it was already on.
pub struct Begin {
    kind: char,
    ref_id: i64,
    name: String,
    extra: String,
    since: Option<i64>,
    deadline: Option<i64>,
    counted: bool,
}

impl Begin {
    pub fn new(kind: char, ref_id: i64) -> Self {
        Begin {
            kind,
            ref_id,
            name: String::new(),
            extra: String::new(),
            since: None,
            deadline: None,
            counted: true,
        }
    }

    pub fn name(mut self, name: &str) -> Self {
        self.name = clip(name);
        self
    }

    pub fn extra(mut self, extra: &str) -> Self {
        self.extra = clip(extra);
        self
    }

    pub fn since(mut self, since: i64) -> Self {
        self.since = Some(since);
        self
    }

    pub fn until(mut self, deadline: i64) -> Self {
        self.deadline = Some(deadline);
        self
    }

    pub fn uncounted(mut self) -> Self {
        self.counted = false;
        self
    }
}

#[derive(Default)]
pub struct Ledger {
    rows: BTreeMap<Key, Row>,
    open: BTreeMap<SpanId, Open>,
    open_of_kind: BTreeMap<char, usize>,
    next: SpanId,
    at: i64,
    len: i64,
    level: Option<i64>,
    zone_id: Option<i64>,
    pub clock: FightClock,
}

impl Ledger {
    pub fn sync(&mut self, now: &Now) {
        self.at = now.at;
        self.len = now.len;
        self.level = now.level;
        self.zone_id = now.zone_id;
    }

    fn touch(&mut self, kind: char, ref_id: i64, name: &str, extra: &str) -> &mut Row {
        let key = Key {
            kind,
            ref_id,
            name: name.to_string(),
            level: self.level,
        };
        let (at, zone_id) = (self.at, self.zone_id);
        let row = self.rows.entry(key).or_insert_with(|| Row {
            at,
            zone_id,
            extra: extra.to_string(),
            starts: 0,
            ms: 0,
            fight_ms: 0,
        });
        if row.extra.is_empty() {
            row.extra = extra.to_string();
        }
        row
    }

    /// Start a span. A state whose id no column holds, or that would be the 513th open of its kind, is not recorded:
    /// its span is never open and credits nothing.
    pub fn begin(&mut self, b: Begin) -> SpanId {
        self.next += 1;
        let id = self.next;
        let open_now = self.open_of_kind.get(&b.kind).copied().unwrap_or(0);
        if !is_id(b.ref_id) || open_now >= TRACKED_MAX {
            return id;
        }
        self.open_of_kind.insert(b.kind, open_now + 1);
        let row = self.touch(b.kind, b.ref_id, &b.name, &b.extra);
        row.starts += i64::from(b.counted);
        self.open.insert(
            id,
            Open {
                kind: b.kind,
                ref_id: b.ref_id,
                name: b.name,
                extra: b.extra,
                since: b.since.unwrap_or(self.at),
                credited: 0,
                deadline: b.deadline,
            },
        );
        id
    }

    pub fn fill_extra(&mut self, kind: char, ref_id: i64, name: &str, extra: &str) {
        self.touch(kind, ref_id, &clip(name), &clip(extra));
    }

    /// End a span now. `exact` is the total time the line itself reports, which replaces the measured time.
    pub fn end(&mut self, id: SpanId, exact: Option<i64>) {
        self.credit(id, self.at, exact);
        if let Some(open) = self.open.remove(&id)
            && let Some(count) = self.open_of_kind.get_mut(&open.kind)
        {
            *count = count.saturating_sub(1);
        }
    }

    /// Time that a line reports for a state whose start was not seen.
    pub fn add_unseen(&mut self, kind: char, ref_id: i64, ms: i64) {
        if !is_id(ref_id) {
            return;
        }
        let ms = within(ms, self.len);
        let fight = within(self.clock.at(self.at) - self.clock.at(self.at - ms), ms);
        let row = self.touch(kind, ref_id, "", "");
        row.ms = row.ms.saturating_add(ms);
        row.fight_ms = row.fight_ms.saturating_add(fight);
    }

    fn credit(&mut self, id: SpanId, t: i64, exact: Option<i64>) {
        let Some(open) = self.open.get_mut(&id) else {
            return;
        };
        let end = open.deadline.map_or(t, |d| d.min(t)).max(open.since);
        let ms = match exact {
            Some(total) => total.saturating_sub(open.credited),
            None => end - open.since,
        };
        let ms = within(ms, self.len);
        let fight = within(self.clock.at(end) - self.clock.at(open.since), ms);
        open.credited = open.credited.saturating_add(ms);
        open.since = open.since.max(t);
        let key = Key {
            kind: open.kind,
            ref_id: open.ref_id,
            name: open.name.clone(),
            level: self.level,
        };
        let (at, zone_id, extra) = (self.at, self.zone_id, open.extra.clone());
        let row = self.rows.entry(key).or_insert_with(|| Row {
            at,
            zone_id,
            extra,
            starts: 0,
            ms: 0,
            fight_ms: 0,
        });
        row.ms = row.ms.saturating_add(ms);
        row.fight_ms = row.fight_ms.saturating_add(fight);
    }

    /// The level changes now: every open span is credited to the old level up to here and goes on at the new one.
    pub fn level_up(&mut self, new: Option<i64>) {
        if new == self.level {
            return;
        }
        let ids: Vec<SpanId> = self.open.keys().copied().collect();
        for id in &ids {
            self.credit(*id, self.at, None);
        }
        self.level = new;
        for id in ids {
            let Some(open) = self.open.get(&id) else {
                continue;
            };
            let (kind, ref_id, name, extra) = (
                open.kind,
                open.ref_id,
                open.name.clone(),
                open.extra.clone(),
            );
            self.touch(kind, ref_id, &name, &extra);
        }
    }

    /// The session ended at `end`: every open span stops there.
    pub fn finish(&mut self, end: i64) {
        self.at = end;
        let ids: Vec<SpanId> = self.open.keys().copied().collect();
        for id in ids {
            self.end(id, None);
        }
    }

    pub fn write(&self, session: i64, combat: bool, w: &mut Rows) {
        for (key, row) in &self.rows {
            if row.starts == 0 && row.ms == 0 {
                continue;
            }
            crate::decode::prefix(w, "state_times", session, row.at, key.level, row.zone_id);
            let known = combat || KINDS_WITH_KNOWN_FIGHT_MS.contains(key.kind);
            w.str("kind", &key.kind.to_string())
                .int("ref_id", key.ref_id)
                .text("name", &key.name)
                .text("extra", &row.extra)
                .int("starts", row.starts)
                .int("ms", row.ms)
                .opt("fight_ms", known.then_some(row.fight_ms));
            w.end();
        }
    }
}
