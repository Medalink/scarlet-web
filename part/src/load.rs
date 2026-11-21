//! Reading a character's payload folders (`payloads/*.scl`, `addons/payloads/*.scl`).

use crate::payload::{Ev, parse_scl};
use std::fs;
use std::path::{Path, PathBuf};

pub struct Loaded {
    pub character: String,
    pub main: Vec<Ev>,
    pub addon: Vec<Ev>,
    pub files: usize,
    /// Bytes of the .scl files as they are on disk.
    pub scl_bytes: u64,
    /// Bytes of the decompressed main-stream text.
    pub text_bytes: u64,
    pub addon_files: usize,
    pub addon_scl_bytes: u64,
    /// Files left out and why.
    pub skipped: Vec<String>,
}

fn scl_files(dir: &Path) -> Result<Vec<PathBuf>, String> {
    let mut out = Vec::new();
    let rd = fs::read_dir(dir).map_err(|e| format!("{}: {e}", dir.display()))?;
    for entry in rd {
        let p = entry.map_err(|e| e.to_string())?.path();
        if p.extension().and_then(|s| s.to_str()) == Some("scl") {
            out.push(p);
        }
    }
    out.sort();
    Ok(out)
}

/// Read every payload of one character, files in name order. The main stream's
/// header names the character; a file with another name or no header is skipped.
pub fn load_dir(payloads: &Path, addons: Option<&Path>) -> Result<Loaded, String> {
    let mut loaded = Loaded {
        character: String::new(),
        main: Vec::new(),
        addon: Vec::new(),
        files: 0,
        scl_bytes: 0,
        text_bytes: 0,
        addon_files: 0,
        addon_scl_bytes: 0,
        skipped: Vec::new(),
    };
    for path in scl_files(payloads)? {
        let bytes = fs::read(&path).map_err(|e| format!("{}: {e}", path.display()))?;
        let name = path
            .file_name()
            .map_or_else(String::new, |n| n.to_string_lossy().into_owned());
        match parse_scl(&bytes, "scarlet") {
            Ok(p) if loaded.character.is_empty() || p.character == loaded.character => {
                loaded.character = p.character;
                loaded.text_bytes += p.text_len as u64;
                loaded.main.extend(p.events);
                loaded.files += 1;
                loaded.scl_bytes += bytes.len() as u64;
            }
            Ok(p) => loaded.skipped.push(format!(
                "{name}: header names another character ({})",
                p.character
            )),
            Err(e) => loaded.skipped.push(format!("{name}: {e}")),
        }
    }
    if loaded.character.is_empty() {
        return Err(format!("{}: no readable payload", payloads.display()));
    }
    if let Some(dir) = addons.filter(|d| d.is_dir()) {
        for path in scl_files(dir)? {
            let bytes = fs::read(&path).map_err(|e| format!("{}: {e}", path.display()))?;
            let name = path
                .file_name()
                .map_or_else(String::new, |n| n.to_string_lossy().into_owned());
            match parse_scl(&bytes, "scarletaddons") {
                Ok(p) if p.character == loaded.character => {
                    loaded.addon.extend(p.events);
                    loaded.addon_files += 1;
                    loaded.addon_scl_bytes += bytes.len() as u64;
                }
                Ok(p) => loaded.skipped.push(format!(
                    "addons/{name}: header names another character ({})",
                    p.character
                )),
                Err(e) => loaded.skipped.push(format!("addons/{name}: {e}")),
            }
        }
    }
    Ok(loaded)
}
