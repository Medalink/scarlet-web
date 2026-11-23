//! Reconcile: which of the counts other addons made are added to Scarlet's own totals (docs/DESIGN.md,
//! "Addon counts", "Reconcile"). One character, one kind: the character's claims, its Scarlet rows of the
//! kind and the claims of the characters it shares an account with go in, and every claim comes out with
//! exactly one status, the amounts that count and the evidence for both. Nothing here reads a database
//! or a clock: `now` is an input, so the same input always gives the same output.

mod levels;
mod rules;
mod runs;

use rules::{Event, Kind};
use serde_json::{Map, Value, json};
use std::cmp::Reverse;
use std::collections::BTreeMap;

/// The version of the rules below. The scheduled pass reconciles a character again when its claims carry an
/// older one.
pub const RECONCILE_VERSION: u32 = 7;

const EPOCH_FLOOR: i64 = 1_566_777_600;
const FUTURE_SLACK: i64 = 86_400;
const MAX_AMOUNT: f64 = 9.0e15;
const WINDOW_EVENT: &str = "event";
const WINDOW_LIFE: &str = "life";
const SCOPE_ACCOUNT: &str = "a";

/// What happened to a claim. Every claim ends with exactly one.
#[derive(Clone, Copy, PartialEq, Eq, Debug)]
pub enum Status {
    Counted,
    Residual,
    Superseded,
    Held,
    Rejected,
    Reference,
}

impl Status {
    pub const ALL: [Status; 6] = [
        Status::Counted,
        Status::Residual,
        Status::Superseded,
        Status::Held,
        Status::Rejected,
        Status::Reference,
    ];

    /// The word the database stores.
    pub fn as_str(self) -> &'static str {
        match self {
            Status::Counted => "counted",
            Status::Residual => "residual",
            Status::Superseded => "superseded",
            Status::Held => "held",
            Status::Rejected => "rejected",
            Status::Reference => "reference",
        }
    }
}

/// One additive statement an addon made (a row of `addon_claims`).
#[derive(Clone, Debug)]
pub struct Claim {
    pub id: i64,
    pub origin: String,
    pub store: String,
    pub scope: String,
    pub window: String,
    pub kind: String,
    pub from: Option<i64>,
    pub to: i64,
    pub dims: BTreeMap<String, String>,
    pub amounts: BTreeMap<String, f64>,
}

type OrderKey<'a> = (
    &'a str,
    &'a str,
    &'a str,
    &'a str,
    &'a str,
    &'a BTreeMap<String, String>,
    i64,
    i64,
);

impl Claim {
    /// The amounts as whole numbers, or None when one is negative, fractional or beyond 9e15.
    pub fn whole(&self) -> Option<BTreeMap<String, i64>> {
        self.amounts
            .iter()
            .map(|(k, v)| {
                (v.is_finite() && *v >= 0.0 && v.fract() == 0.0 && *v <= MAX_AMOUNT)
                    .then(|| (k.clone(), *v as i64))
            })
            .collect()
    }

    fn start(&self) -> i64 {
        self.from.unwrap_or(self.to)
    }

    fn order_key(&self) -> OrderKey<'_> {
        (
            &self.origin,
            &self.store,
            &self.scope,
            &self.window,
            &self.kind,
            &self.dims,
            self.to,
            self.from.unwrap_or(0),
        )
    }

    fn natural(&self) -> Value {
        let mut key = json!({
            "origin": self.origin,
            "store": self.store,
            "window": self.window,
            "kind": self.kind,
            "from": self.from,
            "to": self.to,
        });
        if !self.dims.is_empty() {
            key["dims"] = json!(self.dims);
        }
        key
    }
}

