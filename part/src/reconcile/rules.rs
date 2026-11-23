use super::{Claim, EPOCH_FLOOR, FUTURE_SLACK, Row, SCOPE_ACCOUNT};
use serde_json::{Value, json};
use std::collections::BTreeMap;
use std::ops::RangeInclusive;

pub(super) const WINDOW_LEVEL: &str = "level";
const WINDOW_SESSION: &str = "session";

pub(super) const OVERLAP_SLACK: i64 = 60;
pub(super) const ASSIGN_SLACK_MS: i64 = 60_000;
const SEAM_MS: i64 = 3_000;
const SERVER_CLOCK_MS: i64 = 86_400_000;
const EVENT_TOLERANCE_MS: i64 = 2_000;
const EVENT_SEARCH_MS: i64 = 60_000;
const FISHING: &str = "fishing";
const KILL: &str = "kill";
const XP: &str = "xp";
const RUN: &str = "run";
const FISHING_RANK: [&str; 2] = ["fl", "ff"];
const FISHING_METRICS: [&str; 4] = ["casts", "landed", "items", "unknown"];
const FISHING_REPORTED: [&str; 3] = ["casts", "landed", "items"];
const KILL_RANK: [&str; 1] = ["rxp"];
const KILL_METRICS: [&str; 1] = ["kills"];
const XP_METRICS: [&str; 1] = ["xp"];
const RUN_RANK: [&str; 1] = ["fdj"];
const RUN_METRICS: [&str; 1] = ["runs"];
const SERVER_CLOCK: [&str; 1] = ["ff"];
const LEVEL: &str = "level";
const QUEST: &str = "quest";
const INSTANCE: &str = "instance";
const LEVELS: RangeInclusive<i64> = 1..=100;
const MAX_KILLS: i64 = 100_000;
const MAX_QUEST_XP: i64 = 1_000_000;
pub(super) const RUN_BEFORE_MS: i64 = 600_000;
pub(super) const RUN_AFTER_MS: i64 = 5_000;
pub(super) const RUN_JOIN: i64 = 2_400;

pub(super) enum Event {
    Matched { at: i64, offset: i64 },
    Held,
    Unkeyed,
    Open,
}

#[derive(Clone, Copy, PartialEq, Eq)]
pub(super) enum Kind {
    Fishing,
    Kill,
    Xp,
    Run,
}

struct Shape {
    window: &'static str,
    dims: &'static [&'static str],
}

impl Kind {
    pub(super) fn of(name: &str) -> Option<Kind> {
        match name {
            FISHING => Some(Kind::Fishing),
            KILL => Some(Kind::Kill),
            XP => Some(Kind::Xp),
            RUN => Some(Kind::Run),
            _ => None,
        }
    }

    fn ranking(self) -> &'static [&'static str] {
        match self {
            Kind::Fishing => &FISHING_RANK,
            Kind::Kill | Kind::Xp => &KILL_RANK,
            Kind::Run => &RUN_RANK,
        }
    }

    fn metrics(self) -> &'static [&'static str] {
        match self {
            Kind::Fishing => &FISHING_METRICS,
            Kind::Kill => &KILL_METRICS,
            Kind::Xp => &XP_METRICS,
            Kind::Run => &RUN_METRICS,
        }
    }

    fn shape(self) -> Option<Shape> {
        match self {
            Kind::Fishing => None,
            Kind::Kill => Some(Shape {
                window: WINDOW_LEVEL,
                dims: &[LEVEL],
            }),
            Kind::Xp => Some(Shape {
                window: WINDOW_LEVEL,
                dims: &[LEVEL, QUEST],
            }),
            Kind::Run => Some(Shape {
                window: WINDOW_SESSION,
                dims: &[INSTANCE],
            }),
        }
    }
}

pub(super) fn ranking(kind: &str) -> Vec<&'static str> {
    Kind::of(kind).map_or_else(Vec::new, |k| k.ranking().to_vec())
}

pub(super) fn rank(kind: &str, origin: &str) -> Option<usize> {
    ranking(kind).iter().position(|o| *o == origin)
}

