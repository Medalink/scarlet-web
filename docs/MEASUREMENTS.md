# Measurements

How big the parts are on the wire and in storage, how fast the decoder runs, and whether it matches the companion.
Numbers are from the owner's real recordings, read only. The design points here for everything marked *measured*.

Characters are called A, B and C. No name appears in this repo.

## Method

- **Data.** The output folder of the Scarlet companion (`E:\wow\Scarlet\out`): three character folders, each with
  `payloads\*.scl`. A has one file, B 25, C 8. Characters A and B each have one file of imported history (times in whole
  seconds, no live-client fields); the rest is live recording from the current client. Every file has the
  `scarlet\t1\t<Name-Surname>` header, so no file was skipped. There are no addon-stream payloads (`addons\payloads`),
  so the `addons` group is empty in every number below.
- **Parts.** `scarlet-part measure` builds every part of every closed session with all groups on and the all-zero name
  key (the key changes pseudonyms, not sizes). Wire = `encode_batch` (one zstd frame, level 19, window log 23, parts
  length-prefixed). Storage = each part compressed alone (level 19).
- **Machine.** Windows 11, the owner's main PC, release build (`opt-level 3`, thin LTO), other programs running, so
  timings vary by 10 to 20 percent from run to run. Each timing is a median of repeated runs.

Commands (run in `part/`, `cargo build --release` first):

```
scarlet-part measure --root E:\wow\Scarlet\out --folders <A folder>,<B folder>,<C folder> --runs 5 --dump-text <dir>
scarlet-part check-overview --parts-dir <pack dir> --overview <character folder>\overview.json
scarlet-part parse-bench <dir>\B-parts.txt --runs 21
php -n -d memory_limit=1G scan.php <dir>\B-parts.txt 21
```

`pack` made the pack dirs: `scarlet-part pack --out-dir <pack dir> --payloads <character folder>\payloads`.
`scan.php` is the 30-line PHP loop below; it is a scratch file, not part of the repo.

## Sizes

| | Character A | Character B | Character C | Total |
| --- | ---: | ---: | ---: | ---: |
| payload files | 1 | 25 | 8 | 34 |
| sessions (closed) | 14 | 148 | 7 | 169 |
| lines | 4,952 | 259,431 | 1,557 | 265,940 |
| raw text bytes | 183,437 | 8,919,633 | 49,557 | 9,152,627 |
| .scl files today (bytes) | 35,497 | 1,593,826 | 11,785 | 1,641,108 |
| parts | 118 | 1,500 | 90 | 1,708 |
| part text bytes | 139,682 | 6,547,821 | 37,001 | 6,724,504 |
| one wire batch, all groups (bytes) | 30,017 | 1,198,262 | 7,807 | 1,236,086 |
| requests at 4 MiB | 1 | 1 | 1 | 3 |
| storage, each part compressed alone (bytes) | 40,457 | 1,503,372 | 16,362 | 1,560,191 |

- The whole history of the largest character (148 sessions, 125 hours) is one request of 1.2 MB, 25 percent smaller than
  its `.scl` files today. A part's text is 27 percent smaller than the payload text, because `dt` replaces a 13-digit
  time.
- Parts stored alone cost 25 percent more than one frame over everything (1.50 MB against 1.20 MB for B): the price of
  being able to read one part without the rest.
- The 169 sessions need 3 requests, one per character. 4 MiB is about 3.5 times B's whole history.

## Bytes per session

Wire: all groups of one session in one frame. Storage: the session's parts, each compressed alone.

| | median | mean | largest |
| --- | ---: | ---: | ---: |
| character A wire | 710 | 2,523 | 11,376 |
| character A storage | 898 | 2,890 | 11,976 |
| character B wire | 4,051 | 9,619 | 75,411 |
| character B storage | 4,570 | 10,158 | 76,607 |
| character C wire | 1,552 | 1,719 | 3,117 |
| character C storage | 2,287 | 2,337 | 3,650 |
| all wire | 3,117 | 8,704 | 75,411 |
| all storage | 3,650 | 9,232 | 76,607 |

**A typical new session on the wire.** The 34 sessions the current client recorded (B and C) are 235 to 21,674 bytes
on the wire, median 2,801, mean 4,916; the median-sized one is 9,607 bytes of text and 3,112 bytes on the wire
(about 2.8 KB a session, found by building one batch per live session). Sessions from the imported history are
smaller on average.

## Bytes per hour of play

