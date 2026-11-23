use super::{Claim, Decision, Row, Status, decide, nothing, rules, with_rows};
use serde_json::json;
use std::collections::BTreeMap;

fn chains(claims: &[Claim], members: &[usize]) -> Vec<Vec<usize>> {
    let mut by_instance: BTreeMap<i64, Vec<usize>> = BTreeMap::new();
    for &i in members {
        if let Some(instance) = rules::dim_int(&claims[i], "instance") {
            by_instance.entry(instance).or_default().push(i);
        }
    }
    let mut out = Vec::new();
    for (_, mut ids) in by_instance {
        ids.sort_by(|&a, &b| {
            let key = |i: usize| {
                (
                    claims[i].from.unwrap_or(claims[i].to),
                    claims[i].to,
                    &claims[i].store,
                )
            };
            key(a).cmp(&key(b))
        });
        let mut chain: Vec<usize> = Vec::new();
        let mut reach = i64::MIN;
        for i in ids {
            let joins = !chain.is_empty()
                && claims[i].from.unwrap_or(claims[i].to) - reach < rules::RUN_JOIN;
            if !joins && !chain.is_empty() {
                out.push(std::mem::take(&mut chain));
                reach = i64::MIN;
            }
            chain.push(i);
            reach = reach.max(claims[i].to);
        }
        out.push(chain);
    }
    out
}

fn overlapping<'a>(claims: &[Claim], rows: &'a [Row], chain: &[usize]) -> Vec<&'a Row> {
    let mut over: Vec<&Row> = Vec::new();
    for &i in chain {
        for row in rules::runs_over(rows, &claims[i]) {
            if !over.iter().any(|seen| std::ptr::eq(*seen, row)) {
                over.push(row);
            }
        }
    }
    over.sort_by_key(|r| r.at);
    over
}

pub(super) fn runs(
    claims: &[Claim],
    rows: &[Row],
    members: &[usize],
    twinned: &[usize],
    decided: &mut [Option<Decision>],
) {
    let all: Vec<usize> = members.iter().chain(twinned).copied().collect();
    for chain in chains(claims, &all) {
        let live: Vec<usize> = chain
            .iter()
            .copied()
            .filter(|i| members.contains(i))
            .collect();
        let Some(&first) = live.first() else { continue };
        let over = overlapping(claims, rows, &chain);
        if !over.is_empty() {
            for &i in &live {
                let evidence = json!({"rule": 3, "rows": rules::rows_json(&over)});
                decided[i] = Some(with_rows(
                    nothing(&claims[i], Status::Superseded, evidence),
                    &over,
                ));
            }
            continue;
        }
        let moved = chain.iter().copied().find(|i| twinned.contains(i));
        let head = moved.unwrap_or(first);
        if moved.is_none() {
            decided[first] = Some(decide(
                &claims[first],
                Status::Counted,
                BTreeMap::from([("runs".to_string(), 1)]),
                json!({"rule": 3, "rows": {"n": 0}}),
            ));
        }
        for &i in live.iter().filter(|&&i| i != head) {
            let evidence = json!({"rule": 5, "same_origin": true, "with": claims[head].natural()});
            decided[i] = Some(nothing(&claims[i], Status::Superseded, evidence));
        }
    }
}