pub(super) fn server_clock(origin: &str) -> bool {
    SERVER_CLOCK.contains(&origin)
}

pub(super) fn dim_int(c: &Claim, key: &str) -> Option<i64> {
    let text = c.dims.get(key)?;
    text.parse::<i64>().ok().filter(|n| n.to_string() == *text)
}

pub(super) fn dim_level(c: &Claim) -> Option<i64> {
    matches!(Kind::of(&c.kind), Some(Kind::Kill | Kind::Xp))
        .then(|| dim_int(c, LEVEL))
        .flatten()
        .filter(|l| LEVELS.contains(l))
}

pub(super) fn unmapped(c: &Claim) -> Option<&'static str> {
    let Some(kind) = Kind::of(&c.kind) else {
        return Some("no Scarlet rows are read for the kind yet");
    };
    if rank(&c.kind, &c.origin).is_none() {
        return Some("no rule maps the origin on the kind");
    }
    if c.amounts
        .keys()
        .any(|k| !kind.metrics().contains(&k.as_str()))
    {
        return Some("an amount names a metric no rule maps");
    }
    let shape = kind.shape()?;
    if c.window != shape.window {
        return Some("the claim is not in the window the rule reads");
    }
    if !c
        .dims
        .keys()
        .map(String::as_str)
        .eq(shape.dims.iter().copied())
    {
        return Some("the dimensions are not the ones the rule reads");
    }
    if shape.dims.iter().any(|key| dim_int(c, key).is_none()) {
        return Some("a dimension the rule reads is not a whole number");
    }
    None
}

fn kind_bounds(c: &Claim, whole: &BTreeMap<String, i64>) -> Option<String> {
    let kind = Kind::of(&c.kind)?;
    if let Some(level) = dim_int(c, LEVEL).filter(|_| matches!(kind, Kind::Kill | Kind::Xp))
        && !LEVELS.contains(&level)
    {
        return Some(format!("level {level} is outside 1 to 100"));
    }
    let over = |metric: &str, most: i64| {
        whole
            .get(metric)
            .filter(|&&n| n > most)
            .map(|n| format!("{n} {metric} in one claim are more than {most}"))
    };
    match kind {
        Kind::Fishing => None,
        Kind::Kill => over("kills", MAX_KILLS),
        Kind::Xp => over("xp", MAX_QUEST_XP),
        Kind::Run => whole
            .get("runs")
            .filter(|&&n| n != 1)
            .map(|n| format!("a run claim holds {n} runs, not 1")),
    }
}

pub(super) fn out_of_bounds(c: &Claim, whole: &BTreeMap<String, i64>, now: i64) -> Option<String> {
    let casts = whole.get("casts").copied();
    let landed = whole.get("landed").copied();
    let unknown = whole.get("unknown").copied().unwrap_or(0);
    if c.from.is_some_and(|from| from > c.to) {
        return Some("the window ends before it starts".into());
    }
    if c.from.unwrap_or(c.to) < EPOCH_FLOOR {
        return Some("the window starts before the addon existed".into());
    }
    if c.to > now + FUTURE_SLACK {
        return Some("the window ends in the future".into());
    }
    if let (Some(casts), Some(landed)) = (casts, landed)
        && landed > casts
    {
        return Some(format!("landed {landed} is above casts {casts}"));
    }
    if let (Some(casts), Some(from)) = (casts, c.from)
        && (casts - unknown) * 2 > c.to - from + 2
    {
        return Some(format!(
            "{} casts that Scarlet would keep do not fit in {} seconds",
            casts - unknown,
            c.to - from
        ));
    }
    kind_bounds(c, whole)
}

pub(super) fn reported(kind: &str) -> &'static [&'static str] {
    match Kind::of(kind) {
        Some(Kind::Fishing) => &FISHING_REPORTED,
        Some(other) => other.metrics(),
        None => &[],
    }
}

