//! The Fishing Log: the records, sessions, bounties and leaderboards the companion read from that
//! addon's saved variables (Scarlet FORMAT.md, "The Fishing Log"), and the catches the addon stream
//! records from it (`fl C`, Scarlet ADDONS.md). The `J` lines travel in parts of group `fishing`,
//! one per UTC day under the session key of that day, as imports do. Other anglers' names become
//! pseudonyms by the rules of player-fields.tsv, and with `social` on each part carries its
//! pseudonyms as `pn` lines first, as a social part does.

use crate::build::{Options, PART_MAGIC, Part, parse_header, part_id};
use crate::payload::Ev;
use crate::pseudo::{keeps_name, pseudonym};
use crate::rows::Rows;
use crate::tables::PlayerFields;
use std::collections::BTreeMap;

pub const GROUP: &str = "fishing";
const ADDONS_GROUP: &str = "addons";
const SOCIAL_GROUP: &str = "social";
const DAY_MS: i64 = 86_400_000;

/// The table the decoded rows go to.
pub const TABLE: &str = "fishing";

/// The code a `fl C` catch of the addon stream is decoded under.
pub const CATCH_CODE: &str = "fl.C";

#[derive(Clone, Copy)]
enum Kind {
    Int,
    Decimal,
    Text,
    Bit,
    IntList,
    DecimalList,
}

use Kind::{Bit, Decimal, DecimalList, Int, IntList, Text};

