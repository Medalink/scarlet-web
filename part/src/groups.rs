//! `check-groups` and `groups --json`.

use crate::tables::{Groups, raw_codes};
use serde_json::{Value, json};
use std::collections::{BTreeMap, BTreeSet};

/// The codes of the Codes table in FORMAT.md, in file order.
pub fn format_codes(format_md: &str) -> Vec<String> {
    let mut out = Vec::new();
    let mut inside = false;
    for line in format_md.lines() {
        if line.starts_with("## ") {
            inside = line.trim_end() == "## Codes";
            continue;
        }
        if !inside {
            continue;
        }
        let Some(rest) = line.strip_prefix("| `") else {
            continue;
        };
        let Some((code, _)) = rest.split_once('`') else {
            continue;
        };
        if !code.is_empty()
            && code.bytes().all(|b| b.is_ascii_uppercase())
            && !out.iter().any(|c| c == code)
        {
            out.push(code.to_string());
        }
    }
    out
}

/// Problems between groups.tsv and FORMAT.md; empty when they agree.
pub fn check(format_md: &str) -> Vec<String> {
    let groups = Groups::get();
    let codes = format_codes(format_md);
    let known: BTreeSet<&str> = codes.iter().map(String::as_str).collect();
    let mut problems = Vec::new();
    if codes.is_empty() {
        problems.push("FORMAT.md has no Codes table".to_string());
    }
    let mut seen: BTreeMap<&str, Vec<&str>> = BTreeMap::new();
    for g in &groups.list {
        for c in g.codes.iter().filter(|c| *c != "*") {
            seen.entry(c).or_default().push(&g.key);
        }
    }
    for c in &codes {
        if groups.owner_only.contains(c.as_str()) {
            if let Some(g) = seen.get(c.as_str()) {
                problems.push(format!(
                    "owner-only code {c} is also in group {}",
                    g.join(", ")
                ));
            }
            continue;
        }
        match seen.get(c.as_str()) {
            None => problems.push(format!("code {c} is in no group")),
            Some(g) if g.len() > 1 => {
                problems.push(format!(
                    "code {c} is in {} groups: {}",
                    g.len(),
                    g.join(", ")
                ));
            }
            _ => {}
        }
    }
    for (c, g) in &seen {
        if !known.contains(c) {
            problems.push(format!(
                "group {} lists code {c}, which FORMAT.md does not have",
                g.join(", ")
            ));
        }
    }
    for c in &groups.owner_only {
        if !known.contains(c.as_str()) {
            problems.push(format!("owner-only code {c} is not in FORMAT.md"));
        }
    }
    let (names, players) = raw_codes();
    for (table, set) in [("name-refs.tsv", names), ("player-fields.tsv", players)] {
        for c in set {
            if !known.contains(c.as_str()) {
                problems.push(format!(
                    "{table} lists code {c}, which FORMAT.md does not have"
                ));
            }
        }
    }
    problems
}

pub fn json() -> Value {
    let groups = Groups::get();
    Value::Array(
        groups
            .list
            .iter()
            .map(|g| {
                json!({
                    "key": g.key,
                    "label": g.label,
                    "codes": g.codes,
                    "switchable": g.switchable,
                })
            })
            .collect(),
    )
}