pub(super) fn scarlet_term(kind: &str, rows: &[Row]) -> String {
    match Kind::of(kind) {
        Some(Kind::Fishing) => format!(
            "{}/{}/{}",
            rows.len(),
            rows.iter().filter(|r| r.looted).count(),
            rows.iter().map(|r| r.items).sum::<i64>()
        ),
        Some(Kind::Kill) => rows.iter().map(|r| r.count).sum::<i64>().to_string(),
        _ => rows.len().to_string(),
    }
}

pub(super) fn same_record(a: &Claim, b: &Claim) -> bool {
    b.scope != SCOPE_ACCOUNT
        && a.origin == b.origin
        && a.kind == b.kind
        && a.window == b.window
        && a.from == b.from
        && a.to == b.to
        && a.dims == b.dims
        && a.amounts == b.amounts
}

pub(super) fn rows_within(rows: impl Iterator<Item = i64>, from: i64, until: i64) -> usize {
    rows.filter(|&at| at >= from && at <= until).count()
}

pub(super) fn kills_split(rows: &[Row], level: i64) -> (i64, i64) {
    let held = |wanted: Option<i64>| {
        rows.iter()
            .filter(|r| r.level == wanted)
            .map(|r| r.count)
            .sum::<i64>()
    };
    (held(Some(level)), held(None))
}

pub(super) fn turn_ins(rows: &[Row], quest: i64) -> Vec<&Row> {
    let of = |events: bool| {
        let mut held: Vec<&Row> = rows
            .iter()
            .filter(|r| r.events == events && r.key == Some(quest))
            .collect();
        held.sort_by_key(|r| r.at);
        held
    };
    let (events, gains) = (of(true), of(false));
    if events.len() >= gains.len() {
        events
    } else {
        gains
    }
}

pub(super) fn hidden_turn_ins(rows: &[Row]) -> Vec<&Row> {
    let mut hidden: Vec<&Row> = rows
        .iter()
        .filter(|r| r.events && r.key.is_none())
        .collect();
    hidden.sort_by_key(|r| r.at);
    hidden
}

pub(super) fn runs_over<'a>(rows: &'a [Row], c: &Claim) -> Vec<&'a Row> {
    let (from, to) = (start(c) * 1000, c.to * 1000);
    let instance = dim_int(c, INSTANCE);
    let mut over: Vec<&Row> = rows
        .iter()
        .filter(|r| {
            let beside =
                r.key == instance && r.at <= to + RUN_AFTER_MS && r.end >= from - RUN_BEFORE_MS;
            beside || (r.at <= to && r.end >= from)
        })
        .collect();
    over.sort_by_key(|r| r.at);
    over
}

pub(super) fn beside(kind: &str, c: &Claim, rows: &[Row]) -> i64 {
    match Kind::of(kind) {
        Some(Kind::Fishing) => rows_within(
            rows.iter().map(|r| r.at),
            start(c) * 1000 - ASSIGN_SLACK_MS,
            c.to * 1000 + ASSIGN_SLACK_MS,
        ) as i64,
        Some(Kind::Kill) => dim_level(c).map_or(0, |level| {
            let (at_level, unleveled) = kills_split(rows, level);
            at_level + unleveled
        }),
        Some(Kind::Xp) => dim_int(c, QUEST).map_or(0, |quest| turn_ins(rows, quest).len() as i64),
        Some(Kind::Run) => runs_over(rows, c).len() as i64,
        None => 0,
    }
}

pub(super) fn covers(claimed_above: i64, claimed: i64) -> bool {
    i128::from(claimed_above) * 40 >= i128::from(claimed) * 39
}

fn overlaps(a: &Claim, b: &Claim) -> bool {
    a.to == b.to || (start(a) < b.to && start(b) < a.to)
}

fn start(c: &Claim) -> i64 {
    c.from.unwrap_or(c.to)
}

pub(super) fn span(c: &Claim) -> i64 {
    c.to - start(c)
}

pub(super) fn part_of(amount: i64, overlap: i64, span: i64) -> i64 {
    if span <= 0 || overlap >= span {
        return amount;
    }
    half_up(i128::from(amount) * i128::from(overlap), i128::from(span))
}

pub(super) fn overlap(a: &Claim, b: &Claim) -> i64 {
    a.to.min(b.to) - start(a).max(start(b))
}

