//! Addon counts: the `YC` lines in which the companion writes what other addons counted (Scarlet
//! FORMAT.md, "Addon counts"). They travel in parts of group `counts`, one per UTC day under the
//! session key of that day, as imports do, and the session decoder skips the group. `decode_counts`
//! turns them into claim rows. It reads nothing but the parts, and a line it cannot use is counted
//! and skipped, never fatal to the day.

use crate::build::{parse_header, part_id};
use crate::decode::DECODER_VERSION;
use crate::pseudo::hex;
use crate::rows::Rows;
use std::collections::BTreeMap;

pub const GROUP: &str = "counts";

/// The line code of the group.
pub const CODE: &str = "YC";

const KINDS: [&str; 6] = ["fishing", "kill", "run", "xp", "herb", "ore"];
const WINDOWS: [&str; 5] = ["event", "session", "day", "level", "life"];
const FIELDS: usize = 9;
const MAX_ORIGIN: usize = 8;
const MAX_STORE: usize = 64;
const MAX_KIND: usize = 16;
const MAX_NAME: usize = 24;
const MAX_DIMS: usize = 512;
const MAX_AMOUNTS: usize = 16;

struct Claim {
    origin: String,
    store: String,
    scope: String,
    window: String,
    kind: String,
    from: Option<i64>,
    to: i64,
    dims: Vec<(String, String)>,
    amounts: Vec<(String, f64)>,
}

type Key<'a> = (
    &'a str,
    &'a str,
    &'a str,
    &'a str,
    &'a str,
    &'a [(String, String)],
    i64,
    i64,
);

impl Claim {
    fn key(&self) -> Key<'_> {
        let from = match self.window.as_str() {
            "life" => self.from.unwrap_or(0),
            _ => 0,
        };
        (
            &self.origin,
            &self.store,
            &self.scope,
            &self.window,
            &self.kind,
            &self.dims,
            self.to,
            from,
        )
    }
}

enum Read {
    Claim(Box<Claim>),
    Kind,
    Line,
}

#[derive(Default)]
struct Skipped {
    code: usize,
    kind: usize,
    line: usize,
    duplicate: usize,
}

/// Rows of `claim` and one closing `day` row from the counts parts among `parts` (other groups are
/// ignored). A claim row has `part` (the lower-case hex id of the part that carried it), the key
/// fields `origin`, `store`, `scope`, `window`, `kind`, `from` (null when a lifetime has none) and
/// `to`, then `dims` (an object of strings) and `amounts` (an object of numbers). Lines of one part
/// that repeat a key keep the last. The `day` row holds the decoder version, the claims written and
/// the lines skipped by reason. Returns the number of claims.
pub fn decode_counts(parts: &[&str], out: &mut String) -> Result<usize, String> {
    let mut rows = Rows::new(out);
    let mut skipped = Skipped::default();
    let mut claims = 0;
    for text in parts {
        let (_, _, group) =
            parse_header(text).ok_or_else(|| "a part has no valid header".to_string())?;
        if group != GROUP {
            continue;
        }
        let mut read = Vec::new();
        for line in text.split('\n').skip(1).filter(|l| !l.is_empty()) {
            let mut f = line.split('\t');
            let (Some(dt), Some(code)) = (f.next(), f.next()) else {
                skipped.line += 1;
                continue;
            };
            if code != CODE {
                skipped.code += 1;
                continue;
            }
            if dt.parse::<i64>().is_err() {
                skipped.line += 1;
                continue;
            }
            match read_claim(&f.collect::<Vec<_>>()) {
                Read::Claim(c) => read.push(c),
                Read::Kind => skipped.kind += 1,
                Read::Line => skipped.line += 1,
            }
        }
        let mut kept = BTreeMap::new();
        for c in &read {
            if kept.insert(c.key(), c).is_some() {
                skipped.duplicate += 1;
            }
        }
        let id = hex(&part_id(text));
        for c in kept.values() {
            rows.begin("claim");
            rows.str("part", &id)
                .str("origin", &c.origin)
                .str("store", &c.store)
                .str("scope", &c.scope)
                .str("window", &c.window)
                .str("kind", &c.kind)
                .opt("from", c.from)
                .int("to", c.to)
                .str_map("dims", c.dims.iter().map(|(k, v)| (k.as_str(), v.as_str())))
                .num_map("amounts", c.amounts.iter().map(|(k, v)| (k.as_str(), *v)));
            rows.end();
            claims += 1;
        }
    }
    rows.begin("day");
    rows.int("decoder_version", i64::from(DECODER_VERSION))
        .int("claims", claims as i64)
        .int("skipped_code", skipped.code as i64)
        .int("skipped_kind", skipped.kind as i64)
        .int("skipped_line", skipped.line as i64)
        .int("skipped_duplicate", skipped.duplicate as i64);
    rows.end();
    Ok(claims)
}

fn read_claim(f: &[&str]) -> Read {
    if f.len() < FIELDS {
        return Read::Line;
    }
    let (origin, store, scope, window, kind) = (f[0], f[1], f[2], f[3], f[4]);
    let shaped = named(origin, MAX_ORIGIN)
        && store.len() <= MAX_STORE
        && (scope == "c" || scope == "a")
        && WINDOWS.contains(&window)
        && named(kind, MAX_KIND);
    let to = time(f[6]);
    let from = match (f[5], window) {
        ("", "life") => Some(None),
        (v, _) => time(v).map(Some),
    };
    let (Some(to), Some(from), true) = (to, from, shaped) else {
        return Read::Line;
    };
    if window == "event" && from != Some(to) {
        return Read::Line;
    }
    let (Some(dims), Some(amounts)) = (read_dims(f[7]), read_amounts(f[8])) else {
        return Read::Line;
    };
    if !KINDS.contains(&kind) {
        return Read::Kind;
    }
    Read::Claim(Box::new(Claim {
        origin: origin.to_string(),
        store: store.to_string(),
        scope: scope.to_string(),
        window: window.to_string(),
        kind: kind.to_string(),
        from,
        to,
        dims,
        amounts,
    }))
}

fn time(s: &str) -> Option<i64> {
    s.parse::<i64>().ok().filter(|t| *t >= 0)
}

fn named(s: &str, max: usize) -> bool {
    !s.is_empty()
        && s.len() <= max
        && s.bytes()
            .all(|b| b.is_ascii_lowercase() || b.is_ascii_digit() || b == b'_')
}

fn read_dims(s: &str) -> Option<Vec<(String, String)>> {
    if s.len() > MAX_DIMS {
        return None;
    }
    let mut dims = BTreeMap::new();
    for pair in s.split(';').filter(|p| !p.is_empty()) {
        let (k, v) = pair.split_once('=')?;
        if !named(k, MAX_NAME)
            || v.is_empty()
            || dims.insert(k.to_string(), v.to_string()).is_some()
        {
            return None;
        }
    }
    Some(dims.into_iter().collect())
}

fn read_amounts(s: &str) -> Option<Vec<(String, f64)>> {
    let mut amounts = BTreeMap::new();
    for pair in s.split(';').filter(|p| !p.is_empty()) {
        let (k, v) = pair.split_once('=')?;
        let n: f64 = v.parse().ok().filter(|n: &f64| n.is_finite())?;
        if !named(k, MAX_NAME) || amounts.insert(k.to_string(), n).is_some() {
            return None;
        }
    }
    (!amounts.is_empty() && amounts.len() <= MAX_AMOUNTS).then(|| amounts.into_iter().collect())
}