const SPECS: &[(&str, &[(&str, Kind)])] = &[
    (
        "JP",
        &[
            ("xp", Int),
            ("season", Int),
            ("season_xp", Int),
            ("value", Int),
            ("specimen_item_id", Int),
            ("specimen_weight", Decimal),
            ("specimen_water", Text),
            ("specimen_at", Int),
        ],
    ),
    (
        "JR",
        &[
            ("spot_key", Text),
            ("item_id", Int),
            ("weight", Decimal),
            ("specimen", Bit),
        ],
    ),
    (
        "JJ",
        &[
            ("item_id", Int),
            ("count", Int),
            ("best", Decimal),
            ("best_specimen", Decimal),
            ("first_spot_key", Text),
            ("first_water", Text),
            ("map_id", Int),
            ("x", Int),
            ("y", Int),
        ],
    ),
    (
        "JZ",
        &[
            ("item_id", Int),
            ("zone", Text),
            ("count", Int),
            ("best", Decimal),
            ("best_specimen", Decimal),
            ("spot_key", Text),
        ],
    ),
    (
        "JH",
        &[
            ("zone", Text),
            ("subzone", Text),
            ("seconds", Int),
            ("count", Int),
            ("weight", Decimal),
            ("biggest", Decimal),
            ("biggest_item_id", Int),
            ("biggest_name", Text),
            ("specimens", Int),
            ("value", Int),
            ("solo", Bit),
            ("casts", Int),
            ("landed", Int),
            ("escaped", Int),
            ("early", Int),
            ("cancelled", Int),
            ("unknown", Int),
            ("records_all", Int),
            ("records_season", Int),
        ],
    ),
    (
        "JW",
        &[
            ("water", Text),
            ("seconds", Int),
            ("count", Int),
            ("weight", Decimal),
            ("biggest", Decimal),
            ("biggest_item_id", Int),
            ("biggest_name", Text),
            ("junk", Int),
            ("specimens", Int),
            ("value", Int),
        ],
    ),
    (
        "JB",
        &[
            ("period", Text),
            ("index", Int),
            ("score", Decimal),
            ("specimen", Bit),
            ("species", IntList),
            ("top", DecimalList),
        ],
    ),
    (
        "JX",
        &[
            ("period", Text),
            ("index", Int),
            ("place", Int),
            ("score", Decimal),
            ("xp", Int),
            ("entrants", Int),
        ],
    ),
    (
        "JY",
        &[
            ("period", Text),
            ("index", Int),
            ("rank", Int),
            ("player", Text),
            ("weight", Decimal),
        ],
    ),
    (
        "JF",
        &[
            ("item_id", Int),
            ("name", Text),
            ("count", Int),
            ("water", Text),
        ],
    ),
    (
        "JQ",
        &[("map_id", Int), ("x", Int), ("y", Int), ("count", Int)],
    ),
    (
        "JG",
        &[
            ("runs", Int),
            ("best", Int),
            ("cleared", Int),
            ("mode", Text),
            ("best_weight", Decimal),
        ],
    ),
    (
        "JK",
        &[
            ("spot_key", Text),
            ("scope", Text),
            ("season_id", Int),
            ("item_id", Int),
            ("weight", Decimal),
            ("holder", Text),
        ],
    ),
    (
        "JN",
        &[
            ("spot_key", Text),
            ("water", Text),
            ("map_id", Int),
            ("x", Int),
            ("y", Int),
            ("mine", Bit),
        ],
    ),
    ("JI", &[("item_id", Int), ("name", Text)]),
    (
        "JO",
        &[
            ("period", Text),
            ("format", Text),
            ("index", Int),
            ("player", Text),
            ("score", Decimal),
            ("specimen", Bit),
        ],
    ),
    (
        "JT",
        &[
            ("period", Text),
            ("index", Int),
            ("item_id", Int),
            ("zone", Text),
        ],
    ),
    (
        "JA",
        &[
            ("player", Text),
            ("guild", Text),
            ("xp", Int),
            ("version", Text),
            ("xp_at", Int),
        ],
    ),
    ("JE", &[("player", Text), ("faction", Text)]),
    (
        "JD",
        &[
            ("frenzy_key", Text),
            ("map_id", Int),
            ("x", Int),
            ("y", Int),
            ("player", Text),
            ("count", Int),
            ("mine", Bit),
        ],
    ),
    (
        "JU",
        &[
            ("name", Text),
            ("map_id", Int),
            ("x", Int),
            ("y", Int),
            ("mine", Bit),
            ("seen_by", Int),
        ],
    ),
    (
        "JV",
        &[
            ("zone", Text),
            ("map_id", Int),
            ("total", Int),
            ("item_id", Int),
            ("name", Text),
            ("count", Int),
            ("skill_low", Int),
            ("skill_high", Int),
            ("junk", Bit),
            ("pool", Bit),
        ],
    ),
    ("JC", &[("zone", Text), ("water", Text), ("count", Int)]),
    ("JM", &[("zone", Text), ("item_id", Int), ("count", Int)]),
    (
        "JS",
        &[
            ("map_id", Int),
            ("kind", Text),
            ("band", Int),
            ("landed", Int),
            ("escaped", Int),
        ],
    ),
    (
        "JL",
        &[
            ("map_id", Int),
            ("source", Text),
            ("skill_low", Int),
            ("skill_high", Int),
            ("count", Int),
        ],
    ),
    ("JCH", &[("player", Text), ("until", Int)]),
    (
        CATCH_CODE,
        &[
            ("item_id", Int),
            ("weight", Decimal),
            ("flags", Text),
            ("zone", Text),
            ("water", Text),
            ("map_id", Int),
            ("x", Int),
            ("y", Int),
            ("spot_key", Text),
        ],
    ),
];

fn spec(code: &str) -> Option<&'static [(&'static str, Kind)]> {
    SPECS.iter().find(|(c, _)| *c == code).map(|(_, f)| *f)
}

/// The `J` codes, in FORMAT.md order.
pub fn codes() -> impl Iterator<Item = &'static str> {
    SPECS.iter().map(|(c, _)| *c).filter(|c| *c != CATCH_CODE)
}

