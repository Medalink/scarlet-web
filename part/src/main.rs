use scarlet_part::build::{Options, build_parts};
use scarlet_part::load::load_dir;
use scarlet_part::pseudo::{hex, unhex};
use scarlet_part::tables::Groups;
use scarlet_part::wire;
use serde_json::json;
mod measure;

use std::collections::{BTreeMap, BTreeSet, HashSet};
use std::ffi::OsString;
use std::fs;
use std::path::{Path, PathBuf};
use std::process::ExitCode;

const USAGE: &str = "usage:
  scarlet-part check-groups <FORMAT.md>
  scarlet-part groups --json
  scarlet-part pack --out-dir <dir> --payloads <dir> [--addon-payloads <dir>] [--groups a,b,c] [--name-key-hex <64 hex>]
  scarlet-part batch --parts-dir <dir> --out <file> [--split]
  scarlet-part unbatch --in <file> --out-dir <dir> [--stored] [--accept <id file>] [--max-inflated <bytes>] [--room <units> [--part-charge <units>]]
  scarlet-part inflate < <zstd frame> > <bytes>
  scarlet-part decode <part file>... | --parts-dir <dir> [--session <n>] | --stdin [--out <file>] [--imports | --fishing | --counts]
  scarlet-part check-overview --parts-dir <dir> --overview <overview.json>
  scarlet-part decoder-version
  scarlet-part reconcile [--explain] < <json: kind, now, character, rows, others>
  scarlet-part reconcile-version
  scarlet-part measure --root <out dir> --folders a,b,c [--runs 5] [--dump-text <dir>]
  scarlet-part parse-bench <text file> [--runs 7]";

type Flags = BTreeMap<String, String>;

/// `--name value` pairs and bare words; `bools` are flags without a value.
fn flags(
    args: Vec<OsString>,
    names: &[&str],
    bools: &[&str],
) -> Result<(Flags, Vec<String>), String> {
    use lexopt::prelude::*;
    let mut p = lexopt::Parser::from_args(args);
    let (mut map, mut rest) = (Flags::new(), Vec::new());
    while let Some(arg) = p.next().map_err(|e| e.to_string())? {
        let long = match &arg {
            Long(n) => Some(n.to_string()),
            _ => None,
        };
        match (arg, long) {
            (_, Some(n)) if names.contains(&n.as_str()) => {
                let v = p.value().map_err(|e| e.to_string())?;
                map.insert(n, v.string().map_err(|e| e.to_string())?);
            }
            (_, Some(n)) if bools.contains(&n.as_str()) => {
                map.insert(n, String::new());
            }
            (Value(v), _) => rest.push(v.string().map_err(|e| e.to_string())?),
            (other, _) => return Err(other.unexpected().to_string()),
        }
    }
    Ok((map, rest))
}

fn need<'a>(f: &'a Flags, name: &str) -> Result<&'a str, String> {
    f.get(name)
        .map(String::as_str)
        .ok_or_else(|| format!("missing --{name}"))
}

fn run(mut args: Vec<OsString>) -> Result<(), String> {
    if args.is_empty() {
        return Err(USAGE.into());
    }
    let cmd = args.remove(0).to_string_lossy().into_owned();
    match cmd.as_str() {
        "check-groups" => {
            let (_, rest) = flags(args, &[], &[])?;
            let path = rest.first().ok_or(USAGE)?;
            let text = fs::read_to_string(path).map_err(|e| format!("{path}: {e}"))?;
            let problems = scarlet_part::groups::check(&text);
            if problems.is_empty() {
                println!(
                    "groups ok: {} codes",
                    scarlet_part::groups::format_codes(&text).len()
                );
                return Ok(());
            }
            for p in &problems {
                println!("{p}");
            }
            Err(format!("{} problem(s)", problems.len()))
        }
        "groups" => {
            flags(args, &[], &["json"])?;
            let v = scarlet_part::groups::json();
            println!(
                "{}",
                serde_json::to_string_pretty(&v).map_err(|e| e.to_string())?
            );
            Ok(())
        }
        "pack" => pack(args),
        "batch" => batch(args),
        "unbatch" => unbatch(args),
        "inflate" => inflate(),
        "decode" => decode(args),
        "decoder-version" => {
            flags(args, &[], &[])?;
            println!("{}", scarlet_part::decode::DECODER_VERSION);
            Ok(())
        }
        "reconcile" => reconcile(args),
        "reconcile-version" => {
            flags(args, &[], &[])?;
            println!("{}", scarlet_part::reconcile::RECONCILE_VERSION);
            Ok(())
        }
        "check-overview" => check_overview(args),
        "measure" => {
            let (f, _) = flags(args, &["root", "folders", "runs", "dump-text"], &[])?;
            let folders: Vec<String> = need(&f, "folders")?
                .split(',')
                .map(str::to_string)
                .collect();
            let runs = f
                .get("runs")
                .map_or(Ok(5), |r| r.parse())
                .map_err(|_| "bad --runs")?;
            measure::measure(
                Path::new(need(&f, "root")?),
                &folders,
                runs,
                f.get("dump-text").map(Path::new),
            )
        }
        "parse-bench" => {
            let (f, rest) = flags(args, &["runs"], &[])?;
            let runs = f
                .get("runs")
                .map_or(Ok(7), |r| r.parse())
                .map_err(|_| "bad --runs")?;
            measure::parse_bench(Path::new(rest.first().ok_or(USAGE)?), runs)
        }
        _ => Err(USAGE.into()),
    }
}

