//! Measuring tools: `measure` prints the numbers of docs/MEASUREMENTS.md for the
//! payload folders it is given, `parse-bench` times the line-parsing loop.
//! Characters are labelled A, B, C in the order given; no name is printed.

use scarlet_part::build::{Options, Part, build_parts};
use scarlet_part::decode::decode_session;
use scarlet_part::load::load_dir;
use scarlet_part::tables::Groups;
use scarlet_part::wire;
use std::collections::{BTreeMap, HashMap};
use std::path::Path;
use std::time::Instant;

fn median(v: &mut [f64]) -> f64 {
    v.sort_by(|a, b| a.total_cmp(b));
    if v.is_empty() {
        return 0.0;
    }
    let m = v.len() / 2;
    if v.len() % 2 == 1 {
        v[m]
    } else {
        (v[m - 1] + v[m]) / 2.0
    }
}

fn stats(v: &[usize]) -> (f64, f64, usize) {
    let mut f: Vec<f64> = v.iter().map(|x| *x as f64).collect();
    let mean = f.iter().sum::<f64>() / f.len().max(1) as f64;
    (median(&mut f), mean, v.iter().copied().max().unwrap_or(0))
}

struct Char {
    label: char,
    sessions: usize,
    main_lines: usize,
    text_bytes: u64,
    scl_bytes: u64,
    files: usize,
    parts: Vec<Part>,
    played_s: f64,
}

fn by_session(parts: &[Part]) -> BTreeMap<i64, Vec<&Part>> {
    let mut m: BTreeMap<i64, Vec<&Part>> = BTreeMap::new();
    for p in parts {
        m.entry(p.session).or_default().push(p);
    }
    m
}

fn texts<'a>(parts: impl IntoIterator<Item = &'a Part>) -> Vec<&'a str> {
    parts.into_iter().map(|p| p.text.as_str()).collect()
}

fn decode_all(parts: &[Part]) -> Result<(usize, usize, f64), String> {
    let (mut rows, mut lines, mut played) = (0, 0, 0.0);
    let mut out = String::new();
    for (_, ps) in by_session(parts) {
        let refs = texts(ps);
        out.clear();
        let s = decode_session(&refs, &mut out)?;
        rows += s.rows;
        lines += s.lines;
        played += s.played_ms as f64 / 1000.0;
    }
    Ok((rows, lines, played))
}

fn kib(b: usize) -> String {
    format!("{:.1} KiB", b as f64 / 1024.0)
}

