//! Pseudonyms for other players: `~` and 10 base32 characters of
//! HMAC-SHA256(install name key, lower-case name).

use hmac::{Hmac, KeyInit, Mac};
use sha2::Sha256;

const ALPHABET: &[u8; 32] = b"abcdefghijklmnopqrstuvwxyz234567";

pub fn pseudonym(key: &[u8; 32], name: &str) -> String {
    let mut mac =
        <Hmac<Sha256> as KeyInit>::new_from_slice(key).expect("hmac takes any key length");
    mac.update(name.to_lowercase().as_bytes());
    let digest = mac.finalize().into_bytes();
    let mut out = String::with_capacity(11);
    out.push('~');
    for i in 0..10 {
        let bit = i * 5;
        let (byte, shift) = (bit / 8, bit % 8);
        let two = (u16::from(digest[byte]) << 8) | u16::from(digest[byte + 1]);
        let v = (two >> (11 - shift)) & 31;
        out.push(ALPHABET[v as usize] as char);
    }
    out
}

/// A name that needs no pseudonym: empty, a hidden value or the player's own.
pub fn keeps_name(name: &str, own: &str) -> bool {
    name.is_empty() || name == "?" || name.eq_ignore_ascii_case(own) || name.starts_with('~')
}

pub fn hex(bytes: &[u8]) -> String {
    let mut s = String::with_capacity(bytes.len() * 2);
    for b in bytes {
        s.push(char::from_digit(u32::from(b >> 4), 16).unwrap_or('0'));
        s.push(char::from_digit(u32::from(b & 15), 16).unwrap_or('0'));
    }
    s
}

pub fn unhex<const N: usize>(s: &str) -> Option<[u8; N]> {
    let b = s.as_bytes();
    if b.len() != N * 2 {
        return None;
    }
    let mut out = [0u8; N];
    for (i, o) in out.iter_mut().enumerate() {
        let hi = (b[i * 2] as char).to_digit(16)?;
        let lo = (b[i * 2 + 1] as char).to_digit(16)?;
        *o = (hi * 16 + lo) as u8;
    }
    Some(out)
}

/// A player's `First-Last` name shape, as the app's PlayerNameShape sees it:
/// a capital, then lower-case letters or apostrophes, on both sides of one
/// hyphen. Creature and environment names do not look like this.
pub fn player_shaped(name: &str) -> bool {
    let (first, last) = match name.split_once('-') {
        Some((first, last)) if !last.contains('-') => (first, last),
        _ => return false,
    };
    shaped_part(first) && shaped_part(last)
}

fn shaped_part(part: &str) -> bool {
    let b = part.as_bytes();
    b.len() >= 2
        && b[0].is_ascii_uppercase()
        && b[1..].iter().all(|c| c.is_ascii_lowercase() || *c == b'\'')
}
