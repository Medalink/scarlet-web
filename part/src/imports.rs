//! Imported history: gold balances, sales, purchases and other income and expense that the
//! companion read from other addons' saved variables (Scarlet FORMAT.md, "Imported history").
//! They are a third kind of record beside lifetime and observed numbers: parts of their own,
//! one per UTC day under the session key of that day, skipped by the session decoder and read
//! by `decode_imports` into rows of their own.

use crate::build::{Options, PART_MAGIC, Part, parse_header, part_id};
use crate::payload::Ev;
use crate::rows::Rows;
use std::collections::BTreeMap;

pub const GROUP: &str = "imports";
const DAY_MS: i64 = 86_400_000;

/// The line codes of the group, in FORMAT.md order.
pub const CODES: [&str; 4] = ["YG", "YS", "YB", "YM"];

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
/// per line: `kind`, `at` (epoch ms), `source`, `via`, `item_id`, `quantity`, `copper`.
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
            let dt: i64 = f
                .next()
                .and_then(|d| d.parse().ok())
                .ok_or("bad line in an imports part")?;
            t += dt;
            let code = f.next().unwrap_or("");
            let f: Vec<&str> = f.collect();
            let int = |i: usize| f.get(i).and_then(|v| v.parse::<i64>().ok());
            let source = f.first().copied().unwrap_or("");
            rows.begin("imported");
            match code {
                "YG" => {
                    rows.str("kind", "gold")
                        .int("at", t)
                        .str("source", source)
                        .null("via")
                        .null("item_id")
                        .null("quantity")
                        .int("copper", int(1).unwrap_or(0));
                }
                "YS" | "YB" => {
                    rows.str("kind", if code == "YS" { "sale" } else { "buy" })
                        .int("at", t)
                        .str("source", source)
                        .text("via", f.get(1).copied().unwrap_or(""))
                        .opt("item_id", int(2))
                        .opt("quantity", int(3))
                        .int("copper", int(4).unwrap_or(0));
                }
                "YM" => {
                    rows.str("kind", "other")
                        .int("at", t)
                        .str("source", source)
                        .text("via", f.get(1).copied().unwrap_or(""))
                        .null("item_id")
                        .null("quantity")
                        .int("copper", int(2).unwrap_or(0));
                }
                _ => return Err(format!("unknown imports code {code}")),
            }
            rows.end();
        }
    }
    Ok(rows.count)
}
