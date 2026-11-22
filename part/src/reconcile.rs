//! Reconcile: which of the counts other addons made are added to Scarlet's own totals (docs/DESIGN.md,
//! "Addon counts", "Reconcile"). One character, one kind: the character's claims, its Scarlet rows of the
//! kind and the claims of the characters it shares an account with go in, and every claim comes out with
//! exactly one status, the amounts that count and the evidence for both. Nothing here reads a database
//! or a clock: `now` is an input, so the same input always gives the same output.

mod rules;

use rules::Event;
use serde_json::{Map, Value, json};
use std::collections::BTreeMap;

/// The version of the rules below. The scheduled pass reconciles a character again when its claims carry an
/// older one.
pub const RECONCILE_VERSION: u32 = 2;

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

/// One of Scarlet's own rows of the kind: for fishing a `gathers` row of kind fish, with the quantity of the
/// `gather_items` rows of its loot window.
#[derive(Clone, Debug)]
pub struct Row {
    pub at: i64,
    pub looted: bool,
    pub items: i64,
    pub level: Option<i64>,
    pub zone: Option<i64>,
    pub session: i64,
    pub item_ids: Vec<i64>,
}

/// A character, its claims of the kind and, for a character other than the one reconciled, the `at` (epoch
/// milliseconds) of its Scarlet rows of the kind that lie within a minute of its claims' windows.
#[derive(Clone, Debug)]
pub struct Sibling {
    pub id: i64,
    pub name: String,
    pub claims: Vec<Claim>,
    pub rows: Vec<i64>,
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
    let from = c.start() * 1000 - rules::ASSIGN_SLACK_MS;
    let until = c.to * 1000 + rules::ASSIGN_SLACK_MS;
    let own = (
        !rules::holds_rows(input.rows.iter().map(|r| r.at), from, until),
        input.character.id,
    );
    let (key, holder, same) = input
        .others
        .iter()
        .filter_map(|o| {
            let same = o.claims.iter().find(|x| rules::same_record(c, x))?;
            let rows = rules::holds_rows(o.rows.iter().copied(), from, until);
            Some(((!rows, o.id), o, same))
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
        for (j, _) in higher {
            for (metric, amount) in self.claims()[*j].whole().unwrap_or_default() {
                if whole.contains_key(&metric) {
                    *minus.entry(metric).or_insert(0) += amount;
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
/// holds Scarlet rows beside it or has the lower id (rule 4), the same origin's overlapping claims (rule 5), then
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
    for &i in &order {
        let c = &claims[i];
        match early(input, c).or_else(|| twin(input, c)) {
            Some(d) => decided[i] = Some(d),
            None if c.window == WINDOW_EVENT => events.push(i),
            None => dated.push(i),
        }
    }
    for (loser, winner) in rules::collapse(claims, &dated) {
        decided[loser] = Some(nothing(
            &claims[loser],
            Status::Superseded,
            json!({"rule": 5, "same_origin": true, "with": claims[winner].natural()}),
        ));
    }
    for origin in rules::ranking(&input.kind) {
        dated_origin(input, &rows, &dated, origin, &mut decided);
    }
    for (&i, event) in events
        .iter()
        .zip(rules::match_events(claims, &events, &rows))
    {
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
                json!({"rule": 2, "why": "no Scarlet row matches inside a session that recorded rows of the kind"}),
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
                level: d.level,
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
    Ok(Row {
        at: int(v, "at")?,
        looted: v.get("looted").and_then(Value::as_bool).unwrap_or(false),
        items: v.get("items").and_then(Value::as_i64).unwrap_or(0),
        level: v.get("level").and_then(Value::as_i64),
        zone: v.get("zone").and_then(Value::as_i64),
        session: v.get("session").and_then(Value::as_i64).unwrap_or(0),
        item_ids: v
            .get("item_ids")
            .and_then(Value::as_array)
            .map(|a| a.iter().filter_map(Value::as_i64).collect())
            .unwrap_or_default(),
    })
}

fn sibling_of(v: &Value) -> Result<Sibling, String> {
    Ok(Sibling {
        id: int(v, "id")?,
        name: text(v, "name")?,
        claims: list(v, "claims")?
            .iter()
            .map(claim_of)
            .collect::<Result<_, _>>()?,
        rows: v
            .get("rows")
            .and_then(Value::as_array)
            .map(|a| a.iter().filter_map(Value::as_i64).collect())
            .unwrap_or_default(),
    })
}

/// Read the JSON the server and the command line send: `kind`, `now`, `character` (`id`, `name`, `claims`),
/// `rows` and `others` (characters like `character`, each with the `at` of its own rows in `rows`, which may be
/// left out).
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

fn sums(claims: &[&Claim], counted: &[&Outcome]) -> (String, String) {
    let metrics = ["casts", "landed", "items"];
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
/// what counts (casts, landed and items for fishing), and the rule of the first claim of the status.
pub fn explain(input: &Input, outcomes: &[Outcome]) -> String {
    let claims = &input.character.claims;
    let mut out = format!(
        "{} {}: {} claims, {} Scarlet rows\n{:<11} {:>6}  {:<22} {:<22} {}\n",
        input.character.name,
        input.kind,
        claims.len(),
        input.rows.len(),
        "status",
        "claims",
        "claimed c/l/i",
        "counted c/l/i",
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
    let (claimed, counted) = sums(&all, &kept);
    let scarlet = [
        input.rows.len() as i64,
        input.rows.iter().filter(|r| r.looted).count() as i64,
        input.rows.iter().map(|r| r.items).sum::<i64>(),
    ];
    out.push_str(&format!(
        "{:<11} {:>6}  {:<22} {:<22}\nScarlet rows {}/{}/{}\n",
        "all",
        claims.len(),
        claimed,
        counted,
        scarlet[0],
        scarlet[1],
        scarlet[2]
    ));
    out
}