/// One of Scarlet's own rows of the kind, in the shape the kind's rule reads. Fishing: a `gathers` row of kind
/// fish, with the quantity of the `gather_items` rows of its loot window. Kill: the tally of one level, the
/// non-player `kills` rows there (`count`, `level`) or, with no `level`, the rows the decoder wrote before the
/// session had one; `at` is the last of them. Xp: one turn-in (`at`, `key` the quest id or none when the client
/// hid it, `xp`, `level`), from `quest_events` (`events`) or from `xp_gains` of source `q`. Run: one joined
/// `dungeon_runs` row (`key` the instance, `at` its first entry, `end` its last leave). Boss: one `boss_kills` row
/// (`at`, `key` the encounter id or none, `session`), counted or not.
#[derive(Clone, Debug)]
pub struct Row {
    pub at: i64,
    pub looted: bool,
    pub items: i64,
    pub level: Option<i64>,
    pub zone: Option<i64>,
    pub session: i64,
    pub item_ids: Vec<i64>,
    pub count: i64,
    pub key: Option<i64>,
    pub xp: i64,
    pub end: i64,
    pub events: bool,
}

/// A character, its claims of the kind and, for a character other than the one reconciled, its Scarlet rows of
/// the kind that its claims' rule reads: for fishing only the `at` (epoch milliseconds) of the rows within a
/// minute of its claims' windows. `spans` holds the start and end (epoch milliseconds) of each of its sessions with a
/// combat part whose span lies within 63 s of one of its claims, which the boss rules read.
#[derive(Clone, Debug)]
pub struct Sibling {
    pub id: i64,
    pub name: String,
    pub claims: Vec<Claim>,
    pub rows: Vec<Row>,
    pub spans: Vec<(i64, i64)>,
}

/// Everything one reconcile reads. Times of claims are epoch seconds, `at` of a row epoch milliseconds.
#[derive(Clone, Debug)]
pub struct Input {
    pub kind: String,
    pub now: i64,
    pub character: Sibling,
    pub rows: Vec<Row>,
    pub others: Vec<Sibling>,
}

/// The verdict on one claim.
#[derive(Clone, Debug)]
pub struct Outcome {
    pub id: i64,
    pub status: Status,
    pub counted: BTreeMap<String, i64>,
    pub evidence: Value,
    pub level: Option<i64>,
    pub zone: Option<i64>,
}

#[derive(Clone)]
struct Decision {
    status: Status,
    counted: BTreeMap<String, i64>,
    evidence: Value,
    level: Option<i64>,
    zone: Option<i64>,
}

fn num_value(n: f64) -> Value {
    if n.fract() == 0.0 && n.abs() < MAX_AMOUNT {
        Value::from(n as i64)
    } else {
        Value::from(n)
    }
}

fn decide(c: &Claim, status: Status, counted: BTreeMap<String, i64>, evidence: Value) -> Decision {
    let counted: BTreeMap<String, i64> = c
        .amounts
        .keys()
        .map(|k| (k.clone(), counted.get(k).copied().unwrap_or(0)))
        .collect();
    let mut evidence = match evidence {
        Value::Object(map) => map,
        _ => Map::new(),
    };
    let diff: Map<String, Value> = c
        .amounts
        .iter()
        .map(|(k, v)| (k.clone(), num_value(v - counted[k] as f64)))
        .collect();
    evidence.insert("diff".into(), Value::Object(diff));
    Decision {
        status,
        counted,
        evidence: Value::Object(evidence),
        level: None,
        zone: None,
    }
}

fn nothing(c: &Claim, status: Status, evidence: Value) -> Decision {
    decide(c, status, BTreeMap::new(), evidence)
}

fn early(input: &Input, c: &Claim) -> Option<Decision> {
    let Some(whole) = c.whole() else {
        return Some(nothing(
            c,
            Status::Rejected,
            json!({"rule": "bounds", "why": "an amount is not a whole number of 0 or more"}),
        ));
    };
    if let Some(why) = rules::out_of_bounds(c, &whole, input.now) {
        return Some(nothing(
            c,
            Status::Rejected,
            json!({"rule": "bounds", "why": why}),
        ));
    }
    if c.scope == SCOPE_ACCOUNT {
        return Some(nothing(c, Status::Reference, json!({"rule": 7})));
    }
    if let Some(why) = rules::unmapped(c) {
        return Some(nothing(
            c,
            Status::Held,
            json!({"rule": "unmapped", "why": why}),
        ));
    }
    if c.window == WINDOW_LIFE {
        return Some(nothing(
            c,
            Status::Held,
            json!({"rule": 6, "why": "no origin maps a lifetime total of one character"}),
        ));
    }
    None
}