/// One part for each UTC day that has `J` lines, keyed by the day's start in epoch seconds.
/// Other anglers' names in the fields player-fields.tsv lists become pseudonyms; with `social`
/// on, the part starts with a `pn` line for each, as a social part does.
pub fn build_parts(character: &str, mut events: Vec<Ev>, opts: &Options) -> Vec<Part> {
    if !opts.on.contains(GROUP) {
        return Vec::new();
    }
    let with_names = opts.on.contains(SOCIAL_GROUP);
    let players = PlayerFields::get();
    events.sort_by_key(|e| e.ms);
    let mut days: BTreeMap<i64, Vec<&Ev>> = BTreeMap::new();
    for ev in &events {
        if spec(ev.code()).is_some() && ev.code() != CATCH_CODE {
            days.entry(ev.ms.div_euclid(DAY_MS)).or_default().push(ev);
        }
    }
    days.into_iter()
        .map(|(day, lines)| {
            let session = day * 86_400;
            let mut pseudonyms: BTreeMap<String, String> = BTreeMap::new();
            let mut body = String::new();
            let mut prev = session * 1000;
            for ev in &lines {
                let mut fields: Vec<String> = ev.text.split('\t').map(str::to_string).collect();
                for rule in players.rules(ev.code()) {
                    let i = rule.field + 1;
                    let Some(name) = fields.get(i) else { continue };
                    if keeps_name(name, character) {
                        continue;
                    }
                    let p = pseudonym(&opts.name_key, name);
                    pseudonyms.entry(p.clone()).or_insert_with(|| name.clone());
                    fields[i] = p;
                }
                body.push_str(&(ev.ms - prev).to_string());
                body.push('\t');
                body.push_str(&fields.join("\t"));
                body.push('\n');
                prev = ev.ms;
            }
            let mut text = format!("{PART_MAGIC}\t1\t{character}\t{session}\t{GROUP}\n");
            let mut count = lines.len();
            if with_names {
                for (p, name) in &pseudonyms {
                    text.push_str("0\tpn\t");
                    text.push_str(p);
                    text.push('\t');
                    text.push_str(name);
                    text.push('\n');
                }
                count += pseudonyms.len();
            }
            text.push_str(&body);
            Part {
                id: part_id(&text),
                session,
                group: GROUP.to_string(),
                lines: count,
                text,
            }
        })
        .collect()
}

fn write_field(rows: &mut Rows, name: &str, kind: Kind, value: &str) {
    let value = value.trim();
    if value.is_empty() {
        rows.null(name);
        return;
    }
    let list = |v: &str| -> Vec<f64> {
        v.split(',')
            .filter_map(|p| p.trim().parse::<f64>().ok())
            .filter(|n| n.is_finite())
            .collect()
    };
    match kind {
        Int => rows.opt(name, value.parse::<i64>().ok()),
        Decimal => match value.parse::<f64>() {
            Ok(n) if n.is_finite() => rows.num(name, n),
            _ => rows.null(name),
        },
        Text => rows.str(name, value),
        Bit => rows.bool(name, value == "1"),
        IntList | DecimalList => rows.nums(name, &list(value)),
    };
}

/// Rows of table `fishing` from the parts among `parts`: one per `J` line of a `fishing` part and
/// per `fl C` line of an `addons` part (code `fl.C`), with `code`, `at` (epoch ms) and the line's
/// fields by name; and a `pn` row (`pseudonym`, `name`) for each `pn` line. Other groups are
/// ignored. Returns the number of rows.
pub fn decode_fishing(parts: &[&str], out: &mut String) -> Result<usize, String> {
    let mut rows = Rows::new(out);
    for text in parts {
        let (_, session, group) =
            parse_header(text).ok_or_else(|| "a part has no valid header".to_string())?;
        let addons = group == ADDONS_GROUP;
        if group != GROUP && !addons {
            continue;
        }
        let mut t = session * 1000;
        for line in text.split('\n').skip(1).filter(|l| !l.is_empty()) {
            let mut f = line.split('\t');
            let dt: i64 = f
                .next()
                .and_then(|d| d.parse().ok())
                .ok_or("bad line in a fishing or addons part")?;
            t += dt;
            let code = if addons {
                match (f.next(), f.next()) {
                    (Some("fl"), Some("C")) => CATCH_CODE,
                    _ => continue,
                }
            } else {
                f.next().unwrap_or("")
            };
            let fields: Vec<&str> = f.collect();
            if code == "pn" {
                rows.begin("pn");
                rows.str("pseudonym", fields.first().copied().unwrap_or(""))
                    .str("name", fields.get(1).copied().unwrap_or(""));
                rows.end();
                continue;
            }
            let spec = spec(code).ok_or_else(|| format!("unknown fishing code {code}"))?;
            rows.begin(TABLE);
            rows.str("code", code).int("at", t);
            for (i, (name, kind)) in spec.iter().enumerate() {
                write_field(&mut rows, name, *kind, fields.get(i).copied().unwrap_or(""));
            }
            rows.end();
        }
    }
    Ok(rows.count)
}