fn reconcile(args: Vec<OsString>) -> Result<(), String> {
    use std::io::Read;
    let (f, _) = flags(args, &[], &["explain"])?;
    let mut raw = String::new();
    std::io::stdin()
        .read_to_string(&mut raw)
        .map_err(|e| e.to_string())?;
    let value: serde_json::Value = serde_json::from_str(&raw).map_err(|e| e.to_string())?;
    let input = scarlet_part::reconcile::parse(&value)?;
    let outcomes = scarlet_part::reconcile::reconcile(&input);
    if f.contains_key("explain") {
        print!("{}", scarlet_part::reconcile::explain(&input, &outcomes));
        return Ok(());
    }
    println!(
        "{}",
        serde_json::to_string(&scarlet_part::reconcile::to_json(&outcomes))
            .map_err(|e| e.to_string())?
    );
    Ok(())
}

fn parse_name_key(f: &Flags) -> Result<[u8; 32], String> {
    match f.get("name-key-hex") {
        Some(h) => {
            unhex::<32>(h).ok_or_else(|| "--name-key-hex needs 64 hex characters".to_string())
        }
        None => {
            eprintln!(
                "scarlet-part: no --name-key-hex, using the all-zero key (fine for measuring, never for sharing)"
            );
            Ok([0u8; 32])
        }
    }
}

fn parse_groups(f: &Flags, key: [u8; 32]) -> Result<Options, String> {
    let mut opts = Options::all(key);
    if let Some(list) = f.get("groups") {
        let groups = Groups::get();
        let mut on = BTreeSet::new();
        for g in list.split(',').map(str::trim).filter(|g| !g.is_empty()) {
            if groups.index(g).is_none() {
                return Err(format!("unknown group {g}"));
            }
            on.insert(g.to_string());
        }
        opts.on = on;
    }
    Ok(opts)
}

fn pack(args: Vec<OsString>) -> Result<(), String> {
    let (f, _) = flags(
        args,
        &[
            "out-dir",
            "payloads",
            "addon-payloads",
            "groups",
            "name-key-hex",
        ],
        &[],
    )?;
    let out = PathBuf::from(need(&f, "out-dir")?);
    let loaded = load_dir(
        Path::new(need(&f, "payloads")?),
        f.get("addon-payloads").map(Path::new),
    )?;
    let opts = parse_groups(&f, parse_name_key(&f)?)?;
    for s in &loaded.skipped {
        eprintln!("scarlet-part: skipped {s}");
    }
    let built = build_parts(&loaded.character, loaded.main, loaded.addon, &opts);
    fs::create_dir_all(&out).map_err(|e| format!("{}: {e}", out.display()))?;
    let mut entries = Vec::new();
    for p in &built.parts {
        let id = hex(&p.id);
        fs::write(out.join(format!("{id}.part")), p.text.as_bytes()).map_err(|e| e.to_string())?;
        entries.push(json!({
            "id": id,
            "session": p.session,
            "group": p.group,
            "lines": p.lines,
            "raw_bytes": p.text.len(),
        }));
    }
    let digest = wire::set_digest(built.parts.iter().map(|p| &p.id));
    let manifest = json!({
        "character": loaded.character,
        "parts": entries,
        "set_digest": hex(&digest),
    });
    fs::write(
        out.join("manifest.json"),
        serde_json::to_string_pretty(&manifest).map_err(|e| e.to_string())?,
    )
    .map_err(|e| e.to_string())?;
    let s = &built.stats;
    eprintln!(
        "{} parts from {} closed sessions ({} open left out); {} lines in, {} sent, {} of unknown code and {} of groups that are off dropped, {} held back for the next session",
        built.parts.len(),
        s.sessions_closed,
        s.sessions_open,
        s.lines_in,
        s.lines_sent,
        s.dropped_unknown,
        s.dropped_off,
        s.held_back
    );
    Ok(())
}

