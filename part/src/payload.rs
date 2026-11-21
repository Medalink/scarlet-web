//! Reading the companion's payload text: a header line, then
//! `<epoch ms>\t<code>\t<fields>` for the main stream and
//! `<epoch ms>\t<key>\t<code>\t<fields>` for the addon stream.

use std::io::Read;

/// One recorded line without its time: `code\tfield\tfield` (main stream) or
/// `key\tcode\tfield` (addon stream).
#[derive(Clone, Debug)]
pub struct Ev {
    pub ms: i64,
    pub text: String,
}

impl Ev {
    pub fn code(&self) -> &str {
        self.text.split('\t').next().unwrap_or("")
    }
}

pub struct Payload {
    pub character: String,
    pub events: Vec<Ev>,
}

/// Parse decoded payload text. `magic` is `scarlet` or `scarletaddons`. Lines
/// that do not start with a decimal time are skipped, so a cut tail is harmless.
pub fn parse(text: &str, magic: &str) -> Result<Payload, String> {
    let mut lines = text.split('\n');
    let header = lines.next().unwrap_or("").trim_end_matches('\r');
    let mut h = header.split('\t');
    if h.next() != Some(magic) || h.next() != Some("1") {
        return Err(format!("not a {magic} payload (bad header)"));
    }
    let character = h.next().unwrap_or("").trim().to_string();
    if character.is_empty() {
        return Err("payload header has no character".into());
    }
    let mut events = Vec::new();
    for line in lines {
        let line = line.trim_end_matches('\r');
        let Some((ms, rest)) = line.split_once('\t') else {
            continue;
        };
        let Ok(ms) = ms.parse::<i64>() else {
            continue;
        };
        if rest.is_empty() {
            continue;
        }
        events.push(Ev {
            ms,
            text: rest.to_string(),
        });
    }
    Ok(Payload { character, events })
}

/// Decompress a `.scl` file's bytes (one zstd frame) and parse it.
pub fn parse_scl(bytes: &[u8], magic: &str) -> Result<Payload, String> {
    let mut dec = zstd::stream::read::Decoder::new(bytes).map_err(|e| e.to_string())?;
    dec.window_log_max(27).map_err(|e| e.to_string())?;
    let mut text = String::new();
    dec.take(1 << 30)
        .read_to_string(&mut text)
        .map_err(|e| e.to_string())?;
    parse(&text, magic)
}
