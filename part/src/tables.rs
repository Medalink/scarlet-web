//! The three tables the crate embeds: sharing groups, the player-name fields
//! and the name references. All three are TSV with a header row; `\r` is
//! trimmed so a Windows checkout reads the same.

use crate::pseudo::player_shaped;
use std::collections::{BTreeSet, HashMap};
use std::sync::OnceLock;

const GROUPS_TSV: &str = include_str!("../../docs/groups.tsv");
const PLAYER_FIELDS_TSV: &str = include_str!("../../docs/player-fields.tsv");
const NAME_REFS_TSV: &str = include_str!("../../docs/name-refs.tsv");

fn rows(text: &str) -> impl Iterator<Item = Vec<&str>> {
    text.lines()
        .skip(1)
        .map(|l| l.trim_end_matches('\r'))
        .filter(|l| !l.is_empty() && !l.starts_with('#'))
        .map(|l| l.split('\t').collect())
}

fn cell<'a>(row: &[&'a str], i: usize) -> &'a str {
    row.get(i).copied().unwrap_or("")
}

/// Conditions on a line's fields, joined by `and`. Each clause is
/// `field N is V`, `field N is empty` or `field N is player-shaped`; N is 1-based.
#[derive(Clone, Debug, PartialEq, Eq)]
pub struct When {
    clauses: Vec<Clause>,
}

#[derive(Clone, Debug, PartialEq, Eq)]
enum Clause {
    Is {
        field: usize,
        value: String,
    },
    Empty {
        field: usize,
    },
    /// A player's `First-Last` name shape.
    PlayerShaped {
        field: usize,
    },
}

impl When {
    fn parse(text: &str) -> Option<When> {
        let clauses = text
            .trim()
            .split(" and ")
            .map(Clause::parse)
            .collect::<Option<Vec<_>>>()?;
        (!clauses.is_empty()).then_some(When { clauses })
    }

    pub fn holds(&self, fields: &[&str]) -> bool {
        self.clauses.iter().all(|c| c.holds(fields))
    }
}

impl Clause {
    fn parse(text: &str) -> Option<Clause> {
        let rest = text.trim().strip_prefix("field ")?;
        let (n, v) = rest.split_once(" is ")?;
        let field = n.trim().parse::<usize>().ok()?.checked_sub(1)?;
        Some(match v.trim() {
            "empty" => Clause::Empty { field },
            "player-shaped" => Clause::PlayerShaped { field },
            value => Clause::Is {
                field,
                value: value.to_string(),
            },
        })
    }

    fn holds(&self, fields: &[&str]) -> bool {
        match self {
            Clause::Is { field, value } => fields.get(*field).is_some_and(|v| *v == value),
            Clause::Empty { field } => fields.get(*field).is_none_or(|v| v.is_empty()),
            Clause::PlayerShaped { field } => fields.get(*field).is_some_and(|v| player_shaped(v)),
        }
    }
}

pub struct Group {
    pub key: String,
    pub label: String,
    pub codes: Vec<String>,
    pub switchable: bool,
}

pub struct Groups {
    pub list: Vec<Group>,
    by_code: HashMap<String, usize>,
    /// Codes of the `none` row: the addon writes them, nothing uploads them.
    pub owner_only: BTreeSet<String>,
}

impl Groups {
    pub fn get() -> &'static Groups {
        static G: OnceLock<Groups> = OnceLock::new();
        G.get_or_init(|| Groups::parse(GROUPS_TSV))
    }

    fn parse(text: &str) -> Groups {
        let mut list = Vec::new();
        let mut by_code = HashMap::new();
        let mut owner_only = BTreeSet::new();
        for r in rows(text) {
            let key = cell(&r, 0).to_string();
            let codes: Vec<String> = cell(&r, 2).split_whitespace().map(str::to_string).collect();
            if key == "none" {
                owner_only.extend(codes);
                continue;
            }
            for c in codes.iter().filter(|c| *c != "*") {
                by_code.entry(c.clone()).or_insert(list.len());
            }
            list.push(Group {
                switchable: key != "core" && key != "names",
                label: cell(&r, 1).to_string(),
                key,
                codes,
            });
        }
        Groups {
            list,
            by_code,
            owner_only,
        }
    }

    /// Index of the group a main-stream line code belongs to.
    pub fn of_code(&self, code: &str) -> Option<usize> {
        self.by_code.get(code).copied()
    }

    pub fn index(&self, key: &str) -> Option<usize> {
        self.list.iter().position(|g| g.key == key)
    }

    pub fn key(&self, i: usize) -> &str {
        &self.list[i].key
    }
}

pub struct PlayerRule {
    pub field: usize,
    pub when: Option<When>,
}

/// Which fields of which codes hold another player's name or guild.
pub struct PlayerFields {
    by_code: HashMap<String, Vec<PlayerRule>>,
}

impl PlayerFields {
    pub fn get() -> &'static PlayerFields {
        static P: OnceLock<PlayerFields> = OnceLock::new();
        P.get_or_init(|| {
            let mut by_code: HashMap<String, Vec<PlayerRule>> = HashMap::new();
            for r in rows(PLAYER_FIELDS_TSV) {
                let Some(field) = cell(&r, 1)
                    .parse::<usize>()
                    .ok()
                    .and_then(|n| n.checked_sub(1))
                else {
                    continue;
                };
                by_code
                    .entry(cell(&r, 0).to_string())
                    .or_default()
                    .push(PlayerRule {
                        field,
                        when: When::parse(cell(&r, 2)),
                    });
            }
            PlayerFields { by_code }
        })
    }

    pub fn rules(&self, code: &str) -> &[PlayerRule] {
        self.by_code.get(code).map_or(&[], Vec::as_slice)
    }

    pub fn codes(&self) -> BTreeSet<&str> {
        self.by_code.keys().map(String::as_str).collect()
    }
}

