//! The parts every virtual character is made from: real parts of real recordings with the character's
//! name cut out, shifted to new times and renamed (docs/MEASUREMENTS.md, Load tests).

use scarlet_part::build::{Options, PART_MAGIC, build_parts, parse_header, part_id};
use scarlet_part::load::load_dir;
use scarlet_part::wire::{decode_batch, encode_batch};
use sha2::{Digest, Sha256};
use std::collections::BTreeMap;
use std::fs;
use std::path::Path;

const MARK: &str = "@NAME@";
pub const DAY: i64 = 86_400;

pub struct Part {
    pub id: [u8; 32],
    pub session: i64,
    pub group: String,
    pub lines: usize,
    pub text: String,
}

pub struct Session {
    pub key: i64,
    pub seconds: i64,
    pub parts: Vec<(String, String)>,
}

pub struct Source {
    pub label: String,
    pub sessions: Vec<Session>,
}

pub struct Template {
    pub sources: Vec<Source>,
}

pub struct Plan {
    pub name: String,
    pub parts: Vec<Part>,
    pub sessions: usize,
    pub seconds: i64,
}

pub struct Rng(u64);

impl Rng {
    pub fn new(seed: u64) -> Rng {
        Rng(seed)
    }

    pub fn next(&mut self) -> u64 {
        self.0 = self.0.wrapping_add(0x9E37_79B9_7F4A_7C15);
        let mut z = self.0;
        z = (z ^ (z >> 30)).wrapping_mul(0xBF58_476D_1CE4_E5B9);
        z = (z ^ (z >> 27)).wrapping_mul(0x94D0_49BB_1331_11EB);
        z ^ (z >> 31)
    }

    pub fn below(&mut self, n: u64) -> u64 {
        if n == 0 { 0 } else { self.next() % n }
    }
}

fn replace_ascii_ci(text: &str, needle: &str, with: &str) -> String {
    let (t, n) = (text.as_bytes(), needle.as_bytes());
    let mut out = String::with_capacity(text.len());
    let mut i = 0;
    let mut copied = 0;
    while i + n.len() <= t.len() {
        if t[i..i + n.len()].eq_ignore_ascii_case(n)
            && text.is_char_boundary(i)
            && text.is_char_boundary(i + n.len())
        {
            out.push_str(&text[copied..i]);
            out.push_str(with);
            i += n.len();
            copied = i;
        } else {
            i += 1;
        }
    }
    out.push_str(&text[copied..]);
    out
}

fn is_word(b: u8) -> bool {
    b.is_ascii_alphanumeric() || b == b'\''
}

fn mask_players(text: &str) -> String {
    let t = text.as_bytes();
    let mut out: Vec<u8> = Vec::with_capacity(t.len());
    let mut copied = 0;
    for i in 0..t.len() {
        if t[i] != b'-' || i < copied {
            continue;
        }
        let mut l = i;
        while l > copied && (t[l - 1].is_ascii_lowercase() || t[l - 1] == b'\'') {
            l -= 1;
        }
        if l == 0 || l == copied || !t[l - 1].is_ascii_uppercase() {
            continue;
        }
        l -= 1;
        let mut r = i + 1;
        if r >= t.len() || !t[r].is_ascii_uppercase() {
            continue;
        }
        r += 1;
        while r < t.len() && (t[r].is_ascii_lowercase() || t[r] == b'\'') {
            r += 1;
        }
        let before_ok = l == 0 || !is_word(t[l - 1]);
        let after_ok = r >= t.len() || !is_word(t[r]);
        if !before_ok || !after_ok || i - l < 2 || r - i < 4 {
            continue;
        }
        let d = Sha256::digest(&t[l..r]);
        out.extend_from_slice(&t[copied..l]);
        out.extend_from_slice(format!("Pl{:02x}{:02x}{:02x}-Load", d[0], d[1], d[2]).as_bytes());
        copied = r;
    }
    out.extend_from_slice(&t[copied..]);
    String::from_utf8(out).unwrap_or_else(|_| text.to_string())
}

fn scrub(text: &str, name: &str, marker: &str) -> Result<String, String> {
    let surname = name.split_once('-').map_or("", |(_, s)| s);
    let cut = replace_ascii_ci(text, name, marker);
    let cut = mask_players(&cut);
    let mut out = String::with_capacity(cut.len());
    for line in cut.split_inclusive('\n') {
        let mut f = line.trim_end_matches('\n').splitn(4, '\t');
        let (dt, code, pseudonym, real) = (f.next(), f.next(), f.next(), f.next());
        match (code, pseudonym, real) {
            (Some("pn"), Some(p), Some(_)) => {
                let tail = p.trim_start_matches('~');
                out.push_str(&format!(
                    "{}\tpn\t{p}\tPn{}\n",
                    dt.unwrap_or("0"),
                    &tail[..tail.len().min(6)]
                ));
            }
            _ => out.push_str(line),
        }
    }
    let lower = out.to_ascii_lowercase();
    if lower.contains(&name.to_ascii_lowercase())
        || (!surname.is_empty() && lower.contains(&surname.to_ascii_lowercase()))
    {
        return Err("a character name or its surname is still in a part after the cut".into());
    }
    Ok(out)
}