fn read_parts(dir: &Path) -> Result<Vec<(String, String)>, String> {
    let mut names: Vec<String> = Vec::new();
    for e in fs::read_dir(dir).map_err(|e| format!("{}: {e}", dir.display()))? {
        let name = e
            .map_err(|e| e.to_string())?
            .file_name()
            .to_string_lossy()
            .into_owned();
        if name.ends_with(".part") {
            names.push(name);
        }
    }
    names.sort();
    let mut out = Vec::new();
    for n in names {
        let text = fs::read_to_string(dir.join(&n)).map_err(|e| format!("{n}: {e}"))?;
        let id = hex(&scarlet_part::build::part_id(&text));
        if n != format!("{id}.part") {
            return Err(format!("{n}: the text hashes to {id}"));
        }
        out.push((id, text));
    }
    Ok(out)
}

fn batch(args: Vec<OsString>) -> Result<(), String> {
    let (f, _) = flags(args, &["parts-dir", "out"], &["split"])?;
    let parts = read_parts(Path::new(need(&f, "parts-dir")?))?;
    let texts: Vec<&str> = parts.iter().map(|(_, t)| t.as_str()).collect();
    let out = need(&f, "out")?;
    if f.contains_key("split") {
        let reqs = wire::split_requests(&texts)?;
        for (i, (body, idx)) in reqs.iter().enumerate() {
            let path = format!("{out}.{i}");
            fs::write(&path, body).map_err(|e| format!("{path}: {e}"))?;
            eprintln!("{path}: {} parts, {} bytes", idx.len(), body.len());
        }
        return Ok(());
    }
    let body = wire::encode_batch(&texts)?;
    if body.len() > wire::MAX_COMPRESSED || texts.len() > wire::MAX_PARTS {
        return Err(format!(
            "{} parts, {} bytes: over the request limits, use --split",
            texts.len(),
            body.len()
        ));
    }
    fs::write(out, &body).map_err(|e| format!("{out}: {e}"))?;
    eprintln!("{out}: {} parts, {} bytes", texts.len(), body.len());
    Ok(())
}

/// One zstd frame from stdin to stdout under the protocol limits (the server reads manifests with it).
fn inflate() -> Result<(), String> {
    use std::io::{Read, Write};
    let mut bytes = Vec::new();
    std::io::stdin()
        .take(wire::MAX_COMPRESSED as u64 + 1)
        .read_to_end(&mut bytes)
        .map_err(|e| e.to_string())?;
    let raw = wire::inflate(&bytes)?;
    std::io::stdout()
        .lock()
        .write_all(&raw)
        .map_err(|e| e.to_string())
}

