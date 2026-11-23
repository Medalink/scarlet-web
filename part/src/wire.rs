//! The wire batch, the storage form of one part and the set digest
//! (docs/DESIGN.md, Upload protocol).

use crate::build::{PART_MAGIC, part_id};
use std::io::{Read, Write};

pub const MAX_COMPRESSED: usize = 4 << 20;
pub const MAX_DECOMPRESSED: usize = 64 << 20;
pub const MAX_PARTS: usize = 20_000;
pub const WINDOW_LOG: u32 = 23;
pub const LEVEL: i32 = 19;

/// A request body and the indexes of the parts it holds.
pub type Request = (Vec<u8>, Vec<usize>);

pub struct WirePart {
    pub id: [u8; 32],
    pub text: String,
}

fn raw_batch(parts: &[&str]) -> Result<Vec<u8>, String> {
    let total: usize = parts.iter().map(|p| p.len() + 4).sum();
    let mut raw = Vec::with_capacity(total);
    for p in parts {
        let len = u32::try_from(p.len()).map_err(|_| "a part is larger than 4 GiB".to_string())?;
        raw.extend_from_slice(&len.to_le_bytes());
        raw.extend_from_slice(p.as_bytes());
    }
    Ok(raw)
}

/// One zstd frame (level 19, window log 23) over the length-prefixed parts.
pub fn encode_batch(parts: &[&str]) -> Result<Vec<u8>, String> {
    let raw = raw_batch(parts)?;
    let err = |e: std::io::Error| e.to_string();
    let mut enc = zstd::stream::write::Encoder::new(Vec::new(), LEVEL).map_err(err)?;
    enc.window_log(WINDOW_LOG).map_err(err)?;
    enc.set_pledged_src_size(Some(raw.len() as u64))
        .map_err(err)?;
    enc.write_all(&raw).map_err(err)?;
    enc.finish().map_err(err)
}

/// Decode a batch under the limits of the protocol: at most `max_raw` inflated bytes, checked
/// before the caller touches the parts.
pub fn decode_batch(bytes: &[u8], max_raw: usize) -> Result<Vec<WirePart>, String> {
    let raw = inflate(bytes)?;
    if raw.len() > max_raw {
        return Err(format!("batch inflates to more than {max_raw} bytes"));
    }
    let mut out = Vec::new();
    let mut pos = 0;
    while pos < raw.len() {
        if out.len() >= MAX_PARTS {
            return Err("more than 20000 parts".into());
        }
        let Some(head) = raw.get(pos..pos + 4) else {
            return Err("cut length prefix".into());
        };
        let len = u32::from_le_bytes([head[0], head[1], head[2], head[3]]) as usize;
        pos += 4;
        if len > raw.len() - pos {
            return Err("a length prefix runs past the end of the batch".into());
        }
        let text = std::str::from_utf8(&raw[pos..pos + len])
            .map_err(|_| "a part is not UTF-8".to_string())?;
        pos += len;
        if crate::build::parse_header(text).is_none() {
            return Err(format!("a part has no valid {PART_MAGIC} header"));
        }
        let id = part_id(text);
        out.push(WirePart {
            id,
            text: text.to_string(),
        });
    }
    Ok(out)
}

/// One request body: a single zstd frame of at most 4 MiB that declares and holds at most
/// 64 MiB, with a window of at most 8 MiB (the limits of the protocol).
pub fn inflate(bytes: &[u8]) -> Result<Vec<u8>, String> {
    if bytes.len() > MAX_COMPRESSED {
        return Err("request body is larger than 4 MiB".into());
    }
    let frame = zstd::zstd_safe::find_frame_compressed_size(bytes)
        .map_err(|_| "not a zstd frame".to_string())?;
    if frame != bytes.len() {
        return Err("data after the zstd frame".into());
    }
    if let Ok(Some(n)) = zstd::zstd_safe::get_frame_content_size(bytes)
        && n > MAX_DECOMPRESSED as u64
    {
        return Err("frame declares more than 64 MiB".into());
    }
    let err = |e: std::io::Error| e.to_string();
    let mut dec = zstd::stream::read::Decoder::new(bytes).map_err(err)?;
    dec.window_log_max(WINDOW_LOG).map_err(err)?;
    let mut raw = Vec::new();
    dec.take(MAX_DECOMPRESSED as u64 + 1)
        .read_to_end(&mut raw)
        .map_err(err)?;
    if raw.len() > MAX_DECOMPRESSED {
        return Err("decompressed data is larger than 64 MiB".into());
    }
    Ok(raw)
}

/// Group parts into requests that fit the limits: at most 20000 parts, 64 MiB
/// decompressed and 4 MiB compressed each. Returns the request bodies and the
/// part indexes each holds, in order.
pub fn split_requests(parts: &[&str]) -> Result<Vec<Request>, String> {
    let mut out = Vec::new();
    let mut start = 0;
    while start < parts.len() {
        let (mut end, mut raw) = (start, 0usize);
        while end < parts.len()
            && end - start < MAX_PARTS
            && raw + parts[end].len() + 4 <= MAX_DECOMPRESSED
        {
            raw += parts[end].len() + 4;
            end += 1;
        }
        if end == start {
            return Err("a part is larger than the request limit".into());
        }
        fit(parts, start, end, &mut out)?;
        start = end;
    }
    Ok(out)
}

fn fit(parts: &[&str], a: usize, b: usize, out: &mut Vec<Request>) -> Result<(), String> {
    let body = encode_batch(&parts[a..b])?;
    if body.len() <= MAX_COMPRESSED {
        out.push((body, (a..b).collect()));
        return Ok(());
    }
    if b - a == 1 {
        return Err("one part compresses to more than 4 MiB".into());
    }
    let mid = a + (b - a) / 2;
    fit(parts, a, mid, out)?;
    fit(parts, mid, b, out)
}

/// A part stored on its own: zstd level 19.
pub fn compress_part(text: &str) -> Result<Vec<u8>, String> {
    zstd::bulk::compress(text.as_bytes(), LEVEL).map_err(|e| e.to_string())
}

/// Read a stored part back, refusing more than 64 MiB.
pub fn read_stored(bytes: &[u8]) -> Result<String, String> {
    let err = |e: std::io::Error| e.to_string();
    let mut dec = zstd::stream::read::Decoder::new(bytes).map_err(err)?;
    dec.window_log_max(27).map_err(err)?;
    let mut raw = Vec::new();
    dec.take(MAX_DECOMPRESSED as u64 + 1)
        .read_to_end(&mut raw)
        .map_err(err)?;
    if raw.len() > MAX_DECOMPRESSED {
        return Err("stored part is larger than 64 MiB".into());
    }
    String::from_utf8(raw).map_err(|_| "stored part is not UTF-8".into())
}

/// The XOR of part ids: what `/sync` compares.
pub fn set_digest<'a>(ids: impl IntoIterator<Item = &'a [u8; 32]>) -> [u8; 32] {
    let mut d = [0u8; 32];
    for id in ids {
        for (a, b) in d.iter_mut().zip(id) {
            *a ^= b;
        }
    }
    d
}