Hours are the sum of the decoded session lengths.

| | hours played | wire bytes per hour | storage bytes per hour |
| --- | ---: | ---: | ---: |
| character A | 2.2 | 13,618 | 18,355 |
| character B | 125.2 | 9,570 | 12,007 |
| character C | 0.2 | 31,594 | 66,216 |
| total | 127.7 | 9,683 | 12,222 |

About 10 KB of upload per hour played with every group on; an evening of 3 hours is about 30 KB. A and C are too short
to say more than that (C is 12 minutes, mostly start-up lines).

## Wire bytes per group

Each group's parts, whole history, one frame per character and group, summed over the characters.

| group | parts | text bytes | wire bytes | share of wire |
| --- | ---: | ---: | ---: | ---: |
| movement | 168 | 4,420,844 | 747,002 | 61.6% |
| social | 149 | 425,937 | 116,577 | 9.6% |
| names | 130 | 415,107 | 93,026 | 7.7% |
| combat | 103 | 279,424 | 48,558 | 4.0% |
| economy | 100 | 171,152 | 43,583 | 3.6% |
| xp | 164 | 217,928 | 42,022 | 3.5% |
| loot | 105 | 142,946 | 27,485 | 2.3% |
| world | 110 | 121,641 | 23,754 | 2.0% |
| quests | 101 | 73,143 | 17,200 | 1.4% |
| build | 99 | 157,864 | 16,940 | 1.4% |
| core | 169 | 69,598 | 10,285 | 0.8% |
| professions | 104 | 89,934 | 9,640 | 0.8% |
| collections | 90 | 100,586 | 9,343 | 0.8% |
| client | 87 | 36,629 | 6,136 | 0.5% |
| pvp | 29 | 1,771 | 356 | 0.0% |
| addons | 0 | 0 | 0 | 0.0% |
| sum of groups | | | 1,211,907 | |

`movement` (the `P` path points) is 62 percent of everything sent. A player who switches only `movement` off cuts the
upload by more than half and loses the map paths and heat maps. `social` (player seen lines, 10 percent) and `names`
(8 percent) come next. The groups sum to 2 percent less than the full batch: the full frame finds matches across
groups.

## Decoder

### Decode time, whole history

Release build, parts in memory, median of 5 runs. "Stored" adds reading every part back from its zstd form.

| | part lines | rows | wall time | lines per second | stored: wall time |
| --- | ---: | ---: | ---: | ---: | ---: |
| character A | 4,862 | 3,706 | 2.4 ms | 2,041,485 | 3.0 ms |
| character B | 258,085 | 210,895 | 121.0 ms | 2,133,768 | 141.0 ms |
| character C | 1,559 | 903 | 0.6 ms | 2,580,271 | 0.9 ms |
| total | 264,506 | 215,504 | 123.9 ms | 2,134,171 | 144.9 ms |

One 3 KB session decodes in well under a millisecond, so a queue job is dominated by process start and the database
insert, not the decode. The rows are 80 percent `path_points` (170,384 of 210,895 for B); the NDJSON is 27.7 MB for B.

### PHP against Rust, the line-parsing loop