fn twin(input: &Input, c: &Claim) -> Option<Decision> {
    let standing = |who: &Sibling, rows: &[Row]| {
        let (held, in_session) = rules::beside(&input.kind, c, rows, &who.spans);
        (Reverse(held), Reverse(in_session), who.id)
    };
    let own = standing(&input.character, &input.rows);
    let (key, holder, same) = input
        .others
        .iter()
        .filter_map(|o| {
            let same = o.claims.iter().find(|x| rules::same_record(c, x))?;
            Some((standing(o, &o.rows), o, same))
        })
        .min_by_key(|(key, _, _)| *key)?;
    (key < own).then(|| {
        nothing(
            c,
            Status::Superseded,
            json!({"rule": 4, "with": {"character": holder.name, "claim": same.natural()}}),
        )
    })
}

fn rows_of<'a>(rows: &'a [Row], indices: &[usize]) -> Vec<&'a Row> {
    indices.iter().map(|&i| &rows[i]).collect()
}

fn with_rows(mut d: Decision, rows: &[&Row]) -> Decision {
    if let Some(last) = rows.last() {
        d.level = last.level;
        d.zone = last.zone;
    }
    d
}

struct Dated<'a> {
    input: &'a Input,
    rows: &'a [Row],
    decided: &'a [Option<Decision>],
}

impl Dated<'_> {
    fn claims(&self) -> &[Claim] {
        &self.input.character.claims
    }

    fn higher(&self, c: &Claim) -> Vec<(usize, i64)> {
        let Some(mine) = rules::rank(&self.input.kind, &c.origin) else {
            return Vec::new();
        };
        self.claims()
            .iter()
            .enumerate()
            .filter_map(|(j, other)| {
                let counts = matches!(
                    self.decided[j].as_ref().map(|d| d.status),
                    Some(Status::Counted | Status::Residual)
                );
                let above = rules::rank(&self.input.kind, &other.origin).is_some_and(|r| r < mine);
                let overlap = rules::overlap(c, other);
                (counts && above && overlap > rules::OVERLAP_SLACK).then_some((j, overlap))
            })
            .collect()
    }

    fn covered(&self, c: &Claim, higher: &[(usize, i64)]) -> Decision {
        let whole = c.whole().unwrap_or_default();
        let mut minus: BTreeMap<String, i64> = BTreeMap::new();
        for (j, overlap) in higher {
            let above = &self.claims()[*j];
            let span = rules::span(above);
            for (metric, amount) in above.whole().unwrap_or_default() {
                if whole.contains_key(&metric) {
                    *minus.entry(metric).or_insert(0) += rules::part_of(amount, *overlap, span);
                }
            }
        }
        let reach = |metric: &str| minus.get(metric).copied().unwrap_or(0);
        let evidence = json!({
            "rule": 5,
            "origin": true,
            "overlap": higher.iter().map(|(_, overlap)| overlap).sum::<i64>(),
            "with": higher.iter().map(|(j, _)| self.claims()[*j].natural()).collect::<Vec<_>>(),
            "minus": minus,
        });
        let covered = whole
            .iter()
            .filter(|(metric, _)| metric.as_str() != "unknown")
            .all(|(metric, claimed)| rules::covers(reach(metric), *claimed));
        if covered {
            return nothing(c, Status::Superseded, evidence);
        }
        let counted = whole
            .iter()
            .filter(|(metric, _)| metric.as_str() != "unknown")
            .map(|(metric, claimed)| (metric.clone(), (claimed - reach(metric)).max(0)))
            .collect();
        decide(c, Status::Residual, counted, evidence)
    }

    fn decide(
        &self,
        c: &Claim,
        assigned: &[usize],
        before: Option<&[usize]>,
        prev: Option<&Claim>,
    ) -> Decision {
        let rows = rows_of(self.rows, assigned);
        let higher = self.higher(c);
        let covered = (!higher.is_empty()).then(|| self.covered(c, &higher));
        if let Some(d) = covered.as_ref().filter(|d| d.status == Status::Superseded) {
            return with_rows(d.clone(), &rows);
        }
        if rules::server_clock(&c.origin) && rules::near(self.rows, c) {
            let d = if rows.is_empty() {
                nothing(
                    c,
                    Status::Held,
                    json!({"rule": 1, "why": "Scarlet rows lie within 24 hours of a claim on the game server's clock and none is assigned to it"}),
                )
            } else {
                nothing(
                    c,
                    Status::Superseded,
                    json!({"rule": 1, "rows": rules::rows_json(&rows)}),
                )
            };
            return with_rows(d, &rows);
        }
        if let Some(d) = covered {
            return with_rows(d, &rows);
        }
        let whole = c.whole().unwrap_or_default();
        if !c.amounts.contains_key("casts") && c.amounts.contains_key("items") {
            return self.catch_started(c, &whole, &rows, before, prev);
        }
        if rows.is_empty() {
            if let Some((first, last)) = rules::cancelled_in_session(self.rows, c, &whole) {
                return nothing(
                    c,
                    Status::Superseded,
                    json!({"rule": 3, "rows": {"n": 0}, "session": [first, last]}),
                );
            }
            return decide(
                c,
                Status::Counted,
                rules::counted_whole(&whole),
                json!({"rule": 3, "rows": {"n": 0}}),
            );
        }
        let counted = rules::share(&whole, &rows);
        let status = if counted.values().all(|v| *v == 0) {
            Status::Superseded
        } else {
            Status::Residual
        };
        with_rows(
            decide(
                c,
                status,
                counted,
                json!({"rule": 3, "rows": rules::rows_json(&rows)}),
            ),
            &rows,
        )
    }

    fn catch_started(
        &self,
        c: &Claim,
        whole: &BTreeMap<String, i64>,
        rows: &[&Row],
        before: Option<&[usize]>,
        prev: Option<&Claim>,
    ) -> Decision {
        if rows.iter().any(|r| r.looted) {
            return with_rows(
                nothing(
                    c,
                    Status::Superseded,
                    json!({"rule": 3, "catch_started": true, "rows": rules::rows_json(rows)}),
                ),
                rows,
            );
        }
        let last = before
            .and_then(|b| b.last())
            .map(|&i| &self.rows[i])
            .filter(|row| row.looted);
        match (last, prev) {
            (Some(row), Some(p)) => nothing(
                c,
                Status::Superseded,
                json!({"rule": 3, "catch_started": true, "with": p.natural(), "row": {"at": row.at, "looted": row.looted}}),
            ),
            _ => with_rows(
                decide(
                    c,
                    Status::Counted,
                    rules::counted_whole(whole),
                    json!({"rule": 3, "catch_started": true, "rows": rules::rows_json(rows)}),
                ),
                rows,
            ),
        }
    }
}