fn one_record(a: &Claim, b: &Claim) -> bool {
    a.dims == b.dims
        && (overlaps(a, b)
            || (a.window == WINDOW_LEVEL && b.window == WINDOW_LEVEL && a.store == b.store))
}

pub(super) fn collapse(claims: &[Claim], dated: &[usize]) -> Vec<(usize, usize)> {
    let mut out = Vec::new();
    let mut origins: Vec<&str> = dated.iter().map(|&i| claims[i].origin.as_str()).collect();
    origins.sort_unstable();
    origins.dedup();
    for origin in origins {
        let mut remaining: Vec<usize> = dated
            .iter()
            .copied()
            .filter(|&i| claims[i].origin == origin)
            .collect();
        let order = |c: &Claim| (c.to, start(c), c.store.clone(), c.dims.clone());
        remaining.sort_by(|a, b| order(&claims[*b]).cmp(&order(&claims[*a])));
        while !remaining.is_empty() {
            let winner = remaining.remove(0);
            remaining.retain(|&i| {
                let same = one_record(&claims[winner], &claims[i]);
                if same {
                    out.push((i, winner));
                }
                !same
            });
        }
    }
    out
}

pub(super) fn previous(claims: &[Claim], members: &[usize], at: usize) -> Option<(usize, usize)> {
    let c = &claims[members[at]];
    members
        .iter()
        .enumerate()
        .filter(|&(p, &i)| {
            p != at
                && claims[i].amounts.contains_key("casts")
                && claims[i].to <= start(c)
                && start(c) - claims[i].to <= OVERLAP_SLACK
        })
        .max_by_key(|&(_, &i)| (claims[i].to, start(&claims[i])))
        .map(|(p, &i)| (p, i))
}

pub(super) fn assign(rows: &[Row], windows: &[(i64, i64)]) -> Vec<Vec<usize>> {
    let mut out = vec![Vec::new(); windows.len()];
    for (r, row) in rows.iter().enumerate() {
        let after = windows.partition_point(|(from, _)| from * 1000 <= row.at);
        let mut best: Option<(i64, usize)> = None;
        let candidates = [
            after.checked_sub(1),
            (after < windows.len()).then_some(after),
        ];
        for w in candidates.into_iter().flatten() {
            let (lo, hi) = (windows[w].0 * 1000, windows[w].1 * 1000);
            let distance = if row.at >= lo && row.at <= hi {
                0
            } else {
                (row.at - lo).abs().min((row.at - hi).abs())
            };
            if distance > ASSIGN_SLACK_MS {
                continue;
            }
            match best {
                Some((d, _)) if distance > d => {}
                _ => best = Some((distance, w)),
            }
        }
        if let Some((_, w)) = best {
            out[w].push(r);
        }
    }
    out
}

fn caps(claim: &Claim) -> (Option<i64>, Option<i64>) {
    let get = |key: &str| claim.amounts.get(key).map(|v| *v as i64);
    let casts = get("casts").map(|casts| casts - get("unknown").unwrap_or(0));
    (casts, get("landed"))
}

fn gap(held: i64, cap: Option<i64>) -> (i64, i64) {
    cap.map_or((0, 0), |cap| ((held - cap).max(0), (cap - held).max(0)))
}

fn mismatch(first: (i64, Option<i64>), second: (i64, Option<i64>)) -> i64 {
    let (over_first, under_first) = gap(first.0, first.1);
    let (over_second, under_second) = gap(second.0, second.1);
    over_first.min(under_second) + over_second.min(under_first)
}

fn imbalance(rows: &[Row], earlier: &Claim, later: &Claim, a: &[usize], b: &[usize]) -> i64 {
    let looted = |held: &[usize]| held.iter().filter(|&&r| rows[r].looted).count() as i64;
    let (casts_a, landed_a) = caps(earlier);
    let (casts_b, landed_b) = caps(later);
    mismatch((a.len() as i64, casts_a), (b.len() as i64, casts_b))
        + mismatch((looted(a), landed_a), (looted(b), landed_b))
}

