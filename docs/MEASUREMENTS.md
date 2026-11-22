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
(8 percent) come next. The groups sum to 2 percent less than the full batch (1,211,907 against 1,236,086 bytes): compressing like
lines together beats compressing in session order. It does not mean the full frame finds matches across groups.

## Decoder

### Decode time, whole history

Release build, parts in memory, median of 5 runs. "Stored" adds reading every part back from its zstd form.

| | part lines | rows | wall time | lines per second | stored: wall time |
| --- | ---: | ---: | ---: | ---: | ---: |
| character A | 4,862 | 3,698 | 1.9 ms | 2,519,040 | 2.6 ms |
| character B | 258,085 | 210,851 | 87.8 ms | 2,938,320 | 110.2 ms |
| character C | 1,559 | 903 | 0.5 ms | 3,160,349 | 0.7 ms |
| total | 264,506 | 215,452 | 90.3 ms | 2,930,568 | 113.5 ms |

One 3 KB session decodes in well under a millisecond, so a queue job is dominated by process start and the database
insert, not the decode. The rows are 80 percent `path_points` (170,384 of 210,851 for B); the NDJSON is 27.7 MB for B.

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

## Storage policies

`php artisan scarlet:storage-policies` (docs/HOSTING.md, "Storage policies") compresses the 18 fact hypertables after 7
days by `character_id`, ordered by `session_start, at`. Measured on production (`scarlet-db`, TimescaleDB 2.32,
PostgreSQL 18) on 2026-11-22 at 02:15 UTC, minutes after the deploy that ran it and after each table's compression
policy ran once. Compression was not enabled before the deploy, so `hypertable_compression_stats` returns nothing for
that side; the "before" below is `hypertable_detailed_size` on 2026-11-22 at 01:58 UTC.

| Hypertable | Chunks compressed | Total before | Total after | Compressed chunks, before and after | Ratio |
| --- | ---: | ---: | ---: | ---: | ---: |
| `path_points` | 10 of 18 | 54 MB | 20 MB | 37 MB to 3,488 kB | 10.9 |
| `creature_sightings` | 10 of 18 | 3,216 kB | 2,808 kB | 1,408 kB to 616 kB | 2.3 |
| `xp_gains` | 2 of 4 | 2,424 kB | 1,488 kB | 1,344 kB to 168 kB | 8.0 |
| `money_changes` | 2 of 4 | 2,136 kB | 1,856 kB | 1,080 kB to 160 kB | 6.8 |
| `kills` | 2 of 4 | 2,008 kB | 1,416 kB | 1,096 kB to 152 kB | 7.2 |
| `loot_drops` | 2 of 4 | 1,232 kB | 1,128 kB | 400 kB to 104 kB | 3.9 |
| other 13 hypertables | 0 to 2 | 4,288 kB | 4,136 kB | | 1.0 to 3.6 |
| All 19 hypertables | 40 of 86 | 69.4 MB (72,785,920 bytes) | 32.8 MB (34,365,440 bytes) | | |

The database, with its other tables, went from 140 MB to 105 MB. The ratio of `path_points` is high because
production's heap carried free space the compression removed (the same table on a restored copy compresses 5.1 to
5.9 times, depending on which chunks are compressed). Chunks of the last 7 days are not compressed yet, and `character_levels` never is, so the
totals fall less than the ratios. Tables with a few thousand rows (`deaths`, `dungeon_runs`, the talent tables) do not
gain until they hold more.

Re-decode of a session whose rows are all in compressed chunks (character 2, session 1794067982, 7 November: 12,073
`path_points`, 175 `creature_sightings`, 63 `play_spans`, 217 `xp_gains`, 127 `money_changes`, 178 `kills`, 49
`loot_drops`, 1 `level_ups`, 1 `sessions` row). Count and `bit_xor(hashtextextended(row::text, 0))` per table, the
`sessions` row without `updated_at`, `decoded_at` and `created_at`:

| Step | Result |
| --- | --- |
| Decoded through the queue job with the chunks uncompressed (A) | `path_points` 12,073, bit_xor -4945772434354599251; the other 8 tables recorded |
| After the compression policy compressed the chunks of all five tables above | Identical to A in every table |
| Decoded again through the queue job, rows deleted and inserted in the compressed chunks (B) | Identical to A in every table; chunk status 9 (partly compressed); invalidation log empty after the refresh |
| After the compression policy ran again | Chunk status 1; identical to A |

