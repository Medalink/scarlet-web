use super::{Claim, EPOCH_FLOOR, FUTURE_SLACK, Row, SCOPE_ACCOUNT};
use serde_json::{Value, json};
use std::collections::BTreeMap;

pub(super) const OVERLAP_SLACK: i64 = 60;
pub(super) const ASSIGN_SLACK_MS: i64 = 60_000;
const SEAM_MS: i64 = 3_000;
const SERVER_CLOCK_MS: i64 = 86_400_000;
const EVENT_TOLERANCE_MS: i64 = 2_000;
const EVENT_SEARCH_MS: i64 = 60_000;
const FISHING: &str = "fishing";
const FISHING_RANK: [&str; 2] = ["fl", "ff"];
const FISHING_METRICS: [&str; 4] = ["casts", "landed", "items", "unknown"];
const SERVER_CLOCK: [&str; 1] = ["ff"];

pub(super) enum Event {
    Matched { at: i64, offset: i64 },
    Held,
    Unkeyed,
    Open,
}

pub(super) fn ranking(kind: &str) -> Vec<&'static str> {
    match kind {
        FISHING => FISHING_RANK.to_vec(),
        _ => Vec::new(),
    }
}

pub(super) fn rank(kind: &str, origin: &str) -> Option<usize> {
    ranking(kind).iter().position(|o| *o == origin)
}

pub(super) fn server_clock(origin: &str) -> bool {
    SERVER_CLOCK.contains(&origin)
}

pub(super) fn unmapped(c: &Claim) -> Option<&'static str> {
    if c.kind != FISHING {
        return Some("no Scarlet rows are read for the kind yet");
    }
    if rank(&c.kind, &c.origin).is_none() {
        return Some("no rule maps the origin on the kind");
    }
    if c.amounts
        .keys()
        .any(|k| !FISHING_METRICS.contains(&k.as_str()))
    {
        return Some("an amount names a metric no rule maps");
    }
    None
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
    None
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

pub(super) fn holds_rows(mut rows: impl Iterator<Item = i64>, from: i64, until: i64) -> bool {
    rows.any(|at| at >= from && at <= until)
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

pub(super) fn overlap(a: &Claim, b: &Claim) -> i64 {
    a.to.min(b.to) - start(a).max(start(b))
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
                let overlapped = overlaps(&claims[winner], &claims[i]);
                if overlapped {
                    out.push((i, winner));
                }
                !overlapped
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

fn excess(rows: &[Row], claim: &Claim, assigned: &[usize]) -> Option<i64> {
    let landed = *claim.amounts.get("landed")? as i64;
    let looted = assigned.iter().filter(|&&r| rows[r].looted).count() as i64;
    Some(looted - landed)
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
        while let (Some(x), Some(y)) = (excess(rows, earlier, a_rows), excess(rows, later, b_rows))
        {
            if x > 0 && y < 0 {
                let near = a_rows
                    .iter()
                    .rev()
                    .take_while(|&&r| rows[r].at >= opens)
                    .count();
                let Some(at) = a_rows.iter().rev().take(near).position(|&r| rows[r].looted) else {
                    break;
                };
                let moved = a_rows.split_off(a_rows.len() - at - 1);
                b_rows.splice(0..0, moved);
            } else if y > 0 && x < 0 {
                let near = b_rows.iter().take_while(|&&r| rows[r].at <= closes).count();
                let Some(at) = b_rows.iter().take(near).position(|&r| rows[r].looted) else {
                    break;
                };
                a_rows.extend(b_rows.drain(..=at));
            } else {
                break;
            }
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
