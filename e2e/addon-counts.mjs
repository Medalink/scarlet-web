/**
 * Proof of Addon counts (docs/DESIGN.md, "Addon counts") over the real upload API of the running app. An invented
 * character uploads twelve fishing sessions of Scarlet's own rows and one `counts` part per day holding what another
 * addon counted for the same twelve trips, plus two lifetime reads of that addon. The checks read what the server
 * decided from its database: the totals (Scarlet's rows plus the claims that add), the same state reached by another
 * order or after a group was removed and restored, and `php artisan addons:audit`.
 *
 *   node e2e/boot.mjs -- node e2e/addon-counts.mjs
 *
 * It needs the app's database settings, which boot.mjs hands to its command. It is not part of the default lane.
 */

import crypto from "node:crypto";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { InstallClient } from "./client.mjs";
import { expectJson, expectStatus, sleep } from "./support.mjs";

const repo = path.resolve(import.meta.dirname, "..");
const base = process.env.E2E_URL;
if (!base) throw new Error("E2E_URL is not set: run this through e2e/boot.mjs");

const NAME = "Quill-Fisher";
const TWIN = "Quill-Twin";
const LABEL = "scarlet-web e2e addon counts install";
const FISHING_CAST = 18248;
const FISHING_BOBBER = 35591;
const SMALLFISH = 6291;
const ELWYNN = 1429;
const FISHING_SKILL_LINE = 356;
const STONETUSK_BOAR = 113;
const TRIPS_WITHOUT_LOOT = [0, 1];
const LOOT_DELAY = 0.6;
const GROUP_OF = { S: "core", Z: "core", ES: "core", C: "combat", CH: "combat", LO: "loot", SL: "professions" };
const GROUP_ORDER = ["core", "combat", "loot", "professions", "counts"];
const SETTLE_MS = 120_000;
const STEADY_POLLS = 8;
const WITHHELD = 7;
const FULL_TOTAL = "343/334/356";

const TRIPS = [
  ["2026-11-20T19:49:07Z", 779, 30, 29, 1, 30, 30, 0, null, null, 0.1, 1.9],
  ["2026-11-20T19:52:50Z", 208, 10, 10, 0, 10, 10, 0, null, null, 0.7, 25.5],
  ["2026-11-20T19:59:45Z", 356, 19, 19, 0, 20, 19, 0, 19, 19, 1.3, 2.7],
  ["2026-11-20T20:10:39Z", 641, 34, 34, 0, 36, 34, 0, 34, 36, -0.7, 0.3],
  ["2026-11-20T20:13:06Z", 134, 8, 8, 0, 8, 8, 0, 8, 8, 0.4, 1.5],
  ["2026-11-20T20:19:42Z", 384, 8, 8, 0, 8, 8, 0, 8, 8, 0, 249.2],
  ["2026-11-20T20:20:51Z", 27, 2, 0, 2, 0, 1, 1, 0, 0, 0.6, 23],
  ["2026-11-20T21:43:39Z", 4331, 224, 215, 9, 235, 220, 4, 215, 232, 1.1, 42.4],
  ["2026-11-21T00:13:16Z", 6, 2, 1, 1, 0, 2, 0, 1, 3, 0.3, -9.4],
  ["2026-11-21T00:14:55Z", 89, 15, 5, 10, 8, 6, 9, 5, 5, -0.5, 3.7],
  ["2026-11-21T00:17:36Z", 95, 10, 4, 6, 4, 4, 6, 4, 4, 1.1, 2.7],
  ["2026-11-22T06:03:38Z", 57, 4, 1, 3, 1, 1, 3, 1, 1, 0.3, 32.4],
].map(([stop, elapsed, casts, landed, unknown, items, kept, cancelled, looted, quantity, startOffset, stopOffset], at) => ({
  at, to: Date.parse(stop) / 1000, elapsed, casts, landed, unknown, items, kept, cancelled, looted, quantity, startOffset, stopOffset,
}));
const LIFETIME_END = Date.parse("2026-11-22T17:55:00Z") / 1000;
const LIFETIME_CLAIMS = [
  ["mc", "2026-11-06", Date.parse("2026-11-06T16:53:28Z") / 1000, LIFETIME_END, "casts=2488;landed=2254;items=2364"],
  ["mc", "2026-11-17", Date.parse("2026-11-17T00:00:00Z") / 1000, LIFETIME_END, "casts=100;landed=90;items=95"],
];