#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Shape {
    /// One id in the field.
    Id,
    /// Comma-separated elements; the id is the leading digits of each.
    List,
    /// Comma-separated `a:b:c` elements; the id is the leading digits of the Nth colon part of each.
    Part(usize),
    /// A loot window's items: `itemIDxN`, `mxN` (coin, skipped), `c<currencyID>xN`.
    Lo,
    /// The field is the id of a name line (the line defines the name).
    Def,
}

pub struct RefRule {
    pub field: usize,
    pub kind: u8,
    pub when: Option<When>,
    pub shape: Shape,
}

/// Which field of which code holds which kind of id, and which codes define
/// the name of an id.
pub struct NameRefs {
    pub kinds: Vec<String>,
    refs: HashMap<String, Vec<RefRule>>,
    defs: HashMap<String, u8>,
}

impl NameRefs {
    pub fn get() -> &'static NameRefs {
        static N: OnceLock<NameRefs> = OnceLock::new();
        N.get_or_init(NameRefs::parse)
    }

    fn parse() -> NameRefs {
        let mut kinds: Vec<String> = Vec::new();
        let mut refs: HashMap<String, Vec<RefRule>> = HashMap::new();
        let mut defs = HashMap::new();
        for r in rows(NAME_REFS_TSV) {
            let kind_name = cell(&r, 2);
            let kind = match kinds.iter().position(|k| k == kind_name) {
                Some(i) => i as u8,
                None => {
                    kinds.push(kind_name.to_string());
                    (kinds.len() - 1) as u8
                }
            };
            let shape = match cell(&r, 4) {
                "list" => Shape::List,
                "lo" => Shape::Lo,
                "def" => Shape::Def,
                part => part
                    .strip_prefix("part")
                    .and_then(|n| n.parse::<usize>().ok())
                    .filter(|n| *n > 0)
                    .map_or(Shape::Id, Shape::Part),
            };
            let code = cell(&r, 0).to_string();
            if shape == Shape::Def {
                defs.insert(code, kind);
                continue;
            }
            let Some(field) = cell(&r, 1)
                .parse::<usize>()
                .ok()
                .and_then(|n| n.checked_sub(1))
            else {
                continue;
            };
            refs.entry(code).or_default().push(RefRule {
                field,
                kind,
                when: When::parse(cell(&r, 3)),
                shape,
            });
        }
        NameRefs { kinds, refs, defs }
    }

    pub fn rules(&self, code: &str) -> &[RefRule] {
        self.refs.get(code).map_or(&[], Vec::as_slice)
    }

    /// The kind a name line code defines.
    pub fn def_kind(&self, code: &str) -> Option<u8> {
        self.defs.get(code).copied()
    }

    pub fn kind_index(&self, name: &str) -> Option<u8> {
        self.kinds.iter().position(|k| k == name).map(|i| i as u8)
    }

    pub fn ref_codes(&self) -> BTreeSet<&str> {
        self.refs
            .keys()
            .chain(self.defs.keys())
            .map(String::as_str)
            .collect()
    }

    /// Every (kind, id) a line references, in the order its rules list them.
    pub fn collect(&self, code: &str, fields: &[&str], out: &mut impl FnMut(u8, u64)) {
        for rule in self.rules(code) {
            if rule.when.as_ref().is_some_and(|w| !w.holds(fields)) {
                continue;
            }
            let Some(value) = fields.get(rule.field) else {
                continue;
            };
            match rule.shape {
                Shape::Id => {
                    if let Some(id) = leading_id(value) {
                        out(rule.kind, id);
                    }
                }
                Shape::List => {
                    for el in value.split(',') {
                        if let Some(id) = leading_id(el) {
                            out(rule.kind, id);
                        }
                    }
                }
                Shape::Part(n) => {
                    for el in value.split(',') {
                        if let Some(id) = el.split(':').nth(n - 1).and_then(leading_id) {
                            out(rule.kind, id);
                        }
                    }
                }
                Shape::Lo => {
                    for el in value.split(',') {
                        if let Some(cur) = el.strip_prefix('c') {
                            if let (Some(id), Some(k)) =
                                (leading_id(cur), self.kind_index("currency"))
                            {
                                out(k, id);
                            }
                        } else if !el.starts_with('m')
                            && let Some(id) = leading_id(el)
                        {
                            out(rule.kind, id);
                        }
                    }
                }
                Shape::Def => {}
            }
        }
    }
}

/// The digits a value starts with, as an id. `?`, empty and text give none.
pub fn leading_id(s: &str) -> Option<u64> {
    let end = s.bytes().take_while(u8::is_ascii_digit).count();
    if end == 0 || end > 18 {
        return None;
    }
    s[..end].parse().ok()
}

/// Rows of the three tables, for `check-groups`.
pub fn raw_codes() -> (BTreeSet<String>, BTreeSet<String>) {
    let names = NameRefs::get()
        .ref_codes()
        .into_iter()
        .map(str::to_string)
        .collect();
    let players = PlayerFields::get()
        .codes()
        .into_iter()
        .map(str::to_string)
        .collect();
    (names, players)
}