fn dated_origin(
    input: &Input,
    rows: &[Row],
    dated: &[usize],
    origin: &str,
    decided: &mut [Option<Decision>],
) {
    let claims = &input.character.claims;
    let mut members: Vec<usize> = dated
        .iter()
        .copied()
        .filter(|&i| claims[i].origin == origin && decided[i].is_none())
        .collect();
    members.sort_by_key(|&i| (claims[i].start(), claims[i].to));
    let windows: Vec<(i64, i64)> = members
        .iter()
        .map(|&i| (claims[i].start(), claims[i].to))
        .collect();
    let mut assigned = rules::assign(rows, &windows);
    rules::settle_seams(rows, claims, &members, &mut assigned);
    let verdicts: Vec<Decision> = {
        let ctx = Dated {
            input,
            rows,
            decided: &*decided,
        };
        members
            .iter()
            .enumerate()
            .map(|(m, &i)| {
                let c = &claims[i];
                let prev = rules::previous(claims, &members, m);
                let before = prev.map(|(p, _)| assigned[p].as_slice());
                ctx.decide(c, &assigned[m], before, prev.map(|(_, j)| &claims[j]))
            })
            .collect()
    };
    for (&i, d) in members.iter().zip(verdicts) {
        decided[i] = Some(d);
    }
}