pub fn measure(
    root: &Path,
    folders: &[String],
    runs: usize,
    dump: Option<&Path>,
) -> Result<(), String> {
    let groups = Groups::get();
    let opts = Options::all([0u8; 32]);
    let mut chars: Vec<Char> = Vec::new();
    for (i, folder) in folders.iter().enumerate() {
        let loaded = load_dir(&root.join(folder).join("payloads"), None).map_err(|_| {
            format!(
                "character {}: no readable payloads",
                (b'A' + i as u8) as char
            )
        })?;
        for s in &loaded.skipped {
            eprintln!(
                "skipped file of character {}: {}",
                (b'A' + i as u8) as char,
                s.split(':').nth(1).unwrap_or("")
            );
        }
        let (scl_bytes, text_bytes, files, main_lines) = (
            loaded.scl_bytes,
            loaded.text_bytes,
            loaded.files,
            loaded.main.len(),
        );
        let built = build_parts(&loaded.character, loaded.main, loaded.addon, &opts);
        chars.push(Char {
            label: (b'A' + i as u8) as char,
            sessions: built.stats.sessions_closed,
            main_lines,
            text_bytes,
            scl_bytes,
            files,
            parts: built.parts,
            played_s: 0.0,
        });
    }

    if let Some(dir) = dump {
        std::fs::create_dir_all(dir).map_err(|e| e.to_string())?;
        for c in &chars {
            let text: String = c.parts.iter().map(|p| p.text.as_str()).collect();
            std::fs::write(dir.join(format!("{}-parts.txt", c.label)), text)
                .map_err(|e| e.to_string())?;
        }
    }

    println!("### Sizes\n");
    println!(
        "| | {} | Total |",
        chars
            .iter()
            .map(|c| format!("Character {}", c.label))
            .collect::<Vec<_>>()
            .join(" | ")
    );
    println!("| --- |{}", " ---: |".repeat(chars.len() + 1));
    let sum = |f: &dyn Fn(&Char) -> u64| chars.iter().map(f).sum::<u64>();
    let row = |name: &str, f: &dyn Fn(&Char) -> String, total: String| {
        println!(
            "| {name} | {} | {total} |",
            chars.iter().map(f).collect::<Vec<_>>().join(" | ")
        );
    };
    row(
        "payload files",
        &|c| c.files.to_string(),
        sum(&|c| c.files as u64).to_string(),
    );
    row(
        "sessions (closed)",
        &|c| c.sessions.to_string(),
        sum(&|c| c.sessions as u64).to_string(),
    );
    row(
        "lines",
        &|c| c.main_lines.to_string(),
        sum(&|c| c.main_lines as u64).to_string(),
    );
    row(
        "raw text bytes",
        &|c| c.text_bytes.to_string(),
        sum(&|c| c.text_bytes).to_string(),
    );
    row(
        ".scl files today (bytes)",
        &|c| c.scl_bytes.to_string(),
        sum(&|c| c.scl_bytes).to_string(),
    );
    row(
        "parts",
        &|c| c.parts.len().to_string(),
        sum(&|c| c.parts.len() as u64).to_string(),
    );
    row(
        "part text bytes",
        &|c| {
            c.parts
                .iter()
                .map(|p| p.text.len())
                .sum::<usize>()
                .to_string()
        },
        sum(&|c| c.parts.iter().map(|p| p.text.len() as u64).sum()).to_string(),
    );

    // Whole history, all groups on.
    let t0 = Instant::now();
    let mut batch_bytes = Vec::new();
    let mut requests = Vec::new();
    let mut storage_total = Vec::new();
    for c in &chars {
        let all = texts(&c.parts);
        batch_bytes.push(wire::encode_batch(&all)?.len());
        requests.push(wire::split_requests(&all)?.len());
        let mut st = 0;
        for p in &c.parts {
            st += wire::compress_part(&p.text)?.len();
        }
        storage_total.push(st);
    }
    row(
        "one wire batch, all groups (bytes)",
        &|c| batch_bytes[(c.label as u8 - b'A') as usize].to_string(),
        batch_bytes.iter().sum::<usize>().to_string(),
    );
    row(
        "requests at 4 MiB",
        &|c| requests[(c.label as u8 - b'A') as usize].to_string(),
        requests.iter().sum::<usize>().to_string(),
    );
    row(
        "storage, each part compressed alone (bytes)",
        &|c| storage_total[(c.label as u8 - b'A') as usize].to_string(),
        storage_total.iter().sum::<usize>().to_string(),
    );
    eprintln!(
        "batch and storage measured in {:.1}s",
        t0.elapsed().as_secs_f64()
    );

    // Per session.
    println!("\n### Bytes per session\n");
    println!(
        "Wire: all groups of one session in one frame. Storage: the session's parts compressed alone.\n"
    );
    println!("| | median | mean | largest |");
    println!("| --- | ---: | ---: | ---: |");
    let mut all_wire = Vec::new();
    let mut all_store = Vec::new();
    let mut typical: Vec<(char, usize, usize)> = Vec::new();
    for c in &mut chars {
        let mut wire_s = Vec::new();
        let mut store_s = Vec::new();
        let mut raw_s = Vec::new();
        for (_, ps) in by_session(&c.parts) {
            wire_s.push(wire::encode_batch(&texts(ps.iter().copied()))?.len());
            let mut st = 0;
            for p in &ps {
                st += wire::compress_part(&p.text)?.len();
            }
            store_s.push(st);
            raw_s.push(ps.iter().map(|p| p.text.len()).sum::<usize>());
        }
        let (m, a, x) = stats(&wire_s);
        println!("| character {} wire | {m:.0} | {a:.0} | {x} |", c.label);
        let (m, a, x) = stats(&store_s);
        println!("| character {} storage | {m:.0} | {a:.0} | {x} |", c.label);
        let mut order: Vec<usize> = (0..raw_s.len()).collect();
        order.sort_by_key(|i| raw_s[*i]);
        if let Some(mid) = order.get(order.len() / 2) {
            typical.push((c.label, raw_s[*mid], wire_s[*mid]));
        }
        all_wire.extend(wire_s);
        all_store.extend(store_s);
    }
    let (m, a, x) = stats(&all_wire);
    println!("| all wire | {m:.0} | {a:.0} | {x} |");
    let (m, a, x) = stats(&all_store);
    println!("| all storage | {m:.0} | {a:.0} | {x} |");
    println!(
        "\nA typical new session (the median by size): {}",
        typical
            .iter()
            .map(|(l, r, w)| format!("character {l}: {r} bytes of text, {w} bytes on the wire"))
            .collect::<Vec<_>>()
            .join("; ")
    );

    // Per group.
    println!("\n### Wire bytes per group\n");
    println!("Each group's parts of the whole history in one frame, per character, summed.\n");
    println!("| group | parts | text bytes | wire bytes | share of wire |");
    println!("| --- | ---: | ---: | ---: | ---: |");
    let mut rows: Vec<(String, usize, usize, usize)> = Vec::new();
    for g in &groups.list {
        let (mut n, mut raw, mut wb) = (0, 0, 0);
        for c in &chars {
            let ps: Vec<&Part> = c.parts.iter().filter(|p| p.group == g.key).collect();
            if ps.is_empty() {
                continue;
            }
            n += ps.len();
            raw += ps.iter().map(|p| p.text.len()).sum::<usize>();
            wb += wire::encode_batch(&texts(ps))?.len();
        }
        rows.push((g.key.clone(), n, raw, wb));
    }
    let total_wire: usize = rows.iter().map(|r| r.3).sum();
    rows.sort_by_key(|r| std::cmp::Reverse(r.3));
    for (k, n, raw, wb) in &rows {
        println!(
            "| {k} | {n} | {raw} | {wb} | {:.1}% |",
            *wb as f64 * 100.0 / total_wire.max(1) as f64
        );
    }
    println!("| sum of groups | | | {total_wire} | |");

    // Decode time.
    println!("\n### Decode time, whole history\n");
    println!(
        "Release build, parts in memory, median of {runs} runs. \"Stored\" adds reading each part back from its zstd storage form.\n"
    );
    println!("| | parts lines | rows | wall time | lines per second | stored: wall time |");
    println!("| --- | ---: | ---: | ---: | ---: | ---: |");
    let (mut tl, mut tr, mut tw, mut ts) = (0usize, 0usize, 0.0, 0.0);
    for c in &mut chars {
        let mut times = Vec::new();
        let (mut rows_n, mut lines_n, mut played) = (0, 0, 0.0);
        for _ in 0..runs {
            let t = Instant::now();
            (rows_n, lines_n, played) = decode_all(&c.parts)?;
            times.push(t.elapsed().as_secs_f64());
        }
        c.played_s = played;
        let stored: Vec<Vec<u8>> = c
            .parts
            .iter()
            .map(|p| wire::compress_part(&p.text))
            .collect::<Result<_, _>>()?;
        let mut st_times = Vec::new();
        for _ in 0..runs {
            let t = Instant::now();
            let mut by: BTreeMap<i64, Vec<String>> = BTreeMap::new();
            for (p, b) in c.parts.iter().zip(&stored) {
                by.entry(p.session).or_default().push(wire::read_stored(b)?);
            }
            let mut out = String::new();
            for (_, ps) in by {
                let refs: Vec<&str> = ps.iter().map(String::as_str).collect();
                out.clear();
                decode_session(&refs, &mut out)?;
            }
            st_times.push(t.elapsed().as_secs_f64());
        }
        let (w, sw) = (median(&mut times), median(&mut st_times));
        println!(
            "| character {} | {lines_n} | {rows_n} | {:.1} ms | {:.0} | {:.1} ms |",
            c.label,
            w * 1000.0,
            lines_n as f64 / w,
            sw * 1000.0
        );
        tl += lines_n;
        tr += rows_n;
        tw += w;
        ts += sw;
    }
    println!(
        "| total | {tl} | {tr} | {:.1} ms | {:.0} | {:.1} ms |",
        tw * 1000.0,
        tl as f64 / tw,
        ts * 1000.0
    );

    // Per hour.
    println!("\n### Bytes per hour of play\n");
    println!("| | hours played | wire bytes per hour | storage bytes per hour |");
    println!("| --- | ---: | ---: | ---: |");
    for (i, c) in chars.iter().enumerate() {
        let h = c.played_s / 3600.0;
        println!(
            "| character {} | {h:.1} | {:.0} | {:.0} |",
            c.label,
            batch_bytes[i] as f64 / h,
            storage_total[i] as f64 / h
        );
    }
    let hours: f64 = chars.iter().map(|c| c.played_s).sum::<f64>() / 3600.0;
    println!(
        "| total | {hours:.1} | {:.0} | {:.0} |",
        batch_bytes.iter().sum::<usize>() as f64 / hours,
        storage_total.iter().sum::<usize>() as f64 / hours
    );

    // Dictionary.
    println!("\n### Trained dictionary for per-part storage\n");
    println!(
        "Sessions of all characters, even-numbered ones (in time order) train, odd-numbered ones test. Test parts are compressed alone at level 19, with and without the dictionary.\n"
    );
    let (mut train, mut test): (Vec<Vec<u8>>, Vec<&Part>) = (Vec::new(), Vec::new());
    for c in &chars {
        for (n, (_, ps)) in by_session(&c.parts).into_iter().enumerate() {
            for p in ps {
                if n % 2 == 0 {
                    train.push(p.text.clone().into_bytes());
                } else {
                    test.push(p);
                }
            }
        }
    }
    let plain: usize = test
        .iter()
        .map(|p| wire::compress_part(&p.text).map(|b| b.len()))
        .sum::<Result<usize, _>>()?;
    let raw: usize = test.iter().map(|p| p.text.len()).sum();
    println!(
        "| dictionary | train parts | test parts | test text | no dictionary | with dictionary | ratio |"
    );
    println!("| --- | ---: | ---: | ---: | ---: | ---: | ---: |");
    for size in [32 << 10, 110 << 10] {
        let dict = zstd::dict::from_samples(&train, size).map_err(|e| e.to_string())?;
        let mut comp = zstd::bulk::Compressor::with_dictionary(wire::LEVEL, &dict)
            .map_err(|e| e.to_string())?;
        let mut with = 0;
        for p in &test {
            with += comp
                .compress(p.text.as_bytes())
                .map_err(|e| e.to_string())?
                .len();
        }
        println!(
            "| {} | {} | {} | {raw} | {plain} | {with} | {:.3} |",
            kib(dict.len()),
            train.len(),
            test.len(),
            with as f64 / plain as f64
        );
    }
    Ok(())
}