pub(super) fn settle_seams(
    rows: &[Row],
    claims: &[Claim],
    members: &[usize],
    assigned: &mut [Vec<usize>],
) {
    for b in 1..members.len() {
        let a = b - 1;
        let (earlier, later) = (&claims[members[a]], &claims[members[b]]);
        let (before, after) = assigned.split_at_mut(b);
        let (a_rows, b_rows) = (&mut before[a], &mut after[0]);
        let opens = start(later) * 1000 - SEAM_MS;
        let closes = earlier.to * 1000 + SEAM_MS;
        loop {
            let now = imbalance(rows, earlier, later, a_rows, b_rows);
            if now == 0 {
                break;
            }
            let forward = a_rows
                .iter()
                .rev()
                .take_while(|&&r| rows[r].at >= opens)
                .count();
            let backward = b_rows.iter().take_while(|&&r| rows[r].at <= closes).count();
            let toward_later = (1..=forward).find(|&k| {
                let cut = a_rows.len() - k;
                let moved: Vec<usize> =
                    a_rows[cut..].iter().chain(b_rows.iter()).copied().collect();
                imbalance(rows, earlier, later, &a_rows[..cut], &moved) < now
            });
            if let Some(k) = toward_later {
                let cut = a_rows.len() - k;
                let moved = a_rows.split_off(cut);
                b_rows.splice(0..0, moved);
                continue;
            }
            let toward_earlier = (1..=backward).find(|&k| {
                let moved: Vec<usize> = a_rows.iter().chain(&b_rows[..k]).copied().collect();
                imbalance(rows, earlier, later, &moved, &b_rows[k..]) < now
            });
            let Some(k) = toward_earlier else { break };
            a_rows.extend(b_rows.drain(..k));
        }
    }
}

pub(super) fn near(rows: &[Row], c: &Claim) -> bool {
    let lo = start(c) * 1000 - SERVER_CLOCK_MS;
    let hi = c.to * 1000 + SERVER_CLOCK_MS;
    let i = rows.partition_point(|r| r.at < lo);
    rows.get(i).is_some_and(|r| r.at <= hi)
}

pub(super) fn rows_json(rows: &[&Row]) -> Value {
    json!({
        "n": rows.len(),
        "first": rows.iter().map(|r| r.at).min(),
        "last": rows.iter().map(|r| r.at).max(),
        "looted": rows.iter().filter(|r| r.looted).count(),
        "items": rows.iter().map(|r| r.items).sum::<i64>(),
    })
}

pub(super) fn counted_whole(whole: &BTreeMap<String, i64>) -> BTreeMap<String, i64> {
    whole
        .iter()
        .map(|(k, v)| (k.clone(), if k == "unknown" { 0 } else { *v }))
        .collect()
}

fn half_up(numerator: i128, denominator: i128) -> i64 {
    ((2 * numerator + denominator) / (2 * denominator)) as i64
}

pub(super) fn share(whole: &BTreeMap<String, i64>, rows: &[&Row]) -> BTreeMap<String, i64> {
    let get = |k: &str| i128::from(whole.get(k).copied().unwrap_or(0));
    let covered = rows.len() as i128;
    let looted = rows.iter().filter(|r| r.looted).count() as i128;
    let (casts, landed, items) = (get("casts"), get("landed"), get("items"));
    let comparable = casts - get("unknown");
    let landed_counted = (landed - looted).max(0);
    let mut out = BTreeMap::new();
    if whole.contains_key("casts") {
        let counted = if comparable > 0 {
            half_up(casts * (comparable - covered).max(0), comparable)
        } else {
            0
        };
        out.insert("casts".to_string(), counted);
    }
    if whole.contains_key("landed") {
        out.insert("landed".to_string(), landed_counted as i64);
    }
    if whole.contains_key("items") {
        let counted = if landed > 0 {
            half_up(items * landed_counted, landed)
        } else {
            0
        };
        out.insert("items".to_string(), counted);
    }
    if whole.contains_key("unknown") {
        out.insert("unknown".to_string(), 0);
    }
    out
}