/// Decide every claim of one character and kind: bounds, then the account's total as a reference, kinds,
/// origins and metrics no rule maps, a lifetime of one character, the same record on another character that
/// holds more Scarlet rows beside it, or as many and a lower id (rule 4), the same origin's overlapping claims (rule 5), then
/// per origin from the highest rank the rows
/// assigned to each claim (rules 1 and 3) and the claims of higher ranks (rule 5), and events (rule 2).
/// Outcomes come back in the order of `input.character.claims`.
pub fn reconcile(input: &Input) -> Vec<Outcome> {
    let claims = &input.character.claims;
    let mut rows = input.rows.clone();
    rows.sort_by_key(|r| r.at);
    let mut decided: Vec<Option<Decision>> = vec![None; claims.len()];
    let mut order: Vec<usize> = (0..claims.len()).collect();
    order.sort_by(|a, b| claims[*a].order_key().cmp(&claims[*b].order_key()));

    let mut dated = Vec::new();
    let mut events = Vec::new();
    let mut twinned = Vec::new();
    for &i in &order {
        let c = &claims[i];
        if let Some(d) = early(input, c) {
            decided[i] = Some(d);
            continue;
        }
        if let Some(d) = twin(input, c) {
            decided[i] = Some(d);
            twinned.push(i);
            continue;
        }
        if c.window == WINDOW_EVENT {
            events.push(i);
            continue;
        }
        dated.push(i);
    }
    for &i in &dated {
        let moved = twinned
            .iter()
            .find(|&&t| rules::one_level_record(&claims[t], &claims[i]));
        if let Some(&t) = moved {
            decided[i] = Some(nothing(
                &claims[i],
                Status::Superseded,
                json!({"rule": 5, "same_origin": true, "with": claims[t].natural()}),
            ));
        }
    }
    dated.retain(|&i| decided[i].is_none());
    for (loser, winner) in rules::collapse(claims, &dated) {
        decided[loser] = Some(nothing(
            &claims[loser],
            Status::Superseded,
            json!({"rule": 5, "same_origin": true, "with": claims[winner].natural()}),
        ));
    }
    let open: Vec<usize> = dated
        .iter()
        .copied()
        .filter(|&i| decided[i].is_none())
        .collect();
    match Kind::of(&input.kind) {
        Some(Kind::Fishing) => {
            for origin in rules::ranking(&input.kind) {
                dated_origin(input, &rows, &dated, origin, &mut decided);
            }
        }
        Some(Kind::Kill) => levels::kills(&input.character.claims, &rows, &open, &mut decided),
        Some(Kind::Xp) => levels::quests(&input.character.claims, &rows, &open, &mut decided),
        Some(Kind::Run) => runs::runs(
            &input.character.claims,
            &rows,
            &open,
            &twinned,
            &mut decided,
        ),
        Some(Kind::Boss) | None => {}
    }
    let verdicts = Kind::of(&input.kind).map_or_else(Vec::new, |kind| {
        rules::match_events(kind, claims, &events, &rows, &input.character.spans)
    });
    for (&i, event) in events.iter().zip(verdicts) {
        let c = &claims[i];
        decided[i] = Some(match event {
            Event::Matched { at, offset } => nothing(
                c,
                Status::Superseded,
                json!({"rule": 2, "row": {"at": at}, "offset_ms": offset}),
            ),
            Event::Held => nothing(
                c,
                Status::Held,
                json!({"rule": 2, "why": rules::held_why(&input.kind)}),
            ),
            Event::Unkeyed => nothing(
                c,
                Status::Held,
                json!({"rule": 2, "why": "the event names no item to match a row by"}),
            ),
            Event::Open => decide(
                c,
                Status::Counted,
                rules::counted_whole(&c.whole().unwrap_or_default()),
                json!({"rule": 2, "rows": {"n": 0}}),
            ),
        });
    }

    claims
        .iter()
        .zip(decided)
        .map(|(c, d)| {
            let d = d.unwrap_or_else(|| {
                nothing(
                    c,
                    Status::Held,
                    json!({"rule": "unmapped", "why": "no rule decided the claim"}),
                )
            });
            Outcome {
                id: c.id,
                status: d.status,
                counted: d.counted,
                evidence: d.evidence,
                level: rules::dim_level(c).or(d.level),
                zone: d.zone,
            }
        })
        .collect()
}