A re-decode on the restored copy logged the session's own span in the invalidation log (16:13 to 21:42 on 7 November
for `path_points`), not the chunk's. `RefreshAggregates` over that range took 0.33 s and over 1970 to 2100 (what
`rulesets:merge` asks for) 0.44 s, 28 aggregates each, and left `heat_daily_ruleset`, `xp_daily_character` and
`play_hourly_ruleset` unchanged (count, sum and `bit_xor`). `rulesets:merge` refiled 177,655 compressed `path_points`
rows of one character in one transaction; without `DecompressionLimit` TimescaleDB stops at 100,000.

The orderby and segmentby choices were compared on the restored copy, `path_points` total bytes after compression:
`character_id` by `session_start, at` 5,406,720; `character_id` by `at` 5,373,952; `character_id, session_start` by
`at` 5,480,448; no segmentby, ordered by `character_id, session_start, at` 5,988,352. The first is the one whose batch
metadata lets a delete by character and session skip the other sessions' batches (a delete of one session scanned the metadata of 285 batches and decompressed none).

## Load tests

Measured on 2026-11-22 on throwaway droplets at production sizes in nyc3: `scarlet-load-app` (s-2vcpu-4gb), `scarlet-load-db`
(s-1vcpu-2gb, 50 GB volume) and `scarlet-load-gen` (s-4vcpu-8gb, runs `tools/loadgen` and `oha` over the VPC). The stack is
the production compose files with the database on its own host, pgBackRest archiving to R2 under `pgbackrest-load`, and the
rate limits lifted through `SCARLET_RATE_*`. They ran from 01:32 to 04:14 UTC (2.7 hours) and were destroyed afterwards
(`bin/load down`); at $89 a month for the three droplets and the volume that is about $0.36. `bin/load` pins every command to `scarlet-load-*`, and the generator refuses
any host that is not a `scarlet-load-*` name on a private or tailnet address.

**What the generator sends.** `loadgen prepare` builds the parts of three real recordings (characters A, B and C: 145, 1,660
and 90 parts), cuts out each character's name and every other player's name, and stores them as one batch. B is used: 159
sessions, 130.9 h played over 17 days. `loadgen run` makes a virtual character from consecutive sessions of B until the
asked history length, gives it a new name, moves every part by whole days (the hour of day stays) so every part id is new,
and runs the real protocol: register, sync, manifest, parts, then a second sync to verify. It starts installs at the
requested rate whether or not the last ones finished (open loop), so a slow server shows as late starts and long install
times, not as a lower rate. `loadgen check` decodes the same sessions under another name and 5 days later and compares row,
line and play-time counts: 40 of 40 sessions matched. After R1, R2 and the storage run the part counts in the database
equalled the generator's (R3 was not compared).

The ramps and the burst finished with 0 failed installs and 0 failed verifications; the burst had 2 gateway timeouts on
`parts` that the generator retried.

| Run | Offered | History per install | Result |
| --- | --- | --- | --- |
| R1, before items 3 and 5 | 600 to 9,000 installs/h in 20 min: 1,599 installs, 111,897 parts, 88 MB | 3 h, 70 parts | 0 failed, 0 late. Install p50 2.8 s, p95 5.1 s. Decoding fell behind from the first minutes: 8,358 jobs queued and the oldest undecoded part 904 s old when the ramp ended, 7,666 queued 8 min later |
| R2, items 3 and 5 deployed | the same, 108,575 parts | 3 h | 0 failed, 0 late. Install p50 3.1 s, p95 7.9 s. 8,265 jobs queued and the oldest part 898 s old at the end of the ramp, 8,116 queued 14 min later |
| R3, burst | 10,000 installs/h for 5 min: 834 installs, 256,902 parts, 212 MB | 20 h, 308 parts | 0 failed, but it took 728 s, not 300: 1.15 installs/s (4,100 installs/h, 353 parts/s, 0.29 MB/s of bodies). 651 of 834 installs started more than 1 s late (largest 351 s); install p50 107 s, p95 128 s. 11,827 jobs queued after 6 min |