fn unbatch(args: Vec<OsString>) -> Result<(), String> {
    let (f, _) = flags(
        args,
        &[
            "in",
            "out-dir",
            "accept",
            "max-inflated",
            "room",
            "part-charge",
        ],
        &["stored"],
    )?;
    let path = need(&f, "in")?;
    let bytes = fs::read(path).map_err(|e| format!("{path}: {e}"))?;
    let max = f
        .get("max-inflated")
        .map_or(Ok(wire::MAX_DECOMPRESSED), |v| {
            v.parse().map_err(|_| "bad --max-inflated".to_string())
        })?;
    let accept = match f.get("accept") {
        Some(path) => Some(read_ids(Path::new(path))?),
        None => None,
    };
    let parts = wire::decode_batch(&bytes, max)?;
    let out = PathBuf::from(need(&f, "out-dir")?);
    fs::create_dir_all(&out).map_err(|e| e.to_string())?;
    let number = |name: &str| -> Result<Option<u64>, String> {
        f.get(name)
            .map(|v| v.parse().map_err(|_| format!("bad --{name}")))
            .transpose()
    };
    let part_charge = number("part-charge")?.unwrap_or(0);
    let accepted = |id: &[u8; 32]| accept.as_ref().is_none_or(|a| a.contains(id));
    let cost = parts
        .iter()
        .filter(|p| accepted(&p.id))
        .map(|p| {
            (p.text.len() as u64)
                .saturating_add(p.text.lines().count().saturating_sub(1) as u64)
                .saturating_add(part_charge)
        })
        .fold(0u64, u64::saturating_add);
    let write = number("room")?.is_none_or(|room| cost <= room);
    for p in &parts {
        let stored = f.contains_key("stored");
        if write && accepted(&p.id) {
            let id = hex(&p.id);
            if !stored {
                fs::write(out.join(format!("{id}.part")), p.text.as_bytes())
                    .map_err(|e| e.to_string())?;
            } else {
                fs::write(out.join(format!("{id}.zst")), wire::compress_part(&p.text)?)
                    .map_err(|e| e.to_string())?;
            }
        }
        if stored {
            let (character, session, group) =
                scarlet_part::build::parse_header(&p.text).ok_or("a part has no valid header")?;
            println!(
                "{}",
                json!({
                    "id": hex(&p.id),
                    "character": character,
                    "session": session,
                    "group": group,
                    "lines": p.text.lines().count().saturating_sub(1),
                    "raw_bytes": p.text.len(),
                })
            );
        }
    }
    eprintln!("{} parts written to {}", parts.len(), out.display());
    Ok(())
}

fn read_ids(path: &Path) -> Result<HashSet<[u8; 32]>, String> {
    fs::read_to_string(path)
        .map_err(|e| format!("{}: {e}", path.display()))?
        .lines()
        .filter(|line| !line.trim().is_empty())
        .map(|line| {
            unhex::<32>(line.trim()).ok_or_else(|| format!("{}: not a part id", path.display()))
        })
        .collect()
}

/// A part file: plain text, or the storage form (zstd).
fn read_part_file(path: &Path) -> Result<String, String> {
    let bytes = fs::read(path).map_err(|e| format!("{}: {e}", path.display()))?;
    if bytes.starts_with(&[0x28, 0xB5, 0x2F, 0xFD]) {
        return wire::read_stored(&bytes);
    }
    String::from_utf8(bytes).map_err(|_| format!("{}: not UTF-8", path.display()))
}

/// Parts grouped by session, in session order.
fn by_session(texts: Vec<String>) -> Result<BTreeMap<i64, Vec<String>>, String> {
    let mut map: BTreeMap<i64, Vec<String>> = BTreeMap::new();
    for t in texts {
        let (_, session, _) =
            scarlet_part::build::parse_header(&t).ok_or("a part has no valid header")?;
        map.entry(session).or_default().push(t);
    }
    Ok(map)
}

fn write_rows(out: Option<&String>, rows: &str) -> Result<(), String> {
    use std::io::Write;
    match out {
        Some(path) => fs::write(path, rows).map_err(|e| format!("{path}: {e}")),
        None => std::io::stdout()
            .lock()
            .write_all(rows.as_bytes())
            .map_err(|e| e.to_string()),
    }
}