fn text(v: &Value, key: &str) -> Result<String, String> {
    v.get(key)
        .and_then(Value::as_str)
        .map(str::to_string)
        .ok_or_else(|| format!("`{key}` must be a string"))
}

fn int(v: &Value, key: &str) -> Result<i64, String> {
    v.get(key)
        .and_then(Value::as_i64)
        .ok_or_else(|| format!("`{key}` must be an integer"))
}

fn list<'a>(v: &'a Value, key: &str) -> Result<&'a Vec<Value>, String> {
    v.get(key)
        .and_then(Value::as_array)
        .ok_or_else(|| format!("`{key}` must be an array"))
}

fn claim_of(v: &Value) -> Result<Claim, String> {
    let dims = match v.get("dims") {
        Some(Value::Object(m)) => m
            .iter()
            .map(|(k, x)| (k.clone(), x.as_str().unwrap_or_default().to_string()))
            .collect(),
        _ => BTreeMap::new(),
    };
    let amounts = match v.get("amounts") {
        Some(Value::Object(m)) => m
            .iter()
            .map(|(k, x)| {
                x.as_f64()
                    .map(|n| (k.clone(), n))
                    .ok_or_else(|| format!("amount `{k}` is not a number"))
            })
            .collect::<Result<_, _>>()?,
        _ => BTreeMap::new(),
    };
    Ok(Claim {
        id: v.get("id").and_then(Value::as_i64).unwrap_or(0),
        origin: text(v, "origin")?,
        store: v
            .get("store")
            .and_then(Value::as_str)
            .unwrap_or("")
            .to_string(),
        scope: text(v, "scope")?,
        window: text(v, "window")?,
        kind: text(v, "kind")?,
        from: v.get("from").and_then(Value::as_i64),
        to: int(v, "to")?,
        dims,
        amounts,
    })
}

fn row_of(v: &Value) -> Result<Row, String> {
    let at = int(v, "at")?;
    let number = |key: &str, none: i64| v.get(key).and_then(Value::as_i64).unwrap_or(none);
    Ok(Row {
        at,
        looted: v.get("looted").and_then(Value::as_bool).unwrap_or(false),
        items: number("items", 0),
        level: v.get("level").and_then(Value::as_i64),
        zone: v.get("zone").and_then(Value::as_i64),
        session: number("session", 0),
        item_ids: v
            .get("item_ids")
            .and_then(Value::as_array)
            .map(|a| a.iter().filter_map(Value::as_i64).collect())
            .unwrap_or_default(),
        count: number("count", 1),
        key: v.get("key").and_then(Value::as_i64),
        xp: number("xp", 0),
        end: number("end", at),
        events: v.get("events").and_then(Value::as_bool).unwrap_or(false),
    })
}

fn sibling_row(v: &Value) -> Result<Row, String> {
    match v.as_i64() {
        Some(at) => row_of(&json!({"at": at})),
        None => row_of(v),
    }
}

fn sibling_of(v: &Value) -> Result<Sibling, String> {
    Ok(Sibling {
        id: int(v, "id")?,
        name: text(v, "name")?,
        claims: list(v, "claims")?
            .iter()
            .map(claim_of)
            .collect::<Result<_, _>>()?,
        rows: match v.get("rows") {
            Some(rows) => rows
                .as_array()
                .ok_or("`rows` must be an array")?
                .iter()
                .map(sibling_row)
                .collect::<Result<_, _>>()?,
            None => Vec::new(),
        },
        spans: match v.get("spans") {
            Some(spans) => spans
                .as_array()
                .ok_or("`spans` must be an array")?
                .iter()
                .map(span_of)
                .collect::<Result<_, _>>()?,
            None => Vec::new(),
        },
    })
}

fn span_of(v: &Value) -> Result<(i64, i64), String> {
    match v.as_array().map(Vec::as_slice) {
        Some([first, last]) => first
            .as_i64()
            .zip(last.as_i64())
            .ok_or_else(|| "a span holds two integers".to_string()),
        _ => Err("a span is an array of two integers".to_string()),
    }
}

