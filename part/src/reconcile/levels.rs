use super::{Claim, Decision, Row, Status, decide, nothing, rules, with_rows};
use serde_json::{Value, json};
use std::cmp::Reverse;
use std::collections::BTreeMap;

fn counted(metric: &str, amount: i64) -> BTreeMap<String, i64> {
    BTreeMap::from([(metric.to_string(), amount)])
}

fn claimed(c: &Claim, metric: &str) -> i64 {
    c.whole()
        .and_then(|whole| whole.get(metric).copied())
        .unwrap_or(0)
}

pub(super) fn kills(
    claims: &[Claim],
    rows: &[Row],
    members: &[usize],
    decided: &mut [Option<Decision>],
) {
    for &i in members {
        let c = &claims[i];
        let (at_level, unleveled) = rules::kills_split(rows, rules::dim_level(c).unwrap_or(0));
        let assigned = at_level + unleveled;
        let remainder = (claimed(c, "kills") - assigned).max(0);
        let status = match (assigned, remainder) {
            (0, _) => Status::Counted,
            (_, 0) => Status::Superseded,
            _ => Status::Residual,
        };
        let evidence = json!({
            "rule": 3,
            "rows": {"n": assigned, "level": at_level, "unleveled": unleveled},
        });
        decided[i] = Some(decide(c, status, counted("kills", remainder), evidence));
    }
}

fn xp_order(claims: &[Claim], ids: &mut [usize]) {
    ids.sort_by(|&a, &b| {
        let key = |i: usize| (claimed(&claims[i], "xp"), claims[i].to, &claims[i].store);
        key(a).cmp(&key(b))
    });
}

fn time_order(claims: &[Claim], ids: &mut [usize]) {
    ids.sort_by(|&a, &b| (claims[a].to, &claims[a].store).cmp(&(claims[b].to, &claims[b].store)));
}

fn evidence_of(quest: i64, scarlet: usize, claims: usize, rows: Value) -> Value {
    json!({"rule": 2, "quest": quest, "scarlet": scarlet, "claims": claims, "rows": rows})
}

fn superseded_by(c: &Claim, evidence: Value, row: &Row) -> Decision {
    let rows = json!({"n": 1, "first": row.at, "last": row.at, "quest": row.key});
    let mut evidence = evidence;
    evidence["rows"] = rows;
    with_rows(nothing(c, Status::Superseded, evidence), &[row])
}

pub(super) fn quests(
    claims: &[Claim],
    rows: &[Row],
    members: &[usize],
    decided: &mut [Option<Decision>],
) {
    let mut by_quest: BTreeMap<i64, Vec<usize>> = BTreeMap::new();
    for &i in members {
        if let Some(quest) = rules::dim_int(&claims[i], "quest") {
            by_quest.entry(quest).or_default().push(i);
        }
    }
    let mut open: BTreeMap<usize, Value> = BTreeMap::new();
    for (quest, mut ids) in by_quest {
        let held = rules::turn_ins(rows, quest);
        let keep = ids.len().saturating_sub(held.len());
        let mut smallest = ids.clone();
        xp_order(claims, &mut smallest);
        time_order(claims, &mut ids);
        let mut taken = held.iter();
        for &i in &ids {
            let c = &claims[i];
            let evidence = evidence_of(quest, held.len(), ids.len(), json!({"n": 0}));
            if smallest[..keep].contains(&i) {
                open.insert(i, evidence.clone());
                decided[i] = Some(decide(
                    c,
                    Status::Counted,
                    counted("xp", claimed(c, "xp")),
                    evidence,
                ));
            } else if let Some(row) = taken.next() {
                decided[i] = Some(superseded_by(c, evidence, row));
            }
        }
    }
    let hidden = rules::hidden_turn_ins(rows);
    let mut order: Vec<usize> = open.keys().copied().collect();
    order.sort_by(|&a, &b| {
        let key = |i: usize| {
            (
                Reverse(claimed(&claims[i], "xp")),
                claims[i].to,
                &claims[i].store,
            )
        };
        key(a).cmp(&key(b))
    });
    let mut holder: Vec<Option<usize>> = vec![None; hidden.len()];
    for i in order {
        let mut seen = vec![false; hidden.len()];
        take_row(claims, &hidden, i, &mut holder, &mut seen);
    }
    for (row, held) in hidden.iter().zip(holder) {
        let Some(i) = held else { continue };
        let evidence = open.remove(&i).unwrap_or_default();
        decided[i] = Some(superseded_by(&claims[i], evidence, row));
    }
}

fn take_row(
    claims: &[Claim],
    hidden: &[&Row],
    i: usize,
    holder: &mut [Option<usize>],
    seen: &mut [bool],
) -> bool {
    let level = rules::dim_int(&claims[i], "level");
    for r in 0..hidden.len() {
        if seen[r] || !rules::hidden_fits(hidden[r], level) {
            continue;
        }
        seen[r] = true;
        let free = match holder[r] {
            None => true,
            Some(other) => take_row(claims, hidden, other, holder, seen),
        };
        if free {
            holder[r] = Some(i);
            return true;
        }
    }
    false
}
