//! Building parts from a character's payload lines (docs/DESIGN.md, Parts).
//!
//! The same input always gives byte-identical parts: nothing here depends on
//! hash order, the clock or the machine.

use crate::payload::Ev;
use crate::pseudo::{keeps_name, pseudonym};
use crate::tables::{Groups, NameRefs, PlayerFields, leading_id};
use sha2::{Digest, Sha256};
use std::collections::{BTreeMap, BTreeSet, HashSet};

pub const PART_MAGIC: &str = "scarlet-part";

/// Groups that are on, and the install's name key.
pub struct Options {
    pub on: BTreeSet<String>,
    pub name_key: [u8; 32],
}

impl Options {
    /// Every switchable group on.
    pub fn all(name_key: [u8; 32]) -> Options {
        let on = Groups::get()
            .list
            .iter()
            .filter(|g| g.switchable)
            .map(|g| g.key.clone())
            .collect();
        Options { on, name_key }
    }

    fn is_on(&self, key: &str) -> bool {
        key == "core" || key == "names" || self.on.contains(key)
    }
}

#[derive(Clone, Debug)]
pub struct Part {
    pub id: [u8; 32],
    /// The session's key: its start in epoch seconds.
    pub session: i64,
    pub group: String,
    pub lines: usize,
    pub text: String,
}

#[derive(Default, Debug)]
pub struct Stats {
    pub sessions_closed: usize,
    pub sessions_open: usize,
    pub lines_in: usize,
    pub lines_sent: usize,
    /// Lines whose code is in no group: never sent.
    pub dropped_unknown: usize,
    /// Lines of a group that is off.
    pub dropped_off: usize,
    /// Lines after the last session's `ES`: they wait for the next session.
    pub held_back: usize,
    pub pseudonyms: usize,
}

pub struct Built {
    pub parts: Vec<Part>,
    pub stats: Stats,
}

struct Sess {
    key: i64,
    start_ms: i64,
    lines: Vec<Ev>,
    ended: bool,
}

pub fn part_id(text: &str) -> [u8; 32] {
    Sha256::digest(text.as_bytes()).into()
}

pub const MIN_AT_MS: i64 = -210_866_803_200_000;
pub const MAX_AT_MS: i64 = 9_224_318_015_999_000;

pub fn at_in_range(ms: i64) -> bool {
    (MIN_AT_MS..=MAX_AT_MS).contains(&ms)
}

/// `(character, session, group)` from a part's header line.
pub fn parse_header(text: &str) -> Option<(&str, i64, &str)> {
    let head = text.split('\n').next()?;
    let mut f = head.split('\t');
    if f.next()? != PART_MAGIC || f.next()? != "1" {
        return None;
    }
    let character = f.next()?;
    let session: i64 = f.next()?.parse().ok()?;
    if !at_in_range(session.checked_mul(1000)?) {
        return None;
    }
    let group = f.next()?;
    if f.next().is_some() || character.is_empty() || group.is_empty() {
        return None;
    }
    Some((character, session, group))
}

/// Split a character's lines (in recorded order) into sessions.
///
/// The lines are put in time order first (a stable sort, as the companion
/// does). A session runs from its `S` line to its `ES`, or to the line before
/// the next `S`. Lines between an `ES` and the next `S` are the next session's
/// login lines and join it. Lines after the last `ES` are held back until a
/// later `S` takes them, so a closed session never changes. With no `S` at all
/// the lines form one session, keyed by the first line's time, that never
/// closes. Returns the sessions and the number of lines held back.
fn split_sessions(mut main: Vec<Ev>) -> (Vec<Sess>, usize) {
    main.sort_by_key(|e| e.ms);
    let mut sessions: Vec<Sess> = Vec::new();
    let mut used: HashSet<i64> = HashSet::new();
    let mut pending: Vec<Ev> = Vec::new();
    let mut open = false;
    for ev in main {
        let code = ev.code();
        let (is_s, is_es) = (code == "S", code == "ES");
        if is_s {
            let mut key = ev.ms.div_euclid(1000);
            while !used.insert(key) {
                key += 1;
            }
            let mut lines = std::mem::take(&mut pending);
            let start_ms = ev.ms;
            lines.push(ev);
            sessions.push(Sess {
                key,
                start_ms,
                lines,
                ended: false,
            });
            open = true;
        } else if open {
            if let Some(s) = sessions.last_mut() {
                s.lines.push(ev);
                if is_es {
                    s.ended = true;
                    open = false;
                }
            }
        } else {
            pending.push(ev);
        }
    }
    let mut held = 0;
    if !pending.is_empty() {
        if sessions.is_empty() {
            let ms = pending[0].ms;
            sessions.push(Sess {
                key: ms.div_euclid(1000),
                start_ms: ms,
                lines: pending,
                ended: false,
            });
        } else {
            held = pending.len();
        }
    }
    (sessions, held)
}