The same loop on the same decompressed text (`B-parts.txt`: B's 1,500 parts, 6,547,821 bytes, 258,085 lines): split each
line on tabs, skip a line whose first field is not a number, add the first field, count the fields, count lines per
code. Both print `lines=258085 sum_dt=5236992700 fields=1553584 codes=148`, so they did the same work. File reading is
not timed; PHP's `explode("\n")` is, because PHP cannot walk the string without it.

```php
<?php
$text = file_get_contents($argv[1]);
$runs = (int) ($argv[2] ?? 7);
$times = [];
for ($r = 0; $r < $runs; $r++) {
    $start = hrtime(true);
    $lines = 0; $sumDt = 0; $fields = 0; $codes = [];
    foreach (explode("\n", $text) as $line) {
        if ($line === '') { continue; }
        $f = explode("\t", $line);
        if (!is_numeric($f[0])) { continue; }
        $lines++;
        $sumDt += (int) $f[0];
        $fields += count($f);
        $code = $f[1] ?? '';
        $codes[$code] = ($codes[$code] ?? 0) + 1;
    }
    $times[] = (hrtime(true) - $start) / 1e9;
}
sort($times);
$m = $times[intdiv(count($times), 2)];
printf("lines=%d sum_dt=%d fields=%d codes=%d median=%.2f ms\n", $lines, $sumDt, $fields, count($codes), $m * 1000);
```

Median of 21 runs, five repeats each (the five medians):

| | median of medians | the five medians |
| --- | ---: | --- |
| Rust (`parse-bench`, one `Vec` per line) | 30.1 ms | 27.7, 28.9, 30.1, 30.3, 33.5 |
| PHP 8.5.12, `php -n` (no ini, no extensions) | 62.1 ms | 57.7, 58.4, 62.1, 67.8, 108.0 |
| PHP 8.5.12, the owner's ini | 63.9 ms | 62.7, 63.1, 63.9, 64.4, 78.3 |

PHP takes about twice as long as Rust on the bare loop (about 4.2 million lines/s in PHP, 8.6 million in Rust). The
run used PHP's default CLI settings: OPcache is off for the CLI, and with the owner's ini the JIT stays off anyway
(PHP reports it is incompatible with the loaded extensions). A queue worker run with OPcache and JIT on might do
better; that was not measured. The loop is only the start of a decode. The Rust decoder does the full job (merging,
context, 20 tables) at 2.1 million lines/s, 121 ms for B, and a PHP decoder would also need a zstd library the stock
PHP build does not have (the reason for the Rust tool, DESIGN.md).

### Against the companion's overview

`scarlet-part check-overview` packs nothing itself: it decodes the packed parts of every session, sums the rows the
way docs/OVERVIEW.md counts, joins dungeon runs across sessions by the rule in ROWS.md, and compares with the session
rows of each character's `overview.json` (key `t` is the session start). Sessions that match, of sessions compared:

| Field | A | B | C |
| --- | ---: | ---: | ---: |
| played seconds | 14/14 | 148/148 | 7/7 |
| level at start, at end | 14/14 | 148/148 | 7/7 |
| XP from kills, dungeon kills, quests, exploring, other | 14/14 | 148/148 | 7/7 |
| kills | 14/14 | 148/148 | 7/7 |
| deaths | 14/14 | 148/148 | 7/7 |
| quests turned in | 14/14 | 148/148 | 7/7 |
| copper earned, copper spent | 14/14 | 148/148 | 7/7 |
| dungeon runs | 14/14 | 148/148 | 7/7 |
| distinct zones | 14/14 | 148/148 | 7/7 |
| balance at the end (where the decoder knows it) | 13/13 | 142/142 | 7/7 |
| time, XP, kills, deaths per zone | 14/14 | 148/148 | 7/7 |
| time, XP, kills, deaths, quests per level | 14/14 | 143/148 | 7/7 |

All 169 sessions match on every total. The five that differ (B, sessions starting 1793898397, 1794008723, 1794026823,
1794235983 and 1794791977) differ in the per-level split only: one quest turn-in is counted at the next level by the
decoder and at the previous level by the companion. All five are in the imported history, whose times are whole
seconds, so a quest turn-in and the level-up it gives share a millisecond; the parts keep the order of lines inside a
group but not between groups (DESIGN.md, Parts), and the decoder orders such ties by group, core first. No session
from the current client differs. Making the order exact would take a sequence number in each part line; the imported
history is the only place it matters, so it was not added. Mismatches found on the way and fixed in the decoder:
`ES` sorting before a kill or money line in the same millisecond (lost one kill and its XP), and instance zones, which
have no map id (zone ids are the negative instance id now).

One session in A and six in B have no balance in the decoder's output: the first session of the imported history
(A and B) and five early sessions of the current client in B, from before the addon wrote one (their `S` and `M` lines
end before the balance field). The companion walks the balance back from a later anchor; the decoder does not, because a
session is decoded alone (ROWS.md). Those balances are not in the table.

## Trained dictionary for per-part storage

Sessions of all characters in time order; even-numbered ones train, odd-numbered ones test (854 parts each). Test parts
are compressed alone at level 19 with and without the dictionary. Not adopted.

| dictionary | train parts | test parts | test text | no dictionary | with dictionary | ratio |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| 32.0 KiB | 854 | 854 | 3,194,299 | 749,467 | 684,842 | 0.914 |
| 110.0 KiB | 854 | 854 | 3,194,299 | 749,467 | 678,597 | 0.905 |

A dictionary saves 8.6 to 9.5 percent of the storage. It costs a versioned dictionary file that every stored part
depends on forever, for under 10 percent, so it is not worth it. Changing what is stored (for example not storing
`movement` as text) matters more.