function part(session, group, rows, extra, name = NAME) {
  const text = [["scarlet-part", 1, name, session, group].join("\t"), ...rows].join("\n") + "\n";
  const id = crypto.createHash("sha256").update(text).digest("hex");
  return { id, session, group, text, lines: rows.length, raw_bytes: Buffer.byteLength(text), ...extra };
}

function partsOf(session, lines, extra) {
  const byGroup = new Map();
  for (const [at, code, ...fields] of lines) {
    const group = GROUP_OF[code];
    if (!byGroup.has(group)) byGroup.set(group, []);
    byGroup.get(group).push({ at, text: [code, ...fields].join("\t") });
  }
  return GROUP_ORDER.filter((group) => byGroup.has(group)).map((group) => {
    let before = 0;
    const rows = byGroup.get(group).sort((a, b) => a.at - b.at).map(({ at, text }) => {
      const row = `${at - before}\t${text}`;
      before = at;
      return row;
    });
    return part(session, group, rows, extra);
  });
}

function scarletParts(trip) {
  const roles = [
    ...Array(trip.looted ?? 0).fill("looted"),
    ...Array(trip.kept - (trip.looted ?? 0)).fill("kept"),
    ...Array(trip.cancelled).fill("cancelled"),
  ].map((role, k) => ({ role, key: (k * 0.6180339887) % 1 })).sort((a, b) => a.key - b.key).map((r) => r.role);
  const n = roles.length;
  const from = trip.to - trip.elapsed;
  const first = from - trip.startOffset;
  const lastEnd = Math.min(trip.to - trip.stopOffset, trip.to);
  const step = n > 1 ? (lastEnd - LOOT_DELAY - 5 - first) / (n - 1) : 0;
  const channel = (role) => (role === "cancelled" ? 1 : role === "kept" ? Math.max(2.2, Math.min(3, step - 0.8)) : Math.max(2.2, Math.min(6, step - 0.8)));
  const lastStart = n > 1 ? lastEnd - channel(roles[n - 1]) - (roles[n - 1] === "looted" ? LOOT_DELAY : 0) : first;
  const spacing = n > 1 ? (lastStart - first) / (n - 1) : 0;
  const start = Math.floor(first - 0.3);
  const ms = (t) => Math.round((t - start) * 1000);
  const quantities = Array.from({ length: trip.looted ?? 0 }, (_, k) => Math.floor(trip.quantity / trip.looted) + (k < trip.quantity % trip.looted ? 1 : 0));
  let looting = 0;
  const lines = [
    [0, "S", "1.15.8", "ROGUE", "Human", 3, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
    [100, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
    [150, "SL", FISHING_SKILL_LINE, 5, 75, 0, "s"],
  ];
  if (!TRIPS_WITHOUT_LOOT.includes(trip.at)) lines.push([200, "LO", "c", STONETUSK_BOAR, 1, `${SMALLFISH}x1`, 1]);
  let end = 0;
  roles.forEach((role, k) => {
    const cast = first + k * spacing;
    const seconds = channel(role);
    lines.push([ms(cast), "C", FISHING_CAST], [ms(cast + seconds), "CH", FISHING_CAST, seconds]);
    end = ms(cast + seconds);
    if (role === "looted") {
      end = ms(cast + seconds + LOOT_DELAY);
      lines.push([end, "LO", "g", FISHING_BOBBER, 1, `${SMALLFISH}x${quantities[looting++]}`, 0]);
    }
  });
  lines.push([end + 50, "ES"]);
  return { start, end: start + (end + 50) / 1000, parts: partsOf(start, lines, { trip: trip.at }) };
}

function countsParts(name = NAME) {
  const claims = TRIPS.map((trip) => ({
    day: Math.floor(trip.to / 86400) * 86400,
    to: trip.to,
    line: ["fl", "", "c", "session", "fishing", trip.to - trip.elapsed, trip.to, "", `casts=${trip.casts};landed=${trip.landed};items=${trip.items};unknown=${trip.unknown}`],
  }));
  for (const [origin, store, from, to, amounts] of LIFETIME_CLAIMS) {
    claims.push({ day: Math.floor(to / 86400) * 86400, to, line: [origin, store, "a", "life", "fishing", from, to, "", amounts] });
  }
  const days = [...new Set(claims.map((c) => c.day))].sort();
  return days.map((day) => part(day, "counts", claims.filter((c) => c.day === day).map((c) => `${c.to * 1000}\tYC\t${c.line.join("\t")}`), {}, name));
}

const scarlet = TRIPS.map(scarletParts);
for (let i = 1; i < scarlet.length; i += 1) {
  if (scarlet[i].start <= scarlet[i - 1].end) throw new Error(`the fixture's trip ${i} starts at ${scarlet[i].start}, before trip ${i - 1} ends at ${scarlet[i - 1].end}`);
}
const COUNTS = countsParts();
const TWIN_COUNTS = countsParts(TWIN);
const ALL = [...scarlet.flatMap((s) => s.parts), ...COUNTS];
const BY_ID = new Map([...ALL, ...TWIN_COUNTS].map((p) => [p.id, p]));
const CLAIMS = TRIPS.length + LIFETIME_CLAIMS.length;

function db(statement) {
  const run = spawnSync("psql", ["-h", process.env.DB_HOST, "-p", process.env.DB_PORT, "-U", process.env.DB_USERNAME, "-d", process.env.DB_DATABASE, "-v", "ON_ERROR_STOP=1", "-At", "-F", "|", "-c", statement], { encoding: "utf8", env: process.env });
  if (run.status !== 0) throw new Error(`psql failed: ${run.stderr}\n${statement}`);
  return run.stdout.trim();
}

const artisan = (...args) => spawnSync("php", ["artisan", ...args], { cwd: repo, encoding: "utf8", env: process.env });

const OWN = `character_id = (select id from characters where name = '${NAME}')`;
const METRICS = ["casts", "landed", "items"];
const scarletRows = (session) => `
  (select count(*) from gathers where ${OWN} and kind = 'fish'${session}),
  (select count(*) from gathers where ${OWN} and kind = 'fish' and looted${session}),
  (select coalesce(sum(quantity), 0) from gather_items where ${OWN} and kind = 'fish' and container_item_id is null${session})`;
const CLAIM_HASH = `
  select coalesce(md5(string_agg(row_text, E'\\n' order by row_text)), 'none') from (
    select concat_ws('|', ch.name, c.origin, c.store, c.scope, c.window, c.kind, coalesce(c.from_at::text, ''), c.to_at, c.dims::text, c.amounts::text,
      coalesce(c.status, ''), coalesce(c.counted::text, ''), coalesce(c.evidence::text, ''), coalesce(r.key, ''), coalesce(c.counted_ok::text, ''),
      coalesce(c.level::text, ''), coalesce(c.zone_id::text, ''), coalesce(c.reconcile_version::text, '')) as row_text
    from addon_claims c join characters ch on ch.id = c.character_id left join rulesets r on r.id = c.ruleset_id
    where ch.name = '${NAME}') t`;
const ROW_HASH = `
  select coalesce(md5(string_agg(row_text, E'\\n' order by row_text)), 'none') from (
    select concat_ws('|', g.at::text, g.looted::text, g.level::text, g.zone_id::text, g.counted::text, r.key) as row_text
    from gathers g join rulesets r on r.id = g.ruleset_id where g.${OWN}
    union all
    select concat_ws('|', at::text, item_id, quantity, coalesce(container_item_id::text, '')) from gather_items where ${OWN}) t`;

function snapshot() {
  const counted = METRICS.map((m) => `(select coalesce(sum((c.counted ->> '${m}')::numeric), 0) from addon_claims c where c.${OWN} and c.status in ('counted', 'residual'))`);
  const [sessions, claims, unreconciled, ...rest] = db(`
    select (select count(*) from sessions where ${OWN}),
      (select count(*) from addon_claims where ${OWN}),
      (select count(*) from addon_claims where ${OWN} and status is null),
      ${scarletRows("")}, ${counted.join(", ")}, (${CLAIM_HASH}), (${ROW_HASH})`).split("|");
  const scarletTerm = rest.slice(0, 3).map(Number);
  const countedTerm = rest.slice(3, 6).map(Number);
  return {
    sessions: Number(sessions), gathers: scarletTerm[0], claims: Number(claims), unreconciled: Number(unreconciled),
    scarlet: scarletTerm, counted: countedTerm, total: scarletTerm.map((n, k) => n + countedTerm[k]), hash: rest.slice(6).join("|"),
  };
}

const client = new InstallClient(base, LABEL);
let checks = 0;
const failures = [];

function check(name, ok, detail = "") {
  checks += 1;
  console.log(`${ok ? "ok  " : "FAIL"} ${name}${ok || !detail ? "" : `: ${detail}`}`);
  if (!ok) failures.push(name);
}

async function waiting() {
  const answer = expectStatus(await client.status(), 200, "GET /upload/status");
  return (expectJson(answer, "GET /upload/status").characters ?? []).find((c) => c.name === NAME)?.sessions_waiting ?? 0;
}

async function settle(ready, what) {
  const deadline = Date.now() + SETTLE_MS;
  let last = null;
  let steady = 0;
  while (Date.now() < deadline) {
    const now = snapshot();
    steady = now.hash === last ? steady + 1 : 0;
    last = now.hash;
    if (steady >= STEADY_POLLS && now.unreconciled === 0 && ready(now) && (await waiting()) === 0) return now;
    await sleep(500);
  }
  throw new Error(`${what}: the server did not settle in ${SETTLE_MS / 1000}s, last seen ${JSON.stringify(snapshot())}`);
}

async function upload(held, name = NAME) {
  const manifest = held.map(({ id, session, group, lines, raw_bytes }) => ({ id, session, group, lines, raw_bytes }));
  const answer = expectJson(expectStatus(await client.manifest(name, GROUP_ORDER, manifest), 200, "POST /upload/manifest"), "POST /upload/manifest");
  const need = answer.need ?? [];
  if (need.length > 0) expectStatus(await client.parts(need.map((id) => BY_ID.get(id).text)), 200, "POST /upload/parts");
}

const audit = () => artisan("addons:audit", NAME);
const sum = (rows) => rows.join("/");
const claimOf = (trip) => `${OWN} and origin = 'fl' and addon_claims.window = 'session' and to_at = ${TRIPS[trip].to}`;

async function main() {
  const registered = await client.register();
  if (registered.status !== 200 && registered.status !== 201) expectStatus(registered, 201, "POST /installs");
  expectStatus(await client.remove(), 200, "DELETE /upload");
  console.log(`fixture: ${scarlet.length} Scarlet trips in ${scarlet.flatMap((s) => s.parts).length} parts, ${COUNTS.length} counts parts, ${ALL.length} in all`);

  await upload(ALL);
  const full = await settle((s) => s.sessions >= TRIPS.length && s.claims === CLAIMS, "the full upload");
  const hashFull = full.hash;
  check("the full upload: Scarlet's own rows are 343 casts, 295 landed, 316 items", sum(full.scarlet) === "343/295/316", sum(full.scarlet));
  check(`the totals, Scarlet plus counted, are ${FULL_TOTAL}`, sum(full.total) === FULL_TOTAL, `${sum(full.scarlet)} + ${sum(full.counted)} = ${sum(full.total)}`);
  check("every claim has a status", full.unreconciled === 0);
  const first = audit();
  console.log(first.stdout.trim());
  check("addons:audit passes", first.status === 0, first.stdout + first.stderr);
  check("the small lifetime read raises a ceiling note", /store '2026-11-17' fishing casts/.test(first.stdout), first.stdout);
  check("the large lifetime read raises none", !/says 2488/.test(first.stdout));

  db(`update addon_claims set status = null, counted = null, evidence = null, ruleset_id = null, counted_ok = null, level = null, zone_id = null, reconcile_version = null where ${OWN}`);
  const firstRun = artisan("addons:reconcile", NAME);
  const blank = snapshot();
  const secondRun = artisan("addons:reconcile", NAME);
  const twice = snapshot();
  check(`reconcile from blank columns writes ${CLAIMS} claims and reproduces the hash`, firstRun.stdout.includes(`${CLAIMS} claims changed`) && blank.hash === hashFull, `${firstRun.stdout.trim()} ${firstRun.stderr}`);
  check("reconcile twice changes nothing: 0 claims changed, same hash", secondRun.stdout.includes(": 0 claims changed") && twice.hash === hashFull, secondRun.stdout.trim());

  const daysOf = (where) => Number(db(`select count(*) from addon_days where ${OWN} and ${where}`));
  check("every addon day of the character carries the current reconcile version", daysOf("reconcile_version is not null") === COUNTS.length && daysOf("reconcile_version is null") === 0, `${daysOf("reconcile_version is null")} days without one`);
  db(`update addon_claims set status = null, counted = null, evidence = null, ruleset_id = null, counted_ok = null, level = null, zone_id = null, reconcile_version = null where ${OWN}`);
  db(`update addon_days set reconcile_version = null where ${OWN}`);
  const outdatedRun = artisan("ingest:redecode", "--outdated");
  const outdated = await settle((s) => s.claims === CLAIMS && s.hash === hashFull, "the outdated pass");
  check("the outdated pass queues the character and decides every claim again: the same hash", /[1-9]\d* outdated sessions, addon days and characters queued/.test(outdatedRun.stdout) && outdated.hash === hashFull, `${outdatedRun.stdout.trim()} ${outdatedRun.stderr}`);
  check("the outdated pass stamps every addon day", daysOf("reconcile_version is null") === 0 && daysOf("reconcile_version is not null") === COUNTS.length);

  expectStatus(await client.request("DELETE", "/api/v1/upload/groups/counts"), 200, "DELETE groups/counts");
  const noCounts = await settle((s) => s.claims === 0, "the counts group removal");
  check("counts group removed: no claims, Scarlet's own 343/295/316 stays", sum(noCounts.total) === "343/295/316" && noCounts.gathers === 343, sum(noCounts.total));
  check("the audit passes with no claims", audit().status === 0);
  expectStatus(await client.request("PUT", "/api/v1/upload/groups/counts"), 200, "PUT groups/counts");
  await upload(ALL);
  const restoredCounts = await settle((s) => s.claims === CLAIMS && s.hash === hashFull, "the counts group restore");
  check("counts group restored: the same hash", restoredCounts.hash === hashFull);

  expectStatus(await client.request("DELETE", "/api/v1/upload/groups/professions"), 200, "DELETE groups/professions");
  const noRows = await settle((s) => s.gathers === 0 && s.claims === CLAIMS, "the professions group removal");
  check("professions group removed: no Scarlet rows, every claim counts whole, 366/334/360", sum(noRows.total) === "366/334/360", sum(noRows.total));
  check("the audit passes with no Scarlet rows", audit().status === 0);
  expectStatus(await client.request("PUT", "/api/v1/upload/groups/professions"), 200, "PUT groups/professions");
  await upload(ALL);
  const restoredRows = await settle((s) => s.gathers === 343 && s.hash === hashFull, "the professions group restore");
  check("professions group restored: the same hash", restoredRows.hash === hashFull);

  expectStatus(await client.remove(), 200, "DELETE /upload");
  await upload(ALL.filter((p) => p.trip !== WITHHELD));
  const without = await settle((s) => s.claims === CLAIMS && s.gathers === 123, "the upload without trip 7");
  check("trip 7 withheld: its claim counts whole, totals 347/334/359", sum(without.total) === "347/334/359" && sum(without.scarlet) === "123/80/84", `${sum(without.scarlet)} + ${sum(without.counted)}`);
  check("the audit passes with trip 7 withheld", audit().status === 0);
  check("trip 7's claim is counted", db(`select status from addon_claims where ${claimOf(WITHHELD)}`) === "counted");
  await upload(ALL);
  const arrived = await settle((s) => s.gathers === 343 && s.hash === hashFull, "trip 7 arrives");
  const stored = db(`select concat_ws('|', evidence #>> '{diff,casts}', evidence #>> '{diff,landed}', evidence #>> '{diff,items}') from addon_claims where ${claimOf(WITHHELD)}`).split("|").map(Number);
  const rows = db(`select ${scarletRows(` and session_start = ${scarlet[WITHHELD].start}`)}`).split("|").map(Number);
  const moved = arrived.total.map((n, k) => n - without.total[k]);
  const expected = rows.map((n, k) => n - stored[k]);
  check("trip 7 arrives: each total moves by the trip's Scarlet rows minus the claim's stored difference", sum(moved) === sum(expected), `moved ${sum(moved)}, rows ${sum(rows)} minus stored ${sum(stored)} is ${sum(expected)}`);
  check(`trip 7 arrived: the totals are ${FULL_TOTAL} and the hash is the full upload's`, sum(arrived.total) === FULL_TOTAL && arrived.hash === hashFull, sum(arrived.total));

  expectStatus(await client.remove(), 200, "DELETE /upload");
  const held = ALL.filter((p) => p.group === "counts");
  await upload(held);
  const countsOnly = await settle((s) => s.claims === CLAIMS && s.gathers === 0, "counts first");
  check("counts first, no Scarlet session yet: every claim counts whole, 366/334/360", sum(countsOnly.total) === "366/334/360", sum(countsOnly.total));
  for (let i = TRIPS.length - 1; i >= 0; i -= 1) {
    held.push(...scarlet[i].parts);
    await upload(held);
  }
  const reversed = await settle((s) => s.gathers === 343 && s.hash === hashFull, "sessions in reverse order");
  check(`reversed upload order: ${FULL_TOTAL} and the same hash`, sum(reversed.total) === FULL_TOTAL && reversed.hash === hashFull, `${sum(reversed.total)} ${reversed.hash} against ${hashFull}`);
  await upload(TWIN_COUNTS, TWIN);
  const twinOf = (where) => Number(db(`select count(*) from addon_claims c join characters ch on ch.id = c.character_id where ch.name = '${TWIN}' and ${where}`));
  const twinReady = () => twinOf("true") === CLAIMS && twinOf("c.status is null") === 0 && snapshot().hash === hashFull;
  const until = async (ready, what) => {
    const deadline = Date.now() + SETTLE_MS;
    let steady = 0;
    while (Date.now() < deadline) {
      steady = ready() ? steady + 1 : 0;
      if (steady >= STEADY_POLLS) return;
      await sleep(500);
    }
    throw new Error(`${what}: the twin's claims did not settle: ${twinOf("true")} claims, ${twinOf("c.status is null")} without a status`);
  };
  await until(twinReady, "the twin's counts");
  const twinRule = `c.scope = 'c' and c.status = 'superseded' and c.evidence ->> 'rule' = '4' and c.evidence #>> '{with,character}' = '${NAME}'`;
  check("a second character of the install holds the same counts: its 12 character-scope claims are superseded by rule 4 and name the first", twinOf(twinRule) === TRIPS.length, `${twinOf(twinRule)} of ${TRIPS.length}`);
  check("its 2 account-scope claims are reference and nothing of it counts", twinOf("c.scope = 'a' and c.status = 'reference'") === LIFETIME_CLAIMS.length && twinOf("c.status in ('counted', 'residual')") === 0);
  check("the first character is unchanged by it: the same hash", snapshot().hash === hashFull);
  const twinAudit = artisan("addons:audit");
  check("addons:audit passes for both characters", twinAudit.status === 0, twinAudit.stdout + twinAudit.stderr);
  await upload([], TWIN);
  await until(() => twinOf("true") === 0 && snapshot().hash === hashFull, "the twin lists no parts");
  check("the twin lists no parts: its claims go and the first character is unchanged", twinOf("true") === 0 && snapshot().hash === hashFull);
  const last = audit();
  check("addons:audit passes at the end", last.status === 0, last.stdout + last.stderr);

  expectStatus(await client.remove(), 200, "DELETE /upload");
}

try {
  await main();
} catch (error) {
  failures.push(String(error.message ?? error));
  console.error(error);
}
console.log(`${checks} checks, ${failures.length} failed`);
process.exit(failures.length === 0 ? 0 : 1);