pub struct Scan {
    pub lines: u64,
    pub sum_dt: i64,
    pub fields: u64,
    pub codes: usize,
}

/// The loop the PHP script repeats: split every line on tabs, skip lines whose
/// first field is not an integer, sum those integers, count fields and count
/// lines per code.
pub fn scan(text: &str) -> Scan {
    let mut codes: HashMap<&str, u64> = HashMap::new();
    let (mut lines, mut sum_dt, mut fields) = (0u64, 0i64, 0u64);
    for line in text.split('\n') {
        if line.is_empty() {
            continue;
        }
        let f: Vec<&str> = line.split('\t').collect();
        let Ok(dt) = f[0].parse::<i64>() else {
            continue;
        };
        lines += 1;
        sum_dt += dt;
        fields += f.len() as u64;
        *codes.entry(f.get(1).copied().unwrap_or("")).or_default() += 1;
    }
    Scan {
        lines,
        sum_dt,
        fields,
        codes: codes.len(),
    }
}

pub fn parse_bench(file: &Path, runs: usize) -> Result<(), String> {
    let text = std::fs::read_to_string(file).map_err(|e| e.to_string())?;
    let mut times = Vec::new();
    let mut last = scan("");
    for _ in 0..runs {
        let t = Instant::now();
        last = scan(&text);
        times.push(t.elapsed().as_secs_f64());
    }
    let m = median(&mut times);
    println!(
        "rust: lines={} sum_dt={} fields={} codes={} bytes={} median={:.2} ms ({:.0} lines/s, {:.0} MB/s)",
        last.lines,
        last.sum_dt,
        last.fields,
        last.codes,
        text.len(),
        m * 1000.0,
        last.lines as f64 / m,
        text.len() as f64 / m / 1e6
    );
    Ok(())
}