R1 and R2 differ by items 3 (sweeper, Redis split) and 5 (storage policies). Neither changes decoding speed, and the queue
growth and the ingest rate match, so neither made the ramp better or worse by more than the difference between two runs.
R2's request latencies were 20 to 60 percent higher at p95; the cause was not found.

### What saturated, against the Stages table

| Trigger (Stages 1 and 2 of the scaling plan) | R1 and R2 | R3 (burst) |
| --- | --- | --- |
| Oldest undecoded part older than 10 min | Crossed in minute 14 of the ramp, at about 6,400 installs/h. The queue grew from the first minutes: ingest finishes about 1.8 sessions/s (below), which is 720 installs/h of 3 h | 383 s after 6 min and still rising |
| CPU above 70% | App droplet 70% at about 5,000/h, 83 to 87% at 7,600 to 8,200/h (it also runs Horizon). Database droplet 95 to 100% from the third minute, because ingest alone uses all of its one vCPU | App 100% from the first minute; database 85 to 99% |
| Memory above 85% | App at most 34%, database at most 60% | Same |
| php-fpm max children (10) | Established connections to php-fpm: 11 (R1) and 21 (R2) at 7,400 to 8,200/h, so every child busy and requests waiting | 129 with 128 workers: every child busy, the rest queued |
| Volume above 70% | 4% used, at most 15.5 MB/s written, iowait at most 3%: the volume did not limit either run | 3% |
| Redis above 60% (154 MB) | 48 MB at 8,358 queued jobs: about 4 to 5 kB per queued session, so 60% is about 30,000 to 38,000 queued sessions | 48 MB at 11,827 jobs |
| Connections above 70% of 80 | At most 15 | At most 15 |

The first resource to run out is the database's one vCPU, and it runs out to ingest, not to uploads. The upload path took
9,000 installs/h of 3 h without a failure or a late start, but only by building a queue it cannot empty. With 20 h of
history per install the app droplet's two vCPUs and php-fpm's 10 children are the limit (R3). The 50 GB volume was never
the limit at these rates; it was not driven to its own limit.

### Ingest speed

Sessions finished per second with a backlog and no uploads, counted from `sessions` in windows of about 150 s:

| Condition | Sessions/s | Hours of play per hour | Parts/s |
| --- | ---: | ---: | ---: |
| Backlog (R2), 2 Horizon ingest processes, refresh policies on | 1.80 | 4,430 | 20.3 |
| The same with the 28 refresh policies switched off (one window each) | 2.16 | 5,271 | 24.8 |
| Storage run, 16 characters, nothing else running: 1,003 sessions | 1.76 | 5,600 | 17.9 |
| Re-decode of those sessions with every chunk compressed (150 s) | 1.05 | not counted | not counted |

Jobs took 1 to 4 s each and the Horizon container used about 18% CPU, so the decoders wait on the database, whose one vCPU
sat at 100% (70% user, 26% system, with `pgbackrest archive-push` running about 10 times a minute during ingest). Decoding
into compressed chunks is about 40% slower than into uncompressed ones. One 1-vCPU database decodes about 4,400 hours of
play per hour, whether they arrive as new installs or as live uploads; that is the number to hold against the Stages table.

### Storage and recovery (16 character-weeks)

16 characters of 54 h each (888 h, 1,003 sessions, 10,198 parts, 1,268,809 `path_points`), dated 15 to 20 days back so the
7-day compression policy covers every chunk. The plan asked for 1,000 character-weeks; decoding them takes about 12 hours at
4,400 h/h, so 16 were run and the sizes below scale by 62.5 (not measured at that size). The 18 compression jobs ran with
`call run_job` and took 33 s.

| Measure | Value |
| --- | --- |
| Database, nothing compressed | 318 MB (19.9 MB per character-week). Fact hypertables 212 MB, of which `path_points` 165 MB (1,428 rows per played hour) |
| After compression | 140.5 MB (8.8 MB per character-week). Fact hypertables 19 MB. Compressed chunks 202 MB to 16 MB (12.6 times) |
| Ratio per table | `path_points` 15.8, `money_changes` 10.6, `kills` 10.7, `xp_gains` 9.4, `skill_ups` 7.3, `creature_sightings` 3.8 |
| Everything else | Continuous aggregates 78 MB (`heat_daily_character` 42 MB, `heat_daily_ruleset` 25 MB), uncompressed and four times the compressed facts; `parts` 15 MB |
| Forced refresh of all 28 aggregates over 21 days of chunks | 22.9 s, including one ssh and psql start. `RefreshAggregates` with nothing invalidated: 1.7 s |
| `pgbackrest backup --type=full` | 126 s (14 s of it expiring the previous full): 180 MB of database, 33.7 MB in R2 |
| `pgbackrest --delta restore` into a scratch container, then start and `select count(*)` | 64 s to restore 172 MB, 3 s to start; 10,198 parts and 1,003 sessions, equal to the live database |

