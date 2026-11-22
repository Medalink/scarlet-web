//! The auras on the character and the casts of the last seconds: what the class tallies and item flows look back at.

use crate::class::{Now, TRACKED_MAX};
use crate::sets::Set;
use crate::spans::SpanId;
use std::collections::{BTreeMap, VecDeque};

const RECENT_KEPT_MS: i64 = 10_000;
const KEEP_MAX: usize = TRACKED_MAX;
const A0_AFTER_A1_REPLACES_MS: i64 = 2_000;

/// Who cast an aura, as the `A 1` line's `by` says: `1` the character or its pet, `0` someone else, empty unknown.
#[derive(Clone, Copy, PartialEq, Eq)]
pub enum By {
    Mine,
    Others,
    Unknown,
}

impl By {
    pub fn read(field: &str) -> By {
        match field {
            "1" => By::Mine,
            "0" => By::Others,
            _ => By::Unknown,
        }
    }

    /// An aura that another player is not known to have cast: what `shift_*` and `seal_fate` follow.
    pub fn is_own(self) -> bool {
        self != By::Others
    }
}

pub struct Inst {
    pub id: u64,
    pub spell: i64,
    pub gain_at: i64,
    pub helpful: Option<bool>,
    pub by: By,
    pub dur_ms: i64,
    pub initial: bool,
    pub span: Option<SpanId>,
    pub opened: bool,
    refreshed_at: Option<i64>,
    carried_ms: i64,
}

pub struct End {
    pub id: u64,
    pub spell: i64,
    pub at: i64,
    pub helpful: Option<bool>,
    pub by: By,
    pub gain_at: i64,
    pub opened: bool,
}

pub enum Loss {
    Carried,
    Ended {
        inst: Inst,
        ms: i64,
    },
    Unseen {
        helpful: Option<bool>,
        by: By,
        ms: i64,
    },
}

#[derive(Default)]
pub struct Auras {
    pub on: Vec<Inst>,
    ended: VecDeque<End>,
    kinds: BTreeMap<i64, (Option<bool>, By)>,
    last_end: BTreeMap<i64, i64>,
    next: u64,
}

impl Auras {
    /// A new instance, or `None` when the spell is already on (the client lists every aura again after a loading
    /// screen and on a refresh, and that carries on the aura already open) or when `TRACKED_MAX` auras are on.
    pub fn gain(
        &mut self,
        now: &Now,
        spell: i64,
        (helpful, by): (Option<bool>, By),
        dur_ms: i64,
        initial: bool,
    ) -> Option<u64> {
        self.kinds.insert(spell, (helpful, by));
        if let Some(open) = self.on.iter_mut().find(|i| i.spell == spell) {
            open.refreshed_at = Some(now.at);
            return None;
        }
        if self.on.len() >= TRACKED_MAX {
            return None;
        }
        self.next += 1;
        self.on.push(Inst {
            id: self.next,
            spell,
            gain_at: if initial { now.start } else { now.at },
            helpful,
            by,
            dur_ms,
            initial,
            span: None,
            opened: false,
            refreshed_at: None,
            carried_ms: 0,
        });
        Some(self.next)
    }

    pub fn get_mut(&mut self, id: u64) -> Option<&mut Inst> {
        self.on.iter_mut().find(|i| i.id == id)
    }

    pub fn get(&self, id: u64) -> Option<&Inst> {
        self.on.iter().find(|i| i.id == id)
    }

    /// An aura ended after `ms` milliseconds held, by the line's own seconds.
    pub fn lose(&mut self, now: &Now, spell: i64, ms: i64) -> Loss {
        let Some(at) = self.on.iter().position(|i| i.spell == spell) else {
            let (helpful, by) = self
                .kinds
                .get(&spell)
                .copied()
                .unwrap_or((None, By::Unknown));
            let ms = self
                .last_end
                .get(&spell)
                .map_or(ms, |ended| ms.min((now.at - ended).max(0)));
            self.last_end.insert(spell, now.at);
            self.keep(End {
                id: 0,
                spell,
                at: now.at,
                helpful,
                by,
                gain_at: now.at - ms,
                opened: false,
            });
            return Loss::Unseen { helpful, by, ms };
        };
        let open = &mut self.on[at];
        if open
            .refreshed_at
            .is_some_and(|r| now.at - r <= A0_AFTER_A1_REPLACES_MS)
        {
            open.refreshed_at = None;
            open.carried_ms = open.carried_ms.saturating_add(ms);
            return Loss::Carried;
        }
        let inst = self.on.remove(at);
        self.last_end.insert(spell, now.at);
        self.keep(End {
            id: inst.id,
            spell,
            at: now.at,
            helpful: inst.helpful,
            by: inst.by,
            gain_at: inst.gain_at,
            opened: inst.opened,
        });
        let reported = inst.carried_ms.saturating_add(ms);
        let total = match inst.refreshed_at {
            Some(_) => reported.max(now.at.saturating_sub(inst.gain_at)),
            None => reported,
        };
        Loss::Ended { inst, ms: total }
    }

    fn keep(&mut self, end: End) {
        let cutoff = end.at - RECENT_KEPT_MS;
        self.ended.push_back(end);
        while self.ended.len() > KEEP_MAX || self.ended.front().is_some_and(|e| e.at < cutoff) {
            self.ended.pop_front();
        }
    }

    pub fn ended_since(&self, from: i64) -> impl Iterator<Item = &End> {
        self.ended.iter().rev().take_while(move |e| e.at >= from)
    }

    pub fn ended_of(&self, set: Set, from: i64) -> Option<&End> {
        self.ended_since(from)
            .find(|e| set.has(e.spell) && e.by.is_own())
    }

    pub fn mark_ended_opened(&mut self, id: u64) {
        if let Some(end) = self.ended.iter_mut().find(|e| e.id == id) {
            end.opened = true;
        }
    }
}

pub struct Cast {
    pub at: i64,
    pub spell: i64,
    pub channel: bool,
}

/// The casts and channel ends of the last seconds.
#[derive(Default)]
pub struct Recent {
    items: VecDeque<Cast>,
}

impl Recent {
    pub fn push(&mut self, at: i64, spell: i64, channel: bool) {
        self.items.push_back(Cast { at, spell, channel });
        let cutoff = at - RECENT_KEPT_MS;
        while self.items.len() > KEEP_MAX || self.items.front().is_some_and(|c| c.at < cutoff) {
            self.items.pop_front();
        }
    }

    /// The casts (`C` lines) from `from` on, oldest first.
    pub fn casts_since(&self, from: i64) -> impl Iterator<Item = &Cast> {
        self.items
            .iter()
            .skip_while(move |c| c.at < from)
            .filter(|c| !c.channel)
    }

    /// The latest cast from `from` on; channel ends count when `channels` is set.
    pub fn latest(&self, from: i64, channels: bool) -> Option<&Cast> {
        self.items
            .iter()
            .rev()
            .take_while(|c| c.at >= from)
            .find(|c| channels || !c.channel)
    }
}