fn item_of(c: &Claim) -> Option<i64> {
    c.dims.get("item").and_then(|v| v.parse().ok())
}

fn matching(rows: &[Row], item: i64, from: i64, to: i64) -> impl Iterator<Item = usize> + '_ {
    let first = rows.partition_point(|r| r.at < from);
    (first..rows.len())
        .take_while(move |&r| rows[r].at <= to)
        .filter(move |&r| rows[r].item_ids.contains(&item))
}

fn offset_of(claims: &[Claim], mine: &[usize], rows: &[Row]) -> i64 {
    let mut deltas: Vec<i64> = mine
        .iter()
        .filter_map(|&i| {
            let c = &claims[i];
            let at = c.to * 1000;
            matching(
                rows,
                item_of(c)?,
                at - EVENT_SEARCH_MS,
                at + EVENT_SEARCH_MS,
            )
            .map(|r| at - rows[r].at)
            .min_by_key(|d| d.abs())
        })
        .collect();
    deltas.sort_unstable();
    deltas
        .get(deltas.len().saturating_sub(1) / 2)
        .copied()
        .unwrap_or(0)
}

fn session_spans(rows: &[Row]) -> Vec<(i64, i64)> {
    let mut spans: BTreeMap<i64, (i64, i64)> = BTreeMap::new();
    for row in rows {
        let span = spans.entry(row.session).or_insert((row.at, row.at));
        span.0 = span.0.min(row.at);
        span.1 = span.1.max(row.at);
    }
    spans.into_values().collect()
}

pub(super) fn cancelled_in_session(
    rows: &[Row],
    c: &Claim,
    whole: &BTreeMap<String, i64>,
) -> Option<(i64, i64)> {
    let get = |k: &str| whole.get(k).copied().unwrap_or(0);
    let every_cast_unknown = whole.contains_key("casts") && get("casts") - get("unknown") <= 0;
    if !every_cast_unknown || get("landed") != 0 || get("items") != 0 {
        return None;
    }
    session_spans(rows)
        .into_iter()
        .find(|&(first, last)| first <= start(c) * 1000 && c.to * 1000 <= last)
}

pub(super) fn match_events(claims: &[Claim], events: &[usize], rows: &[Row]) -> Vec<Event> {
    let mut verdicts: Vec<Option<Event>> = (0..events.len()).map(|_| None).collect();
    let mut used = vec![false; rows.len()];
    let spans = session_spans(rows);
    let mut origins: Vec<&str> = events.iter().map(|&i| claims[i].origin.as_str()).collect();
    origins.sort_unstable();
    origins.dedup();
    for origin in origins {
        let mut mine: Vec<usize> = (0..events.len())
            .filter(|&e| claims[events[e]].origin == origin)
            .collect();
        mine.sort_by_key(|&e| {
            let c = &claims[events[e]];
            (c.to, c.store.clone(), c.dims.clone())
        });
        let indices: Vec<usize> = mine.iter().map(|&e| events[e]).collect();
        let offset = offset_of(claims, &indices, rows);
        for &e in &mine {
            let c = &claims[events[e]];
            let Some(item) = item_of(c) else {
                verdicts[e] = Some(Event::Unkeyed);
                continue;
            };
            let target = c.to * 1000 - offset;
            let hit = matching(
                rows,
                item,
                target - EVENT_TOLERANCE_MS,
                target + EVENT_TOLERANCE_MS,
            )
            .filter(|&r| !used[r])
            .min_by_key(|&r| ((rows[r].at - target).abs(), r));
            verdicts[e] = Some(match hit {
                Some(r) => {
                    used[r] = true;
                    Event::Matched {
                        at: rows[r].at,
                        offset,
                    }
                }
                None if spans.iter().any(|&(a, b)| {
                    target >= a - EVENT_TOLERANCE_MS && target <= b + EVENT_TOLERANCE_MS
                }) =>
                {
                    Event::Held
                }
                None => Event::Open,
            });
        }
    }
    verdicts
        .into_iter()
        .map(|v| v.unwrap_or(Event::Open))
        .collect()
}
