use scarlet_part::build::{Options, build_parts};
use scarlet_part::load::load_dir;
use scarlet_part::pseudo::{hex, unhex};
use scarlet_part::tables::Groups;
use scarlet_part::wire;
use serde_json::json;
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
  scarlet-part unbatch --in <file> --out-dir <dir>";

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
        _ => Err(USAGE.into()),
    }
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
        "{} parts from {} closed sessions ({} open left out); {} lines in, {} sent, {} of unknown code and {} of groups that are off dropped",
        built.parts.len(),
        s.sessions_closed,
        s.sessions_open,
        s.lines_in,
        s.lines_sent,
        s.dropped_unknown,
        s.dropped_off
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

fn unbatch(args: Vec<OsString>) -> Result<(), String> {
    let (f, _) = flags(args, &["in", "out-dir"], &[])?;
    let path = need(&f, "in")?;
    let bytes = fs::read(path).map_err(|e| format!("{path}: {e}"))?;
    let parts = wire::decode_batch(&bytes, None::<&HashSet<[u8; 32]>>)?;
    let out = PathBuf::from(need(&f, "out-dir")?);
    fs::create_dir_all(&out).map_err(|e| e.to_string())?;
    for p in &parts {
        fs::write(out.join(format!("{}.part", hex(&p.id))), p.text.as_bytes())
            .map_err(|e| e.to_string())?;
    }
    eprintln!("{} parts written to {}", parts.len(), out.display());
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
