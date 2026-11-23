//! Imported history: gold balances, sales, purchases and other income and expense that the
//! companion read from other addons' saved variables (Scarlet FORMAT.md, "Imported history").
//! They are a third kind of record beside lifetime and observed numbers: parts of their own,
//! one per UTC day under the session key of that day, skipped by the session decoder and read
//! by `decode_imports` into rows of their own.

use crate::build::{Options, PART_MAGIC, Part, at_near, parse_header, part_id};
use crate::payload::Ev;
use crate::rows::Rows;
use std::collections::BTreeMap;

pub const GROUP: &str = "imports";
const DAY_MS: i64 = 86_400_000;

const SOURCE_MAX: usize = 24;

const VIA_MAX: usize = 64;

/// The line codes of the group, in FORMAT.md order.
pub const CODES: [&str; 5] = ["YG", "YS", "YB", "YM", "YU"];

/// One part for each UTC day that has lines. Its session key is the day's start in epoch seconds;
/// no real session starts at midnight sharp more than a hair's breadth of the time, and when one
/// does the decoder skips the group, so the two never mix.
pub fn build_parts(character: &str, mut events: Vec<Ev>, opts: &Options) -> Vec<Part> {
    if !opts.on.contains(GROUP) {
        return Vec::new();
    }
    events.sort_by_key(|e| e.ms);
    let mut days: BTreeMap<i64, Vec<&Ev>> = BTreeMap::new();
    for ev in &events {
        if CODES.contains(&ev.code()) {
            days.entry(ev.ms.div_euclid(DAY_MS)).or_default().push(ev);
        }
    }
    days.into_iter()
        .map(|(day, lines)| {
            let session = day * 86_400;
            let mut text = format!("{PART_MAGIC}\t1\t{character}\t{session}\t{GROUP}\n");
            let mut prev = session * 1000;
            for ev in &lines {
                text.push_str(&(ev.ms - prev).to_string());
                text.push('\t');
                text.push_str(&ev.text);
                text.push('\n');
                prev = ev.ms;
            }
            Part {
                id: part_id(&text),
                session,
                group: GROUP.to_string(),
                lines: lines.len(),
                text,
            }
        })
        .collect()
}

/// Rows of table `imported` from the imports parts among `parts` (other groups are ignored), one
/// per line: `kind`, `at` (epoch ms), `source`, `via`, `item_id`, `quantity`, `copper`. A `YU` line
/// is kind `expired` or `cancel`, with the stack size in `via` and no copper; one whose kind is
/// neither `e` nor `x` is skipped. A line whose time leaves the window the columns keep, whose code
/// is none of the group's is skipped before its row starts, and an id or count wider than the
/// signed 32-bit column holds is written as null, never fatal: one bad line costs its row, not the day.
/// Returns the number of rows.
pub fn decode_imports(parts: &[&str], out: &mut String) -> Result<usize, String> {
    let mut rows = Rows::new(out);
    for text in parts {
        let (_, session, group) =
            parse_header(text).ok_or_else(|| "a part has no valid header".to_string())?;
        if group != GROUP {
            continue;
        }
        let mut t = session * 1000;
        for line in text.split('\n').skip(1).filter(|l| !l.is_empty()) {
            let mut f = line.split('\t');
            let Some(dt) = f.next().and_then(|d| d.parse::<i64>().ok()) else {
                continue;
            };
            let Some(next) = t.checked_add(dt).filter(|t| at_near(session * 1000, *t)) else {
                continue;
            };
            t = next;
            let code = f.next().unwrap_or("");
            if !CODES.contains(&code) {
                continue;
            }
            let f: Vec<&str> = f.collect();
            let int = |i: usize| f.get(i).and_then(|v| v.parse::<i64>().ok());
            let count = |i: usize| int(i).filter(|v| (0..=i64::from(i32::MAX)).contains(v));
            let clip = |i: usize, max: usize| {
                f.get(i)
                    .map_or(String::new(), |s| s.chars().take(max).collect())
            };
            let source = clip(0, SOURCE_MAX);
            let returned = match f.get(1).copied() {
                Some("e") => "expired",
                Some("x") => "cancel",
                _ => "",
            };
            if code == "YU" && returned.is_empty() {
                continue;
            }
            rows.begin("imported");
            match code {
                "YG" => {
                    rows.str("kind", "gold")
                        .int("at", t)
                        .str("source", &source)
                        .null("via")
                        .null("item_id")
                        .null("quantity")
                        .int("copper", int(1).unwrap_or(0));
                }
                "YS" | "YB" => {
                    let via = clip(1, VIA_MAX);
                    rows.str("kind", if code == "YS" { "sale" } else { "buy" })
                        .int("at", t)
                        .str("source", &source)
                        .text("via", &via)
                        .opt("item_id", count(2))
                        .opt("quantity", count(3))
                        .int("copper", int(4).unwrap_or(0));
                }
                "YM" => {
                    let via = clip(1, VIA_MAX);
                    rows.str("kind", "other")
                        .int("at", t)
                        .str("source", &source)
                        .text("via", &via)
                        .null("item_id")
                        .null("quantity")
                        .int("copper", int(2).unwrap_or(0));
                }
                "YU" => {
                    let via = clip(4, VIA_MAX);
                    rows.str("kind", returned)
                        .int("at", t)
                        .str("source", &source)
                        .text("via", &via)
                        .opt("item_id", count(2))
                        .opt("quantity", count(3))
                        .int("copper", 0);
                }
                _ => {}
            }
            rows.end();
        }
    }
    Ok(rows.count)
}