/// Read the JSON the server and the command line send: `kind`, `now`, `character` (`id`, `name`, `claims`,
/// `spans`), `rows` and `others` (characters like `character`, each with its own rows in `rows`, which may be left
/// out: a fishing row is the integer `at` alone, the other kinds send the rows their rule reads, and `spans` is a
/// list of `[first, last]` in epoch milliseconds that only the boss kind reads).
pub fn parse(v: &Value) -> Result<Input, String> {
    Ok(Input {
        kind: text(v, "kind")?,
        now: int(v, "now")?,
        character: sibling_of(v.get("character").ok_or("`character` is missing")?)?,
        rows: list(v, "rows")?
            .iter()
            .map(row_of)
            .collect::<Result<_, _>>()?,
        others: match v.get("others") {
            Some(others) => others
                .as_array()
                .ok_or("`others` must be an array")?
                .iter()
                .map(sibling_of)
                .collect::<Result<_, _>>()?,
            None => Vec::new(),
        },
    })
}

/// The outcomes as the JSON the server stores: `version` and one entry per claim with `id`, `status`,
/// `counted`, `evidence`, `level` and `zone`.
pub fn to_json(outcomes: &[Outcome]) -> Value {
    json!({
        "version": RECONCILE_VERSION,
        "claims": outcomes.iter().map(|o| json!({
            "id": o.id,
            "status": o.status.as_str(),
            "counted": o.counted,
            "evidence": o.evidence,
            "level": o.level,
            "zone": o.zone,
        })).collect::<Vec<_>>(),
    })
}

fn sums(kind: &str, claims: &[&Claim], counted: &[&Outcome]) -> (String, String) {
    let metrics = rules::reported(kind);
    let claimed: Vec<String> = metrics
        .iter()
        .map(|m| {
            claims
                .iter()
                .map(|c| c.amounts.get(*m).copied().unwrap_or(0.0))
                .sum::<f64>()
                .to_string()
        })
        .collect();
    let kept: Vec<String> = metrics
        .iter()
        .map(|m| {
            counted
                .iter()
                .map(|o| o.counted.get(*m).copied().unwrap_or(0))
                .sum::<i64>()
                .to_string()
        })
        .collect();
    (claimed.join("/"), kept.join("/"))
}

/// The table that explains a character's total: one line per status with its claims, what they claimed and
/// what counts (casts, landed and items for fishing, the kind's one metric for the others), and the rule of
/// the first claim of the status.
pub fn explain(input: &Input, outcomes: &[Outcome]) -> String {
    let claims = &input.character.claims;
    let metrics = rules::reported(&input.kind).join("/");
    let mut out = format!(
        "{} {}: {} claims, {} Scarlet rows\n{:<11} {:>6}  {:<22} {:<22} {}\n",
        input.character.name,
        input.kind,
        claims.len(),
        input.rows.len(),
        "status",
        "claims",
        format!("claimed {metrics}"),
        format!("counted {metrics}"),
        "evidence"
    );
    for status in Status::ALL {
        let picked: Vec<usize> = (0..claims.len())
            .filter(|&i| outcomes[i].status == status)
            .collect();
        if picked.is_empty() {
            continue;
        }
        let (claimed, kept) = sums(
            &input.kind,
            &picked.iter().map(|&i| &claims[i]).collect::<Vec<_>>(),
            &picked.iter().map(|&i| &outcomes[i]).collect::<Vec<_>>(),
        );
        let rule = &outcomes[picked[0]].evidence["rule"];
        out.push_str(&format!(
            "{:<11} {:>6}  {:<22} {:<22} rule {}\n",
            status.as_str(),
            picked.len(),
            claimed,
            kept,
            rule
        ));
    }
    let all: Vec<&Claim> = claims.iter().collect();
    let kept: Vec<&Outcome> = outcomes.iter().collect();
    let (claimed, counted) = sums(&input.kind, &all, &kept);
    out.push_str(&format!(
        "{:<11} {:>6}  {:<22} {:<22}\nScarlet rows {}\n",
        "all",
        claims.len(),
        claimed,
        counted,
        rules::scarlet_term(&input.kind, &input.rows)
    ));
    out
}