Scaled to 1,000 character-weeks that is about 8.8 GB compressed (19.9 GB without) and 12 hours of decoding. The backup ran
at 1.6 MB/s of database (180 MB in 112 s) and the restore at 2.7 MB/s, so at 8.8 GB they would take about 1.5 hours and 1
hour if they scale linearly; they were not timed at that size. The aggregates grow with the characters (rows per character, cell and day), so they, not the fact tables,
are the number to watch. One recording was re-timed for every character, so all virtual characters walk the same places;
real characters may make the heat aggregates larger or smaller.

### Reads at origin

`oha 1.17` from the generator, 15 s per row, against the load app with the storage run's 16 characters decoded, the database
otherwise idle, and no Cloudflare in front (items 4 and 7 had not landed):

| Path | 10 connections | 40 connections |
| --- | --- | --- |
| `/api/v1/public/stats` | 25 req/s, p95 760 ms | 27 req/s, p95 1,907 ms |
| `/leaderboards` | 6 req/s, p95 3,649 ms | 9 req/s, p95 6,306 ms |
| `/stats` | 10 req/s, p95 4,506 ms | 17 req/s, p95 3,207 ms |
| `/c/<name>` | 3 req/s, p95 7,426 ms | 5 req/s, p95 14,953 ms |

No request failed. During these runs the app droplet used 35 to 69% CPU and the database 87 to 98%, so the pages are limited
by the database, not by php-fpm. That is what an edge cache has to absorb: without one the origin answers about 25
requests a second on the cheapest endpoint and 3 to 17 on the pages.
### Not measured

- 1,000 character-weeks, and the refresh, backup and restore times at that size (16 were run).
- Each flip under load (resize, second ingest droplet, replica, PgBouncer): those items had not landed, and the environment
  has one app and one database droplet.
- Item 5's refresh window against a long history: the ramps held at most a week of data, too little for a refresh of the whole
  table to cost much more than one of the last 3 days. Production's history is what will show it.
- The volume's own limit. It never wrote more than 15.5 MB/s.
- A steady mix of live uploads: every virtual install sends its whole history at once, which is the hardest case.
- Every ingest condition above was one window; differences under 10 percent are noise.

### Rerun

```
loadgen prepare --root <companion out dir> --folders <A>,<B>,<C> --out template.bin   # tools/loadgen, cargo build --release
bin/load up && bin/load gen-install template.bin
bin/load sample 1750 > /dev/null &                 # CPU, fpm connections, Redis, queue, Postgres, volume every 15 s
bin/load sh gen 'loadgen run --connect scarlet-load-app --template /tmp/template.bin --ca /tmp/load-ca.pem --rate 600 --ramp-to 9000 --duration 1200 --concurrency 64 --history-hours 3 --salt r1 --log /tmp/r1.jsonl --drain-seconds 600'
bin/load reset
bin/load sh gen 'loadgen run ... --rate 10000 --duration 300 --concurrency 128 --history-hours 20 --salt r3 --drain-seconds 30 --poll-rate 5'
bin/load reset
bin/load sh gen 'loadgen run ... --rate 3600 --duration 16 --concurrency 16 --history-hours 54 --min-age-days 15 --spread-days 5 --salt r4 --drain-seconds 1500'
bin/load sh gen 'ip=$(getent hosts scarlet-load-app | cut -d" " -f1); oha -z 15s -c 40 --no-tui --insecure --connect-to scarletweb.io:443:$ip:443 https://scarletweb.io/stats'
bin/load down
```

`loadgen info` and `loadgen check` read a template without a server, and `loadgen run --dry-run` builds the plans and sends
nothing. The summary line at the end of a run holds the counts and latencies used above; `bin/load sample` prints one TSV per
droplet.