fn decode(args: Vec<OsString>) -> Result<(), String> {
    use std::io::{Read, Write};
    let (f, files) = flags(
        args,
        &["parts-dir", "session", "out"],
        &["stdin", "imports", "fishing", "counts"],
    )?;
    let mut texts: Vec<String> = Vec::new();
    if f.contains_key("stdin") {
        let mut raw = Vec::new();
        std::io::stdin()
            .read_to_end(&mut raw)
            .map_err(|e| e.to_string())?;
        let mut pos = 0;
        while pos < raw.len() {
            let head = raw.get(pos..pos + 4).ok_or("cut length prefix")?;
            let len = u32::from_le_bytes([head[0], head[1], head[2], head[3]]) as usize;
            pos += 4;
            let body = raw
                .get(pos..pos + len)
                .ok_or("a length prefix runs past the end")?;
            pos += len;
            texts.push(wire::read_stored(body)?);
        }
    }
    if let Some(dir) = f.get("parts-dir") {
        let mut names: Vec<PathBuf> = fs::read_dir(dir)
            .map_err(|e| format!("{dir}: {e}"))?
            .filter_map(|e| e.ok().map(|e| e.path()))
            .filter(|p| p.extension().is_some_and(|x| x == "part"))
            .collect();
        names.sort();
        for p in names {
            texts.push(read_part_file(&p)?);
        }
    }
    for file in files {
        texts.push(read_part_file(Path::new(&file))?);
    }
    if f.contains_key("fishing") {
        let refs: Vec<&str> = texts.iter().map(String::as_str).collect();
        let mut out = String::new();
        scarlet_part::fishing::decode_fishing(&refs, &mut out)?;
        return write_rows(f.get("out"), &out);
    }
    if f.contains_key("counts") {
        let refs: Vec<&str> = texts.iter().map(String::as_str).collect();
        let mut out = String::new();
        scarlet_part::counts::decode_counts(&refs, &mut out)?;
        return write_rows(f.get("out"), &out);
    }
    if f.contains_key("imports") {
        let refs: Vec<&str> = texts.iter().map(String::as_str).collect();
        let mut out = String::new();
        scarlet_part::imports::decode_imports(&refs, &mut out)?;
        return write_rows(f.get("out"), &out);
    }
    let want: Option<i64> = f
        .get("session")
        .map(|s| s.parse())
        .transpose()
        .map_err(|_| "bad --session")?;
    let sessions = by_session(texts)?;
    let sink = f.get("out");
    let mut out = String::new();
    let stdout = std::io::stdout();
    let mut lock = stdout.lock();
    for (s, parts) in &sessions {
        if want.is_some_and(|w| w != *s) {
            continue;
        }
        let refs: Vec<&str> = parts.iter().map(String::as_str).collect();
        out.clear();
        let summary = scarlet_part::decode::decode_session(&refs, &mut out)?;
        if summary.skipped > 0 {
            eprintln!("scarlet-part: {} lines skipped", summary.skipped);
        }
        match sink {
            Some(path) => {
                let mut file = fs::OpenOptions::new()
                    .create(true)
                    .append(true)
                    .open(path)
                    .map_err(|e| format!("{path}: {e}"))?;
                file.write_all(out.as_bytes()).map_err(|e| e.to_string())?;
            }
            None => lock.write_all(out.as_bytes()).map_err(|e| e.to_string())?,
        }
    }
    Ok(())
}

fn check_overview(args: Vec<OsString>) -> Result<(), String> {
    let (f, _) = flags(args, &["parts-dir", "overview"], &[])?;
    let dir = need(&f, "parts-dir")?;
    let mut texts = Vec::new();
    for e in fs::read_dir(dir).map_err(|e| format!("{dir}: {e}"))? {
        let p = e.map_err(|e| e.to_string())?.path();
        if p.extension().is_some_and(|x| x == "part") {
            texts.push(read_part_file(&p)?);
        }
    }
    let mut decoded = Vec::new();
    for (_, parts) in by_session(texts)? {
        let refs: Vec<&str> = parts.iter().map(String::as_str).collect();
        let mut out = String::new();
        let summary = scarlet_part::decode::decode_session(&refs, &mut out)?;
        if summary.skipped > 0 {
            eprintln!("scarlet-part: {} lines skipped", summary.skipped);
        }
        decoded.push(out);
    }
    let overview = fs::read_to_string(need(&f, "overview")?).map_err(|e| e.to_string())?;
    let rep = scarlet_part::compare::compare(&decoded, &overview)?;
    println!(
        "sessions decoded: {}, compared with the overview: {}",
        rep.sessions, rep.compared
    );
    for (name, (ok, bad)) in &rep.fields {
        println!("{name}: {ok} match, {} differ", bad.len());
        for b in bad.iter().take(12) {
            println!("  {b}");
        }
        if bad.len() > 12 {
            println!("  ... {} more", bad.len() - 12);
        }
    }
    println!("in decoder only: {:?}", rep.missing_in_overview);
    println!("in overview only: {:?}", rep.missing_in_parts);
    for n in &rep.notes {
        println!("note: {n}");
    }
    Ok(())
}

fn main() -> ExitCode {
    match run(std::env::args_os().skip(1).collect()) {
        Ok(()) => ExitCode::SUCCESS,
        Err(e) => {
            eprintln!("scarlet-part: {e}");
            ExitCode::FAILURE
        }
    }
}