/// Build the parts of the recorded characters under `root` (one folder each, labelled A, B, C) with every group on, cut out
/// the character's name and every other player's real name, and write them as one wire batch.
pub fn prepare(root: &Path, folders: &[String], out: &Path) -> Result<(), String> {
    let key: [u8; 32] = Sha256::digest(b"scarlet-loadgen template").into();
    let mut texts: Vec<String> = Vec::new();
    for (i, folder) in folders.iter().enumerate() {
        let label = char::from(b'A' + u8::try_from(i).map_err(|_| "too many folders")?);
        let loaded = load_dir(&root.join(folder).join("payloads"), None)?;
        let name = loaded.character.clone();
        let built = build_parts(&name, loaded.main, Vec::new(), &Options::all(key));
        eprintln!(
            "loadgen: character {label}: {} parts in {} sessions",
            built.parts.len(),
            built.stats.sessions_closed
        );
        for part in &built.parts {
            texts.push(scrub(&part.text, &name, &format!("{MARK}{label}"))?);
        }
    }
    let refs: Vec<&str> = texts.iter().map(String::as_str).collect();
    let bytes = encode_batch(&refs)?;
    fs::write(out, &bytes).map_err(|e| format!("{}: {e}", out.display()))?;
    eprintln!(
        "loadgen: wrote {} parts, {} bytes, to {}",
        texts.len(),
        bytes.len(),
        out.display()
    );
    Ok(())
}

fn part_seconds(text: &str) -> i64 {
    let mut total = 0i64;
    for line in text.split('\n').skip(1) {
        if let Some(dt) = line.split('\t').next().and_then(|d| d.parse::<i64>().ok()) {
            total += dt;
        }
    }
    total / 1000
}

pub fn load(path: &Path) -> Result<Template, String> {
    let bytes = fs::read(path).map_err(|e| format!("{}: {e}", path.display()))?;
    let mut by_source: BTreeMap<String, BTreeMap<i64, Session>> = BTreeMap::new();
    for part in decode_batch(&bytes, None)? {
        let (character, session, group) =
            parse_header(&part.text).ok_or("a template part has no header")?;
        let label = character
            .strip_prefix(MARK)
            .ok_or("a template part is not marked")?
            .to_string();
        let entry = by_source
            .entry(label)
            .or_default()
            .entry(session)
            .or_insert_with(|| Session {
                key: session,
                seconds: 0,
                parts: Vec::new(),
            });
        entry.seconds = entry.seconds.max(part_seconds(&part.text));
        entry.parts.push((group.to_string(), part.text));
    }
    let sources = by_source
        .into_iter()
        .map(|(label, sessions)| Source {
            label,
            sessions: sessions.into_values().collect(),
        })
        .collect();
    Ok(Template { sources })
}

impl Source {
    pub fn hours(&self) -> f64 {
        self.sessions.iter().map(|s| s.seconds).sum::<i64>() as f64 / 3600.0
    }

    pub fn span_days(&self) -> f64 {
        match (self.sessions.first(), self.sessions.last()) {
            (Some(a), Some(b)) => (b.key + b.seconds - a.key) as f64 / DAY as f64,
            _ => 0.0,
        }
    }
}

fn shifted(text: &str, marker: &str, name: &str, session: i64, group: &str) -> String {
    let body = text.split_once('\n').map_or("", |(_, rest)| rest);
    let mut out = format!("{PART_MAGIC}\t1\t{name}\t{session}\t{group}\n");
    out.push_str(&body.replace(marker, name));
    out
}

/// The history of one virtual character: consecutive sessions of `source` from a random place, wrapping round with the
/// times moved on, until `hours` of play are in it, then the whole history moved by whole days so it ends within the last
/// `spread_days` days before `now`. Whole days keep the hour of day of every session.
pub fn instantiate(
    source: &Source,
    name: &str,
    hours: f64,
    spread_days: i64,
    now: i64,
    rng: &mut Rng,
) -> Plan {
    let n = source.sessions.len();
    let first = &source.sessions[0];
    let last = &source.sessions[n - 1];
    let cycle = ((last.key + last.seconds - first.key) / DAY + 2) * DAY;
    let want = (hours * 3600.0) as i64;
    let start = rng.below(n as u64) as usize;
    let mut picked: Vec<(&Session, i64)> = Vec::new();
    let mut seconds = 0;
    let mut step = 0usize;
    while seconds < want || picked.is_empty() {
        let session = &source.sessions[(start + step) % n];
        let wraps = ((start + step) / n) as i64;
        picked.push((session, wraps * cycle));
        seconds += session.seconds.max(1);
        step += 1;
    }
    let end = picked.last().map_or(0, |(s, add)| s.key + add + s.seconds);
    let back = rng.below(spread_days.max(1) as u64) as i64;
    let target_end = now - 3600 - back * DAY;
    let delta = (target_end - end).div_euclid(DAY) * DAY;
    let marker = format!("{MARK}{}", source.label);
    let mut parts = Vec::new();
    for (session, add) in &picked {
        let key = session.key + add + delta;
        for (group, text) in &session.parts {
            let text = shifted(text, &marker, name, key, group);
            parts.push(Part {
                id: part_id(&text),
                session: key,
                group: group.clone(),
                lines: text.split('\n').skip(1).filter(|l| !l.is_empty()).count(),
                text,
            });
        }
    }
    Plan {
        name: name.to_string(),
        parts,
        sessions: picked.len(),
        seconds,
    }
}