/// Build every part of every closed session. `main` and `addon` are the
/// character's lines from the main and the addon streams.
pub fn build_parts(character: &str, main: Vec<Ev>, mut addon: Vec<Ev>, opts: &Options) -> Built {
    let groups = Groups::get();
    let mut stats = Stats {
        lines_in: main.len() + addon.len(),
        ..Stats::default()
    };
    let (sessions, held) = split_sessions(main);
    stats.held_back = held;
    addon.sort_by_key(|e| e.ms);
    let mut addon_for: Vec<Vec<&Ev>> = vec![Vec::new(); sessions.len()];
    if opts.is_on("addons") && !sessions.is_empty() {
        for ev in &addon {
            let i = sessions
                .partition_point(|s| s.start_ms <= ev.ms)
                .saturating_sub(1);
            addon_for[i].push(ev);
        }
    }
    let mut parts = Vec::new();
    let last = sessions.len().saturating_sub(1);
    for (i, sess) in sessions.iter().enumerate() {
        if !(sess.ended || i < last) {
            stats.sessions_open += 1;
            continue;
        }
        stats.sessions_closed += 1;
        build_session(
            character,
            sess,
            &addon_for[i],
            opts,
            groups,
            &mut stats,
            &mut parts,
        );
    }
    Built { parts, stats }
}

fn build_session(
    character: &str,
    sess: &Sess,
    addon: &[&Ev],
    opts: &Options,
    groups: &Groups,
    stats: &mut Stats,
    out: &mut Vec<Part>,
) {
    let refs = NameRefs::get();
    let players = PlayerFields::get();
    let names_idx = groups.index("names");
    let social_idx = groups.index("social");
    let addons_idx = groups.index("addons");
    let mut buf: Vec<Vec<(i64, String)>> = vec![Vec::new(); groups.list.len()];
    let mut referenced: HashSet<(u8, u64)> = HashSet::new();
    let mut pseudonyms: BTreeMap<String, String> = BTreeMap::new();
    let mut fields: Vec<&str> = Vec::new();

    for ev in &sess.lines {
        let mut it = ev.text.split('\t');
        let code = it.next().unwrap_or("");
        let Some(g) = groups.of_code(code) else {
            stats.dropped_unknown += 1;
            continue;
        };
        if !opts.is_on(groups.key(g)) {
            stats.dropped_off += 1;
            continue;
        }
        fields.clear();
        fields.extend(it);
        let mut text: Option<String> = None;
        if Some(g) != social_idx && Some(g) != names_idx {
            let rules = players.rules(code);
            if !rules.is_empty() {
                let mut replaced: Vec<String> = Vec::new();
                for rule in rules {
                    if rule.when.as_ref().is_some_and(|w| !w.holds(&fields)) {
                        continue;
                    }
                    let Some(name) = fields.get(rule.field) else {
                        continue;
                    };
                    if keeps_name(name, character) {
                        continue;
                    }
                    if replaced.is_empty() {
                        replaced = fields.iter().map(|f| f.to_string()).collect();
                    }
                    let p = pseudonym(&opts.name_key, name);
                    pseudonyms
                        .entry(p.clone())
                        .or_insert_with(|| name.to_string());
                    replaced[rule.field] = p;
                }
                if !replaced.is_empty() {
                    let mut t = String::with_capacity(ev.text.len());
                    t.push_str(code);
                    for f in &replaced {
                        t.push('\t');
                        t.push_str(f);
                    }
                    text = Some(t);
                }
            }
        }
        if Some(g) != names_idx {
            refs.collect(code, &fields, &mut |k, id| {
                referenced.insert((k, id));
            });
        }
        buf[g].push((ev.ms, text.unwrap_or_else(|| ev.text.clone())));
    }

    if let Some(n) = names_idx {
        let lines = std::mem::take(&mut buf[n]);
        buf[n] = lines
            .into_iter()
            .filter(|(_, text)| {
                let mut it = text.split('\t');
                let code = it.next().unwrap_or("");
                match (refs.def_kind(code), it.next().and_then(leading_id)) {
                    (Some(kind), Some(id)) => referenced.contains(&(kind, id)),
                    _ => false,
                }
            })
            .collect();
    }
    if let Some(a) = addons_idx {
        buf[a] = addon.iter().map(|e| (e.ms, e.text.clone())).collect();
    }

    let start = sess.key * 1000;
    for (g, lines) in buf.iter().enumerate() {
        let key = groups.key(g);
        let with_pn = Some(g) == social_idx && opts.is_on(key) && !pseudonyms.is_empty();
        if lines.is_empty() && !with_pn {
            continue;
        }
        let mut text = format!("{PART_MAGIC}\t1\t{character}\t{}\t{key}\n", sess.key);
        let mut count = 0;
        if with_pn {
            for (p, name) in &pseudonyms {
                text.push_str("0\tpn\t");
                text.push_str(p);
                text.push('\t');
                text.push_str(name);
                text.push('\n');
                count += 1;
            }
            stats.pseudonyms += pseudonyms.len();
        }
        let mut prev = start;
        for (ms, line) in lines {
            text.push_str(&(ms - prev).to_string());
            text.push('\t');
            text.push_str(line);
            text.push('\n');
            prev = *ms;
            count += 1;
        }
        stats.lines_sent += lines.len();
        out.push(Part {
            id: part_id(&text),
            session: sess.key,
            group: key.to_string(),
            lines: count,
            text,
        });
    }
}
