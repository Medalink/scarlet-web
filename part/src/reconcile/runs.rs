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
        for i in ids {
            let joins = chain.last().is_some_and(|&last| {
                claims[i].from.unwrap_or(claims[i].to) - claims[last].to < rules::RUN_JOIN
            });
            if !joins && !chain.is_empty() {
                out.push(std::mem::take(&mut chain));
            }
            chain.push(i);
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
    decided: &mut [Option<Decision>],
) {
    for chain in chains(claims, members) {
        let over = overlapping(claims, rows, &chain);
        if !over.is_empty() {
            for &i in &chain {
                let evidence = json!({"rule": 3, "rows": rules::rows_json(&over)});
                decided[i] = Some(with_rows(
                    nothing(&claims[i], Status::Superseded, evidence),
                    &over,
                ));
            }
            continue;
        }
        let first = &claims[chain[0]];
        decided[chain[0]] = Some(decide(
            first,
            Status::Counted,
            BTreeMap::from([("runs".to_string(), 1)]),
            json!({"rule": 3, "rows": {"n": 0}}),
        ));
        for &i in &chain[1..] {
            let evidence = json!({"rule": 5, "same_origin": true, "with": first.natural()});
            decided[i] = Some(nothing(&claims[i], Status::Superseded, evidence));
        }
    }
}
