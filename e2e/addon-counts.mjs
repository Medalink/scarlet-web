/**
 * Proof of Addon counts (docs/DESIGN.md, "Addon counts") over the real upload API of the running app. An invented
 * character uploads twelve fishing sessions of Scarlet's own rows and one `counts` part per day holding what another
 * addon counted for the same twelve trips, plus two lifetime reads of that addon; session 8 carries the instants the
 * owner's data has, a cast inside the session whose loot arrives 9.4 s after the stop. The checks read what the server
 * decided from its database: the totals (Scarlet's rows plus the claims that add), the same state reached by another
 * order or after a group was removed and restored, and `php artisan addons:audit`.
 *
 * Then small invented characters each hold one case a verdict can get wrong: a cast 0.8 s and one 1.0 s after the
 * stop of the claim before it, and one that was not looted (rule 3, seams), a session uploaded in two halves, a claim a catch started whose
 * neighbour's rows were not looted, a claim an `ff` count covers in part and one that only ends inside it (rule 5), a claim whose casts were all
 * cancelled inside a Scarlet session, two characters that hold one record (rule 4) with the lower id uploaded first,
 * with the rows on the higher id, with some rows on the lower and more on the higher, a day with no claim that the outdated pass must not queue twice, and linked by an account afterwards. Each case names the totals the history has.
 * A lane that waits only for the server to stop changing would read the last case's state, so it waits until no
 * addon day of the character has a null reconcile version (docs/DESIGN.md, Reconcile) and its row and claim counts
 * are the ones the case uploaded, and compares hashes only after that.
 *
 *   node e2e/boot.mjs -- node e2e/addon-counts.mjs
 *
 * It needs the app's database settings, which boot.mjs hands to its command. It is not part of the default lane.
 *
 * With both installs linked it also reads the public and the account's own fishing page and the character's page.
 * The account's characters page shows each lifetime read once, with what Scarlet and the counted addons add up to inside its window and the ceiling note of rule 7.
 * By default they show Scarlet, what the addons added and the total, which equal the database's; run with
 * ADDON_COUNTS_PAGES=false (boot.mjs passes it on) they show Scarlet's own numbers and nothing from addons. ADDON_PAGES_DIR=<folder> keeps the pages read, for a before and after comparison.
 */

import crypto from "node:crypto";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { linkToAccount, signIn } from "./checks/pages.mjs";
import { InstallClient } from "./client.mjs";
import { Http, Jar, expectJson, expectStatus, rollupsRefreshed, sleep } from "./support.mjs";

const repo = path.resolve(import.meta.dirname, "..");
const base = process.env.E2E_URL;
if (!base) throw new Error("E2E_URL is not set: run this through e2e/boot.mjs");

const NAME = "Quill-Fisher";
const TWIN = "Quill-Twin";
const ONLY = "Quill-Dockhand";
const GATHERER = "Quill-Herbalist";
const LABEL = "scarlet-web e2e addon counts install";
const LABEL_TWO = "scarlet-web e2e addon counts second install";
const FISHING_CAST = 18248;
const FISHING_BOBBER = 35591;
const SMALLFISH = 6291;
const ELWYNN = 1429;
const FISHING_SKILL_LINE = 356;
const STONETUSK_BOAR = 113;
const TRIPS_WITHOUT_LOOT = [0, 1];
const LOOT_DELAY = 0.6;
const GROUP_OF = { S: "core", Z: "core", ES: "core", K: "combat", C: "combat", CH: "combat", LO: "loot", SL: "professions" };
const GROUP_ORDER = ["core", "combat", "loot", "professions", "counts"];
const SETTLE_MS = 120_000;
const STEADY_POLLS = 6;
const POLL_MS = 500;
const WITHHELD = 7;
const FULL_TOTAL = "343/334/356";
const WHOLE_TOTAL = "366/334/360";
const BOUNDARY = 8;
let hashFull = null;

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
const instant = (iso) => Date.parse(iso) / 1000;
const BOUNDARY_CASTS = [
  { at: instant("2026-11-21T00:13:09.680Z"), seconds: 3, loot: null },
  { at: instant("2026-11-21T00:13:14.500Z"), seconds: 10.3, loot: { at: instant("2026-11-21T00:13:25.400Z"), quantity: 3 } },
];

function part(session, group, rows, extra, name = NAME) {
  const text = [["scarlet-part", 1, name, session, group].join("\t"), ...rows].join("\n") + "\n";
  const id = crypto.createHash("sha256").update(text).digest("hex");
  return { id, session, group, text, lines: rows.length, raw_bytes: Buffer.byteLength(text), ...extra };
}

function partsOf(session, lines, extra, name = NAME) {
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
    return part(session, group, rows, extra, name);
  });
}

const header = () => [
  [0, "S", "1.15.8", "ROGUE", "Human", 3, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [100, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  [150, "SL", FISHING_SKILL_LINE, 5, 75, 0, "s"],
  [160, "K", STONETUSK_BOAR],
];

function sessionOf(casts, { tag, loot = true, name = NAME } = {}) {
  const first = Math.min(...casts.map((cast) => cast.at));
  const start = Math.floor(first - 0.3);
  const ms = (t) => Math.round((t - start) * 1000);
  const lines = header();
  if (loot) lines.push([200, "LO", "c", STONETUSK_BOAR, 1, `${SMALLFISH}x1`, 1]);
  let end = 0;
  for (const cast of casts) {
    lines.push([ms(cast.at), "C", FISHING_CAST], [ms(cast.at + cast.seconds), "CH", FISHING_CAST, cast.seconds]);
    end = Math.max(end, ms(cast.at + cast.seconds));
    if (cast.loot) {
      lines.push([ms(cast.loot.at), "LO", "g", FISHING_BOBBER, 1, `${SMALLFISH}x${cast.loot.quantity}`, 0]);
      end = Math.max(end, ms(cast.loot.at));
    }
  }
  lines.push([end + 50, "ES"]);
  return { start, end: start + (end + 50) / 1000, parts: partsOf(start, lines, tag === undefined ? {} : { trip: tag }, name) };
}

function scarletParts(trip) {
  if (trip.at === BOUNDARY) return sessionOf(BOUNDARY_CASTS, { tag: trip.at });
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
  const quantities = Array.from({ length: trip.looted ?? 0 }, (_, k) => Math.floor(trip.quantity / trip.looted) + (k < trip.quantity % trip.looted ? 1 : 0));
  let looting = 0;
  const casts = roles.map((role, k) => {
    const at = first + k * spacing;
    const seconds = channel(role);
    return { at, seconds, loot: role === "looted" ? { at: at + seconds + LOOT_DELAY, quantity: quantities[looting++] } : null };
  });
  return sessionOf(casts, { tag: trip.at, loot: !TRIPS_WITHOUT_LOOT.includes(trip.at) });
}

function claimOf({ origin = "fl", store = "", scope = "c", window = "session", kind = "fishing", from, to, dims = "", amounts }) {
  return { to, day: Math.floor(to / 86400) * 86400, line: [origin, store, scope, window, kind, from ?? "", to, dims, amounts] };
}

function countsPartsOf(claims, name = NAME) {
  const days = [...new Set(claims.map((c) => c.day))].sort();
  return days.map((day) => part(day, "counts", claims.filter((c) => c.day === day).map((c) => `${c.to * 1000}\tYC\t${c.line.join("\t")}`), {}, name));
}

function countsParts(name = NAME) {
  const claims = TRIPS.map((trip) => claimOf({
    from: trip.to - trip.elapsed,
    to: trip.to,
    amounts: `casts=${trip.casts};landed=${trip.landed};items=${trip.items};unknown=${trip.unknown}`,
  }));
  for (const [origin, store, from, to, amounts] of LIFETIME_CLAIMS) claims.push(claimOf({ origin, store, scope: "a", window: "life", from, to, amounts }));
  return countsPartsOf(claims, name);
}

const scarlet = TRIPS.map(scarletParts);
for (let i = 1; i < scarlet.length; i += 1) {
  if (scarlet[i].start <= scarlet[i - 1].end) throw new Error(`the fixture's trip ${i} starts at ${scarlet[i].start}, before trip ${i - 1} ends at ${scarlet[i - 1].end}`);
}
const COUNTS = countsParts();
const TWIN_COUNTS = countsParts(TWIN);
const ALL = [...scarlet.flatMap((s) => s.parts), ...COUNTS];
const CLAIMS = TRIPS.length + LIFETIME_CLAIMS.length;
const BY_ID = new Map();
const known = (parts) => {
  for (const p of parts) BY_ID.set(p.id, p);
  return parts;
};
known(ALL);
known(TWIN_COUNTS);

const CASE_DAY = Date.parse("2026-11-10T12:00:00Z") / 1000;
const CHANNEL = 3;
const looted = (cast, quantity = 1) => ({ at: CASE_DAY + cast, seconds: CHANNEL, loot: { at: CASE_DAY + cast + CHANNEL + LOOT_DELAY, quantity } });
const kept = (cast) => ({ at: CASE_DAY + cast, seconds: CHANNEL, loot: null });
const claimIn = (from, to, amounts, extra = {}) => claimOf({ from: CASE_DAY + from, to: CASE_DAY + to, amounts, ...extra });

function db(statement) {
  const run = spawnSync("psql", ["-h", process.env.DB_HOST, "-p", process.env.DB_PORT, "-U", process.env.DB_USERNAME, "-d", process.env.DB_DATABASE, "-v", "ON_ERROR_STOP=1", "-At", "-F", "|", "-c", statement], { encoding: "utf8", env: process.env });
  if (run.status !== 0) throw new Error(`psql failed: ${run.stderr}\n${statement}`);
  return run.stdout.trim();
}

const artisan = (...args) => spawnSync("php", ["artisan", ...args], { cwd: repo, encoding: "utf8", env: process.env });

const own = (name) => `character_id = (select id from characters where name = '${name}')`;
const METRICS = ["casts", "landed", "items"];
const scarletRows = (name, session = "") => `
  (select count(*) from gathers where ${own(name)} and kind = 'fish'${session}),
  (select count(*) from gathers where ${own(name)} and kind = 'fish' and looted${session}),
  (select coalesce(sum(quantity), 0) from gather_items where ${own(name)} and kind = 'fish' and container_item_id is null${session})`;
const claimHash = (name) => `
  select coalesce(md5(string_agg(row_text, E'\\n' order by row_text)), 'none') from (
    select concat_ws('|', ch.name, c.origin, c.store, c.scope, c.window, c.kind, coalesce(c.from_at::text, ''), c.to_at, c.dims::text, c.amounts::text,
      coalesce(c.status, ''), coalesce(c.counted::text, ''), coalesce(c.evidence::text, ''), coalesce(r.key, ''), coalesce(c.counted_ok::text, ''),
      coalesce(c.level::text, ''), coalesce(c.zone_id::text, ''), coalesce(c.reconcile_version::text, '')) as row_text
    from addon_claims c join characters ch on ch.id = c.character_id left join rulesets r on r.id = c.ruleset_id
    where ch.name = '${name}') t`;
const rowHash = (name) => `
  select coalesce(md5(string_agg(row_text, E'\\n' order by row_text)), 'none') from (
    select concat_ws('|', g.at::text, g.looted::text, g.level::text, g.zone_id::text, g.counted::text, r.key) as row_text
    from gathers g join rulesets r on r.id = g.ruleset_id where g.${own(name)}
    union all
    select concat_ws('|', at::text, item_id, quantity, coalesce(container_item_id::text, '')) from gather_items where ${own(name)}) t`;

function snapshot(name) {
  const counted = METRICS.map((m) => `(select coalesce(sum((c.counted ->> '${m}')::numeric), 0) from addon_claims c where c.${own(name)} and c.status in ('counted', 'residual'))`);
  const state = JSON.parse(db(`
    select json_build_object(
      'sessions', (select count(*) from sessions where ${own(name)}),
      'claims', (select count(*) from addon_claims where ${own(name)}),
      'unreconciled', (select count(*) from addon_claims where ${own(name)} and status is null),
      'nullDays', (select count(*) from addon_days where ${own(name)} and reconcile_version is null),
      'scarlet', (select json_build_array(${scarletRows(name)})),
      'counted', json_build_array(${counted.join(", ")}),
      'hash', (${claimHash(name)}) || '|' || (${rowHash(name)}))`));
  const scarletTerm = state.scarlet.map(Number);
  const countedTerm = state.counted.map(Number);
  return {
    sessions: Number(state.sessions), gathers: scarletTerm[0], claims: Number(state.claims), unreconciled: Number(state.unreconciled), nullDays: Number(state.nullDays),
    scarlet: scarletTerm, counted: countedTerm, total: scarletTerm.map((n, k) => n + countedTerm[k]), hash: state.hash,
  };
}

function claimsOf(name) {
  return JSON.parse(db(`
    select coalesce(json_agg(t order by t.to_at, t.origin, t.id), '[]'::json) from (
      select c.id, c.origin, c.scope, c.window, c.from_at, c.to_at, c.status, c.evidence ->> 'rule' as rule, c.evidence #>> '{with,character}' as with_character,
        coalesce((c.evidence #>> '{rows,n}')::int, 0) as rows_n, jsonb_exists(c.evidence, 'session') as in_session,
        coalesce(c.counted ->> 'casts', '-') || '/' || coalesce(c.counted ->> 'landed', '-') || '/' || coalesce(c.counted ->> 'items', '-') as counted
      from addon_claims c join characters ch on ch.id = c.character_id where ch.name = '${name}') t`));
}

const client = new InstallClient(base, LABEL);
const second = new InstallClient(base, LABEL_TWO);
const http = new Http(base);
let checks = 0;
const failures = [];

function check(name, ok, detail = "") {
  checks += 1;
  console.log(`${ok ? "ok  " : "FAIL"} ${name}${ok || !detail ? "" : `: ${detail}`}`);
  if (!ok) failures.push(name);
}

async function waiting() {
  let total = 0;
  for (const app of [client, second]) {
    const answer = expectStatus(await app.status(), 200, "GET /upload/status");
    total += (expectJson(answer, "GET /upload/status").characters ?? []).reduce((n, c) => n + (c.sessions_waiting ?? 0), 0);
  }
  return total;
}

async function settle(names, ready, what) {
  const deadline = Date.now() + SETTLE_MS;
  let last = null;
  let steady = 0;
  while (Date.now() < deadline) {
    const now = names.map(snapshot);
    const key = JSON.stringify(now);
    steady = key === last ? steady + 1 : 0;
    last = key;
    const reconciled = now.every((s) => s.unreconciled === 0 && s.nullDays === 0);
    if (steady >= STEADY_POLLS && reconciled && ready(...now) && (await waiting()) === 0) return now;
    await sleep(POLL_MS);
  }
  throw new Error(`${what}: the server did not settle in ${SETTLE_MS / 1000}s, last seen ${JSON.stringify(names.map(snapshot))}`);
}

const settleOne = async (name, ready, what) => (await settle([name], ready, what))[0];

async function upload(held, name = NAME, app = client, groups = GROUP_ORDER) {
  const manifest = held.map(({ id, session, group, lines, raw_bytes }) => ({ id, session, group, lines, raw_bytes }));
  const answer = expectJson(expectStatus(await app.manifest(name, groups, manifest), 200, "POST /upload/manifest"), "POST /upload/manifest");
  const need = answer.need ?? [];
  if (need.length > 0) expectStatus(await app.parts(need.map((id) => BY_ID.get(id).text)), 200, "POST /upload/parts");
}

const audit = (name = NAME) => artisan("addons:audit", name);
const pagesOf = (name) => [
  ["public", "/professions/fishing", false],
  ["own", "/account/professions/fishing", true],
  ["character", `/c/${name}`, false],
  ["character-owner", `/c/${name}`, true],
];

function forgetReads() {
  const script = "local n = 0 for _, k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) n = n + 1 end return n";
  const bulk = (words) => `*${words.length}\r\n` + words.map((w) => `$${Buffer.byteLength(w)}\r\n${w}\r\n`).join("");
  return new Promise((resolve, reject) => {
    const socket = net.connect(Number(process.env.REDIS_PORT), process.env.REDIS_HOST);
    let replies = "";
    socket.on("data", (chunk) => {
      replies += chunk;
      if (/:\d+\r\n/.test(replies)) socket.end();
    });
    socket.once("error", reject);
    socket.once("close", resolve);
    socket.once("connect", () => socket.write(bulk(["SELECT", process.env.REDIS_CACHE_DB]) + bulk(["EVAL", script, "0", `*${process.env.CACHE_PREFIX}rollup-read:*`])));
  });
}
const PAGES_DIR = process.env.ADDON_PAGES_DIR ?? "";
const PAGES_ON = process.env.ADDON_COUNTS_PAGES !== "false";

const plain = (html) => html.replace(/<(script|style)[\s\S]*?<\/\1>/g, " ").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();

async function pagesRead(character = NAME, prefix = "") {
  await rollupsRefreshed({ http });
  await forgetReads();
  const jar = new Jar();
  await signIn(http, jar);
  const read = {};
  for (const [name, route, signed] of pagesOf(character)) {
    const answer = expectStatus(await http.page(route, signed ? { jar } : {}), 200, `GET ${route}${signed ? " signed in" : ""}`);
    read[name] = plain(answer.text);
    if (PAGES_DIR) fs.writeFileSync(path.join(PAGES_DIR, `${prefix}${name}.html`), answer.text);
  }
  return read;
}

const count = (n) => n.toLocaleString("en-US");

async function pages(state) {
  const shown = await pagesRead();
  const terms = ["Casts", "Catches", "Fish"].map((label, k) => `${label} ${count(state.scarlet[k])} ${count(state.counted[k])} ${count(state.total[k])}`).join(" ");
  const addonPages = ["public", "own"];
  if (PAGES_ON) {
    check(`the public and the account's own fishing page show the three terms, ${terms}`, addonPages.every((name) => shown[name].includes(terms)), addonPages.map((name) => shown[name].slice(0, 400)).join(" | "));
    check(`their heads count the total casts, ${count(state.total[0])}`, addonPages.every((name) => shown[name].includes(`Fishing ${count(state.total[0])} casts`)));
    check(`the character page counts the catches with the addons' ${count(state.counted[1])} beside them, to the owner and to a visitor`, ["character", "character-owner"].every((name) => shown[name].includes(`${count(state.total[1])} Fish caught From addons ${count(state.counted[1])}`)), shown.character);
    return;
  }
  check("with the flag off no page shows what addons counted", Object.values(shown).every((text) => !text.includes("From addons")));
  check(`the fishing pages count Scarlet's own ${count(state.scarlet[0])} casts and the character page its ${count(state.scarlet[1])} catches`, addonPages.every((name) => shown[name].includes(`Fishing ${count(state.scarlet[0])} casts`)) && shown.character.includes(`${count(state.scarlet[1])} Fish caught since`));
}

async function ensureLinked() {
  const linkedNow = expectJson(expectStatus(await client.me(), 200, "GET /installs/me"), "installs/me").linked === true;
  if (linkedNow) return;
  const jar = new Jar();
  await signIn(http, jar);
  await linkToAccount(http, jar, client);
}

async function addonOnly() {
  await fresh(client, second);
  const rows = Number(db("select count(*) from gathers where kind = 'fish'"));
  check("no character holds a Scarlet fishing row", rows === 0, `${rows} rows`);
  await ensureLinked();
  const claims = [claimIn(0, 600, "casts=40;landed=36;items=38"), claimIn(3600, 4200, "casts=25;landed=20;items=27")];
  await upload(known(countsPartsOf(claims, ONLY)), ONLY);
  const state = await settleOne(ONLY, (s) => s.claims === claims.length, "the addon-only character");
  check("the addon-only character: Scarlet holds 0 rows of it and its two claims count whole, 65/56/65", sum(state.scarlet) === "0/0/0" && sum(state.counted) === "65/56/65", `${sum(state.scarlet)} + ${sum(state.counted)}`);
  check("the audit passes for the addon-only character", audit(ONLY).status === 0);
  const shown = await pagesRead(ONLY, "only-");
  const addonPages = ["public", "own"];
  if (PAGES_ON) {
    const terms = ["Casts", "Catches", "Fish"].map((label, k) => `${label} ${count(state.scarlet[k])} ${count(state.counted[k])} ${count(state.total[k])}`).join(" ");
    check(`addon only, the public and the account's own fishing page show the three terms, ${terms}`, addonPages.every((name) => shown[name].includes(terms)), addonPages.map((name) => shown[name].slice(0, 500)).join(" | "));
    check(`addon only, their heads count the total casts, ${count(state.total[0])}`, addonPages.every((name) => shown[name].includes(`Fishing ${count(state.total[0])} casts`)));
    check("addon only, the pages show Scarlet's own figures empty, with no note that says no one has recorded", addonPages.every((name) => !shown[name].includes("What comes up") && !shown[name].includes("Numbers appear once") && !shown[name].includes("Scarlet records this once")));
    check(`addon only, the character page counts ${count(state.total[1])} fish caught from addons, to the owner and to a visitor`, ["character", "character-owner"].every((name) => shown[name].includes(`${count(state.total[1])} Fish caught From addons ${count(state.counted[1])}`)), shown.character.slice(0, 600));
    return;
  }
  check("addon only, with the flag off no page shows what addons counted", Object.values(shown).every((text) => !text.includes("From addons") && !text.includes(`${count(state.total[1])} Fish caught`)), Object.values(shown).map((text) => text.slice(0, 300)).join(" | "));
}

const sum = (rows) => rows.join("/");

async function gatheredAndReturned() {
  await fresh(client, second);
  await ensureLinked();
  const to = CASE_DAY;
  const day = Math.floor(to / 86400) * 86400;
  const claims = [
    claimOf({ origin: "mc", scope: "a", window: "life", kind: "herb", to, amounts: "attempts=84" }),
    claimOf({ origin: "mc", scope: "a", window: "life", kind: "ore", to, amounts: "attempts=272" }),
  ];
  const returned = part(day, "imports", [`3600000\tYU\tTSM\te\t2589\t20\t5`, `3600000\tYU\tTSM\tx\t2592\t12\t1`], {}, GATHERER);
  await upload(known([...countsPartsOf(claims, GATHERER), returned]), GATHERER, client, [...GROUP_ORDER, "imports"]);
  const imported = () => Number(db(`select count(*) from imported_records where ${own(GATHERER)}`));
  const state = await settleOne(GATHERER, (s) => s.claims === 2 && imported() === 2, "the herb and ore claims and the returned auctions");
  const kinds = db(`select string_agg(concat_ws('/', c.kind, c.scope, c.window, c.status), ' ' order by c.kind) from addon_claims c where c.${own(GATHERER)}`);
  check("an account life claim of kind herb and one of kind ore decode and reconcile to reference", kinds === "herb/a/life/reference ore/a/life/reference", kinds);
  check("they add nothing to any total, and no Scarlet row exists for them", sum(state.total) === "0/0/0" && state.counted.every((n) => n === 0) && state.gathers === 0, `${sum(state.scarlet)} + ${sum(state.counted)}`);
  const amounts = db(`select string_agg(c.kind || '=' || (c.amounts ->> 'attempts'), ' ' order by c.kind) from addon_claims c where c.${own(GATHERER)}`);
  check("the claims keep the addon's attempts, herb 84 and ore 272", amounts === "herb=84 ore=272", amounts);
  const lines = db(`select string_agg(concat_ws('/', kind, source, item_id, quantity, copper, via, at), ' ' order by at) from imported_records where ${own(GATHERER)}`);
  check("the returned auctions land as expired and cancel records: item, count, no copper, the stack size in via", lines === `expired/TSM/2589/20/0/5/${day + 3600} cancel/TSM/2592/12/0/1/${day + 7200}`, lines);
  const audited = audit(GATHERER);
  check("the audit passes with herb and ore claims", audited.status === 0, audited.stdout + audited.stderr);
  const jar = new Jar();
  await signIn(http, jar);
  const own_ = plain(expectStatus(await http.page(`/c/${GATHERER}`, { jar }), 200, `GET /c/${GATHERER} signed in`).text);
  check("the owner's character page shows 2 returned auctions and 32 items", own_.includes("Returned auctions 2") && own_.includes("Items returned 32"), own_.slice(0, 600));
  const roster = plain(expectStatus(await http.page("/account/characters", { jar }), 200, "GET /account/characters signed in").text);
  check("the account's characters page lists the character's returned auctions beside its sales and purchases", roster.includes("Imported history") && roster.includes("Returned"), roster.slice(0, 600));
  const visitor = plain(expectStatus(await http.page(`/c/${GATHERER}`), 200, `GET /c/${GATHERER}`).text);
  check("a visitor sees no imported history", !visitor.includes("Returned auctions"));
}
const claimOfTrip = (trip) => `${own(NAME)} and origin = 'fl' and addon_claims.window = 'session' and to_at = ${TRIPS[trip].to}`;
const claimCount = (name, where) => Number(db(`select count(*) from addon_claims c join characters ch on ch.id = c.character_id where ch.name = '${name}' and ${where}`));
const fresh = async (...apps) => {
  for (const app of apps) expectStatus(await app.remove(), 200, "DELETE /upload");
};

async function emptyDay() {
  const skipped = [{ to: CASE_DAY, day: Math.floor(CASE_DAY / 86400) * 86400, line: ["fl", "", "c", "session", "mining", CASE_DAY - 60, CASE_DAY, "", "casts=3"] }];
  const parts = known(countsPartsOf(skipped));
  const days = () => Number(db(`select count(*) from addon_days where ${own(NAME)}`));
  const unstamped = () => Number(db("select count(*) from addon_days where reconcile_version is null"));
  await fresh(client);
  await upload(parts);
  await settleOne(NAME, (s) => s.claims === 0 && days() === 1, "a day with no claim");
  check("a counts day whose only line is a kind nothing maps holds no claim and is stamped with the reconcile version", days() === 1 && unstamped() === 0, `${days()} days, ${unstamped()} without a version`);
  db(`update addon_days set reconcile_version = null where ${own(NAME)}`);
  const requeued = artisan("ingest:redecode", "--outdated");
  check("with its version cleared the outdated pass queues the character", /[1-9]\d* outdated sessions, addon days and characters queued/.test(requeued.stdout), requeued.stdout + requeued.stderr);
  await settleOne(NAME, (s) => s.claims === 0 && days() === 1, "the day is stamped again");
  const idle = artisan("ingest:redecode", "--outdated");
  check("with every day stamped the outdated pass queues nothing", unstamped() === 0 && /^0 outdated sessions, addon days and characters queued/.test(idle.stdout.trim()), idle.stdout + idle.stderr);
  const counts = countsPartsOf([claimIn(0, 300, "casts=10;landed=10;items=10;unknown=0")]);
  await upload(known([...counts]));
  const filled = await settleOne(NAME, (s) => s.claims === 1, "the first claim of a character with a stamped empty day");
  check("the first claim of a character with a stamped empty day is decided and the day stamped again, 10/10/10", sum(filled.total) === "10/10/10" && filled.nullDays === 0 && filled.unreconciled === 0, `${sum(filled.total)} with ${filled.nullDays} unstamped days`);
}

async function seam(label, gap, firstOfLater) {
  const earlier = sessionOf(Array.from({ length: 10 }, (_, i) => looted(10 + 28 * i)));
  const later = sessionOf([looted(firstOfLater), ...Array.from({ length: 9 }, (_, j) => looted(330 + 30 * j))]);
  const even = "casts=10;landed=10;items=10;unknown=0";
  const parts = known([...earlier.parts, ...later.parts, ...countsPartsOf([claimIn(0, 300, even), claimIn(300 + gap, 600, even)])]);
  await fresh(client);
  await upload(parts);
  const state = await settleOne(NAME, (s) => s.gathers === 20 && s.claims === 2 && s.sessions === 2, label);
  const claims = claimsOf(NAME);
  check(`${label}: Scarlet's 20 rows and the two claims make 20/20/20, nothing twice`, sum(state.total) === "20/20/20", `${sum(state.scarlet)} + ${sum(state.counted)} = ${sum(state.total)}`);
  check(`${label}: each claim holds 10 of the rows, so both are superseded`, claims.length === 2 && claims.every((c) => c.status === "superseded" && c.rows_n === 10), JSON.stringify(claims));
  const audited = audit();
  check(`${label}: the audit passes`, audited.status === 0, audited.stdout);
}

async function seamKept() {
  const earlier = sessionOf([...Array.from({ length: 5 }, (_, i) => looted(10 + 28 * i)), ...Array.from({ length: 5 }, (_, i) => kept(150 + 28 * i))]);
  const later = sessionOf([kept(300.8), ...Array.from({ length: 5 }, (_, j) => looted(330 + 30 * j)), ...Array.from({ length: 4 }, (_, j) => kept(490 + 30 * j))]);
  const even = "casts=10;landed=5;items=5;unknown=0";
  const label = "a cast that was not looted 0.8 s after a stop";
  await fresh(client);
  await upload(known([...earlier.parts, ...later.parts, ...countsPartsOf([claimIn(0, 300, even), claimIn(302, 600, even)])]));
  const state = await settleOne(NAME, (s) => s.gathers === 20 && s.claims === 2 && s.sessions === 2, label);
  const claims = claimsOf(NAME);
  check(`${label}: Scarlet's 20 rows, 10 of them looted, and the two claims make 20/10/10, no cast twice`, sum(state.total) === "20/10/10", `${sum(state.scarlet)} + ${sum(state.counted)} = ${sum(state.total)}`);
  check(`${label}: each claim holds 10 of the rows, so both are superseded`, claims.length === 2 && claims.every((c) => c.status === "superseded" && c.rows_n === 10), JSON.stringify(claims));
  const audited = audit();
  check(`${label}: the audit passes`, audited.status === 0, audited.stdout);
}

async function halves() {
  const casts = Array.from({ length: 20 }, (_, i) => looted(10 + 30 * i, [2, 2, 2, 1, 1, 1, 1, 1, 1, 1, 2, 2, 1, 1, 1, 1, 1, 1, 1, 1][i]));
  const whole = sessionOf(casts);
  const half = sessionOf(casts.slice(0, 10));
  if (half.start !== whole.start) throw new Error("the half session must start where the whole one does");
  const counts = countsPartsOf([claimIn(0, 620, "casts=25;landed=20;items=25;unknown=5")]);
  known([...whole.parts, ...half.parts, ...counts]);
  await fresh(client);
  await upload([...whole.parts, ...counts]);
  const reference = await settleOne(NAME, (s) => s.gathers === 20 && s.claims === 1, "the whole session");
  check("a session of 20 looted casts and its claim of 25 casts, 5 of them cancelled: 20/20/25", sum(reference.total) === "20/20/25", sum(reference.total));
  await fresh(client);
  await upload([...half.parts, ...counts]);
  const partial = await settleOne(NAME, (s) => s.gathers === 10 && s.claims === 1, "the first half");
  check("only the first 10 casts uploaded: the claim counts the half it does not cover, 13/10/13 (12.5 rounds up) beside Scarlet's 10/10/13", sum(partial.counted) === "13/10/13" && sum(partial.scarlet) === "10/10/13", `${sum(partial.scarlet)} + ${sum(partial.counted)}`);
  await upload([...whole.parts, ...counts]);
  const completed = await settleOne(NAME, (s) => s.gathers === 20 && s.claims === 1, "the second half arrives");
  check("the rest arrives: 20/20/25 and the hash of the whole upload", sum(completed.total) === "20/20/25" && completed.hash === reference.hash, `${sum(completed.total)} ${completed.hash} against ${reference.hash}`);
  check("the audit passes with the session in halves", audit().status === 0);
}

async function catchStarted() {
  const session = sessionOf([kept(5), kept(20), kept(35)], { loot: false });
  const parts = known([...session.parts, ...countsPartsOf([claimIn(0, 46, "casts=3;landed=3;items=2;unknown=0"), claimIn(56, 80, "items=3")])]);
  await fresh(client);
  await upload(parts);
  const state = await settleOne(NAME, (s) => s.gathers === 3 && s.claims === 2, "the catch-started pair");
  const [first, next] = claimsOf(NAME);
  check("a claim a catch started, beside a claim whose 3 rows were not looted: its 3 items count, 3/3/5", sum(state.total) === "3/3/5", `${sum(state.scarlet)} + ${sum(state.counted)} = ${sum(state.total)}`);
  check("the first claim is residual 0/3/2 and the catch-started one is counted 0/0/3", first.status === "residual" && first.counted === "0/3/2" && next.status === "counted" && next.counted === "-/-/3", JSON.stringify([first, next]));
  check("the audit passes with the catch-started pair", audit().status === 0);
}

async function covered() {
  const parts = known(countsPartsOf([claimIn(0, 7200, "casts=500;landed=450;items=470", { origin: "ff" }), claimIn(6600, 7200, "casts=40;landed=36;items=38")]));
  await fresh(client);
  await upload(parts);
  const state = await settleOne(NAME, (s) => s.claims === 2, "the covered claim");
  const [ff, fl] = claimsOf(NAME).sort((a, b) => a.origin.localeCompare(b.origin));
  check("an ff session of 7200 s whose last 600 s a Fishing Log session covers: 500/450/470, nothing lost", sum(state.total) === "500/450/470", `${sum(state.counted)}`);
  check("ff is residual 460/414/432 by rule 5 and fl is counted 40/36/38", ff.status === "residual" && ff.rule === "5" && ff.counted === "460/414/432" && fl.status === "counted" && fl.counted === "40/36/38", JSON.stringify([ff, fl]));
  const audited = audit();
  check("the audit passes with a residual that overlaps the claim above it", audited.status === 0, audited.stdout);
}

async function straddled() {
  const parts = known(countsPartsOf([claimIn(0, 3601, "casts=300;landed=270;items=280", { origin: "fl" }), claimIn(3540, 7200, "casts=500;landed=450;items=470", { origin: "ff" })]));
  await fresh(client);
  await upload(parts);
  const state = await settleOne(NAME, (s) => s.claims === 2, "the straddled claim");
  const [ff, fl] = claimsOf(NAME).sort((a, b) => a.origin.localeCompare(b.origin));
  check("an fl session that ends 61 s into an ff session: only 61 s of 3600 are shared, so 795/715/745 and not 500/450/470", sum(state.total) === "795/715/745", sum(state.counted));
  check("ff is residual 495/445/465 by rule 5 and fl is counted 300/270/280", ff.status === "residual" && ff.rule === "5" && ff.counted === "495/445/465" && fl.status === "counted" && fl.counted === "300/270/280", JSON.stringify([ff, fl]));
  const audited = audit();
  check("the audit passes with a residual that shares 61 s with the claim above it", audited.status === 0, audited.stdout);
}

async function cancelled() {
  const session = sessionOf([looted(780), looted(800), looted(1460), looted(1480)]);
  const none = "casts=3;landed=0;items=0;unknown=3";
  const parts = known([...session.parts, ...countsPartsOf([claimIn(1000, 1060, none), claimIn(5000, 5060, none)])]);
  await fresh(client);
  await upload(parts);
  const state = await settleOne(NAME, (s) => s.gathers === 4 && s.claims === 2, "the cancelled claims");
  const [inside, outside] = claimsOf(NAME);
  check("3 casts cancelled inside a Scarlet session add nothing, 3 outside any session count: 7/4/4", sum(state.total) === "7/4/4", `${sum(state.scarlet)} + ${sum(state.counted)}`);
  check("the claim inside the session is superseded and names the session, the one outside is counted whole", inside.status === "superseded" && inside.in_session && outside.status === "counted" && outside.counted === "3/0/0", JSON.stringify([inside, outside]));
  const audited = audit();
  check("the audit passes with a claim superseded by a session", audited.status === 0, audited.stdout);
}

async function twinFirst() {
  await fresh(client);
  await upload(TWIN_COUNTS, TWIN);
  await upload(COUNTS);
  const [mine, twin] = await settle([NAME, TWIN], (n, t) => n.claims === CLAIMS && t.claims === CLAIMS, "two characters with the same counts");
  check("the same counts on two characters with no rows: they count once, on the lower id (the twin uploaded first)", sum(twin.total) === WHOLE_TOTAL && sum(mine.total) === "0/0/0", `${sum(twin.total)} and ${sum(mine.total)}`);
  await upload(ALL);
  const [rows, copy] = await settle([NAME, TWIN], (n, t) => n.gathers === 343 && n.claims === CLAIMS && t.claims === CLAIMS, "the rows arrive on the higher id");
  const superseded = `c.scope = 'c' and c.status = 'superseded' and c.evidence ->> 'rule' = '4' and c.evidence #>> '{with,character}' = '${NAME}'`;
  check(`the rows arrive on the higher id: the counts move to it and the totals are ${FULL_TOTAL}`, sum(rows.total) === FULL_TOTAL && sum(copy.total) === "0/0/0", `${sum(rows.total)} and ${sum(copy.total)}`);
  check("the lower id's 12 character-scope claims are superseded by rule 4 and name the character that holds the rows", claimCount(TWIN, superseded) === TRIPS.length, `${claimCount(TWIN, superseded)} of ${TRIPS.length}`);
  check("the character with the rows is unchanged by the twin: the same hash as with no twin", rows.hash === hashFull, `${rows.hash} against ${hashFull}`);
  const audited = artisan("addons:audit");
  check("addons:audit passes for both characters", audited.status === 0, audited.stdout + audited.stderr);
}

async function twinWithMoreRows() {
  const record = "casts=10;landed=10;items=10;unknown=0";
  const copy = sessionOf([looted(10), looted(40)], { name: TWIN });
  const original = sessionOf(Array.from({ length: 10 }, (_, i) => looted(10 + 28 * i)));
  const counts = (name) => countsPartsOf([claimIn(0, 300, record)], name);
  await fresh(client);
  await upload(known([...copy.parts, ...counts(TWIN)]), TWIN);
  await upload(known([...original.parts, ...counts(NAME)]));
  const [mine, twin] = await settle([NAME, TWIN], (n, t) => n.gathers === 10 && n.claims === 1 && t.gathers === 2 && t.claims === 1, "two characters that hold one record and some rows");
  const [loser] = claimsOf(TWIN);
  check("a copy with 2 rows of its own in the window, uploaded first, and the original with 10: the record counts once, 10/10/10 on the original and 2/2/2 on the copy", sum(mine.total) === "10/10/10" && sum(twin.total) === "2/2/2", `${sum(mine.total)} and ${sum(twin.total)}`);
  check("the copy's claim is superseded by rule 4 and names the original, which holds more rows", loser.status === "superseded" && loser.rule === "4" && loser.with_character === NAME, JSON.stringify(loser));
  const audited = artisan("addons:audit");
  check("addons:audit passes for both characters", audited.status === 0, audited.stdout + audited.stderr);
}

async function linked() {
  await fresh(client, second);
  await upload(ALL);
  await upload(TWIN_COUNTS, TWIN, second);
  const [mine, copy] = await settle([NAME, TWIN], (n, t) => n.gathers === 343 && n.claims === CLAIMS && t.claims === CLAIMS, "two installs that no account links");
  check(`two installs of nobody's account: each counts its own, ${FULL_TOTAL} and ${WHOLE_TOTAL}`, sum(mine.total) === FULL_TOTAL && sum(copy.total) === WHOLE_TOTAL, `${sum(mine.total)} and ${sum(copy.total)}`);
  const jar = new Jar();
  await signIn(http, jar);
  await linkToAccount(http, jar, client);
  await linkToAccount(http, jar, second);
  const superseded = `c.scope = 'c' and c.status = 'superseded' and c.evidence ->> 'rule' = '4' and c.evidence #>> '{with,character}' = '${NAME}'`;
  const [after, twin] = await settle([NAME, TWIN], (n, t) => n.claims === CLAIMS && claimCount(TWIN, superseded) === TRIPS.length && t.claims === CLAIMS, "the link of the second install");
  check(`linking both installs to one account decides again with no new upload: the twin counts nothing, ${FULL_TOTAL}`, sum(twin.total) === "0/0/0" && sum(after.total) === FULL_TOTAL && after.hash === hashFull, `${sum(after.total)} and ${sum(twin.total)}`);
  const audited = artisan("addons:audit");
  check("addons:audit passes after the link", audited.status === 0, audited.stdout + audited.stderr);
  await pages(after);
  await accountReferences(jar, after);
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const shownDay = (epoch) => {
  const d = new Date(epoch * 1000);
  return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
};

async function accountReferences(jar, state) {
  const read = async () => plain(expectStatus(await http.page("/account/characters", { jar }), 200, "GET /account/characters signed in").text);
  const heading = "Mastercraft lifetime fishing";
  let shown = await read();
  if (!PAGES_ON) {
    check("with the flag off the characters page reads no account claim", !shown.includes(heading));
    return;
  }
  for (let i = 0; i < 40 && !shown.includes(heading); i += 1) {
    await sleep(POLL_MS);
    shown = await read();
  }
  const labels = ["Casts", "Catches", "Fish"];
  const total = state.total.map(count).join(" ");
  const expected = [];
  const notes = [];
  for (const [, , from, to, amounts] of LIFETIME_CLAIMS) {
    const claimed = amounts.split(";").map((pair) => Number(pair.split("=")[1]));
    expected.push(`Mastercraft ${shownDay(from)} ${shownDay(to)} ${claimed.map(count).join(" ")} Scarlet and counted addons ${shownDay(from)} ${shownDay(to)} ${total}`);
    claimed.forEach((value, k) => {
      if (state.total[k] > value * 1.025) notes.push(`${labels[k]}: ${count(state.total[k])} against ${count(value)} in Mastercraft, ${((state.total[k] / value - 1) * 100).toFixed(1)}% above.`);
    });
  }
  const start = shown.indexOf(heading);
  const module = start < 0 ? "" : shown.slice(start, start + 1200);
  console.log(`account characters page: ${module}`);
  check(`the characters page shows "${heading}" once`, shown.split(heading).length === 2, module);
  check(`its two reads show what Mastercraft counted beside Scarlet and the counted addons, ${state.total.join("/")}`, expected.every((row) => shown.includes(row)), module);
  check("each read shows once though two characters carry it", shown.split("2,488").length === 2 && shown.split("2,254").length === 2);
  check("the ceiling note shows for the read of 17 Nov 2026 and for no other", notes.length === 3 && notes.every((note) => shown.includes(note)), notes.join(" | "));
  check("the read of 6 Nov 2026 raises no note", !/against 2,/.test(shown));
}

async function main() {
  for (const app of [client, second]) {
    const registered = await app.register();
    if (registered.status !== 200 && registered.status !== 201) expectStatus(registered, 201, "POST /installs");
  }
  await fresh(client, second);
  console.log(`fixture: ${scarlet.length} Scarlet trips in ${scarlet.flatMap((s) => s.parts).length} parts, ${COUNTS.length} counts parts, ${ALL.length} in all`);

  await upload(ALL);
  const full = await settleOne(NAME, (s) => s.sessions === TRIPS.length && s.gathers === 343 && s.claims === CLAIMS, "the full upload");
  hashFull = full.hash;
  check("the full upload: Scarlet's own rows are 343 casts, 295 landed, 316 items", sum(full.scarlet) === "343/295/316", sum(full.scarlet));
  check(`the totals, Scarlet plus counted, are ${FULL_TOTAL}`, sum(full.total) === FULL_TOTAL, `${sum(full.scarlet)} + ${sum(full.counted)} = ${sum(full.total)}`);
  check("every claim has a status", full.unreconciled === 0);
  const [eight, nine] = [BOUNDARY, BOUNDARY + 1].map((t) => claimsOf(NAME).find((c) => c.origin === "fl" && c.to_at === TRIPS[t].to));
  check("session 8's second cast starts inside its window and its loot arrives 9.4 s after the stop: session 8's claim holds its 2 rows, session 9's its 6, and both count 0", eight?.rows_n === 2 && nine?.rows_n === 6 && eight.counted === "0/0/0" && nine.counted === "0/0/0", JSON.stringify([eight, nine]));
  const first = audit();
  console.log(first.stdout.trim());
  check("addons:audit passes", first.status === 0, first.stdout + first.stderr);
  check("the small lifetime read raises a ceiling note", /store '2026-11-17' fishing casts/.test(first.stdout), first.stdout);
  check("the large lifetime read raises none", !/says 2488/.test(first.stdout));

  const blank = `update addon_claims set status = null, counted = null, evidence = null, ruleset_id = null, counted_ok = null, level = null, zone_id = null, reconcile_version = null where ${own(NAME)}`;
  db(blank);
  const firstRun = artisan("addons:reconcile", NAME);
  const afterBlank = snapshot(NAME);
  const secondRun = artisan("addons:reconcile", NAME);
  const twice = snapshot(NAME);
  check(`reconcile from blank columns writes ${CLAIMS} claims and reproduces the hash`, firstRun.stdout.includes(`${CLAIMS} claims changed`) && afterBlank.hash === hashFull, `${firstRun.stdout.trim()} ${firstRun.stderr}`);
  check("reconcile twice changes nothing: 0 claims changed, same hash", secondRun.stdout.includes(": 0 claims changed") && twice.hash === hashFull, secondRun.stdout.trim());

  const daysOf = (where) => Number(db(`select count(*) from addon_days where ${own(NAME)} and ${where}`));
  check("every addon day of the character carries the current reconcile version", daysOf("reconcile_version is not null") === COUNTS.length && daysOf("reconcile_version is null") === 0, `${daysOf("reconcile_version is null")} days without one`);
  db(blank);
  db(`update addon_days set reconcile_version = null where ${own(NAME)}`);
  const outdatedRun = artisan("ingest:redecode", "--outdated");
  const outdated = await settleOne(NAME, (s) => s.claims === CLAIMS && s.gathers === 343, "the outdated pass");
  check("the outdated pass queues the character and decides every claim again: the same hash", /[1-9]\d* outdated sessions, addon days and characters queued/.test(outdatedRun.stdout) && outdated.hash === hashFull, `${outdatedRun.stdout.trim()} ${outdatedRun.stderr}`);
  check("the outdated pass stamps every addon day", daysOf("reconcile_version is null") === 0 && daysOf("reconcile_version is not null") === COUNTS.length);

  expectStatus(await client.request("DELETE", "/api/v1/upload/groups/counts"), 200, "DELETE groups/counts");
  const noCounts = await settleOne(NAME, (s) => s.claims === 0 && s.gathers === 343, "the counts group removal");
  check("counts group removed: no claims, Scarlet's own 343/295/316 stays", sum(noCounts.total) === "343/295/316" && noCounts.gathers === 343, sum(noCounts.total));
  check("the audit passes with no claims", audit().status === 0);
  expectStatus(await client.request("PUT", "/api/v1/upload/groups/counts"), 200, "PUT groups/counts");
  await upload(ALL);
  const restoredCounts = await settleOne(NAME, (s) => s.claims === CLAIMS && s.gathers === 343, "the counts group restore");
  check("counts group restored: the same hash", restoredCounts.hash === hashFull);

  expectStatus(await client.request("DELETE", "/api/v1/upload/groups/professions"), 200, "DELETE groups/professions");
  const noRows = await settleOne(NAME, (s) => s.gathers === 0 && s.claims === CLAIMS, "the professions group removal");
  check(`professions group removed: no Scarlet rows, every claim counts whole, ${WHOLE_TOTAL}`, sum(noRows.total) === WHOLE_TOTAL, sum(noRows.total));
  check("the audit passes with no Scarlet rows", audit().status === 0);
  expectStatus(await client.request("PUT", "/api/v1/upload/groups/professions"), 200, "PUT groups/professions");
  await upload(ALL);
  const restoredRows = await settleOne(NAME, (s) => s.gathers === 343 && s.claims === CLAIMS, "the professions group restore");
  check("professions group restored: the same hash", restoredRows.hash === hashFull);

  await fresh(client);
  await upload(ALL.filter((p) => p.trip !== WITHHELD));
  const without = await settleOne(NAME, (s) => s.claims === CLAIMS && s.gathers === 123, "the upload without trip 7");
  check("trip 7 withheld: its claim counts whole, totals 347/334/359", sum(without.total) === "347/334/359" && sum(without.scarlet) === "123/80/84", `${sum(without.scarlet)} + ${sum(without.counted)}`);
  check("the audit passes with trip 7 withheld", audit().status === 0);
  check("trip 7's claim is counted", db(`select status from addon_claims where ${claimOfTrip(WITHHELD)}`) === "counted");
  await upload(ALL);
  const arrived = await settleOne(NAME, (s) => s.gathers === 343 && s.claims === CLAIMS, "trip 7 arrives");
  const stored = db(`select concat_ws('|', evidence #>> '{diff,casts}', evidence #>> '{diff,landed}', evidence #>> '{diff,items}') from addon_claims where ${claimOfTrip(WITHHELD)}`).split("|").map(Number);
  const rows = db(`select ${scarletRows(NAME, ` and session_start = ${scarlet[WITHHELD].start}`)}`).split("|").map(Number);
  const moved = arrived.total.map((n, k) => n - without.total[k]);
  const expected = rows.map((n, k) => n - stored[k]);
  check("trip 7 arrives: each total moves by the trip's Scarlet rows minus the claim's stored difference", sum(moved) === sum(expected), `moved ${sum(moved)}, rows ${sum(rows)} minus stored ${sum(stored)} is ${sum(expected)}`);
  check(`trip 7 arrived: the totals are ${FULL_TOTAL} and the hash is the full upload's`, sum(arrived.total) === FULL_TOTAL && arrived.hash === hashFull, sum(arrived.total));

  await fresh(client);
  const held = ALL.filter((p) => p.group === "counts");
  await upload(held);
  const countsOnly = await settleOne(NAME, (s) => s.claims === CLAIMS && s.gathers === 0, "counts first");
  check(`counts first, no Scarlet session yet: every claim counts whole, ${WHOLE_TOTAL}`, sum(countsOnly.total) === WHOLE_TOTAL, sum(countsOnly.total));
  for (let i = TRIPS.length - 1; i >= 0; i -= 1) {
    held.push(...scarlet[i].parts);
    await upload(held);
  }
  const reversed = await settleOne(NAME, (s) => s.gathers === 343 && s.claims === CLAIMS, "sessions in reverse order");
  check(`reversed upload order: ${FULL_TOTAL} and the same hash`, sum(reversed.total) === FULL_TOTAL && reversed.hash === hashFull, `${sum(reversed.total)} ${reversed.hash} against ${hashFull}`);

  await upload(TWIN_COUNTS, TWIN);
  const [, twinAfter] = await settle([NAME, TWIN], (n, t) => n.gathers === 343 && t.claims === CLAIMS, "the twin's counts");
  const twinRule = `c.scope = 'c' and c.status = 'superseded' and c.evidence ->> 'rule' = '4' and c.evidence #>> '{with,character}' = '${NAME}'`;
  check("a second character of the install holds the same counts: its 12 character-scope claims are superseded by rule 4 and name the first", claimCount(TWIN, twinRule) === TRIPS.length, `${claimCount(TWIN, twinRule)} of ${TRIPS.length}`);
  check("its 2 account-scope claims are reference and nothing of it counts", claimCount(TWIN, "c.scope = 'a' and c.status = 'reference'") === LIFETIME_CLAIMS.length && sum(twinAfter.total) === "0/0/0");
  check("the first character is unchanged by it: the same hash", snapshot(NAME).hash === hashFull);
  const twinAudit = artisan("addons:audit");
  check("addons:audit passes for both characters", twinAudit.status === 0, twinAudit.stdout + twinAudit.stderr);
  await upload([], TWIN);
  await settle([NAME, TWIN], (n, t) => t.claims === 0 && n.gathers === 343, "the twin lists no parts");
  check("the twin lists no parts: its claims go and the first character is unchanged", claimCount(TWIN, "true") === 0 && snapshot(NAME).hash === hashFull);
  const last = audit();
  check("addons:audit passes at the end", last.status === 0, last.stdout + last.stderr);

  await emptyDay();
  await seam("a stop and a cast 0.8 s later: windows [0,300] and [302,600], the later session's first cast at 300.8", 2, 300.8);
  await seam("a cast at the stop itself: windows [0,300] and [301,600], the later session's first cast at 300.0", 1, 300);
  await seamKept();
  await halves();
  await catchStarted();
  await covered();
  await straddled();
  await cancelled();
  await twinFirst();
  await twinWithMoreRows();
  await linked();
  await addonOnly();
  await gatheredAndReturned();

  await fresh(client, second);
}

try {
  await main();
} catch (error) {
  failures.push(String(error.message ?? error));
  console.error(error);
}
console.log(`${checks} checks, ${failures.length} failed`);
process.exit(failures.length === 0 ? 0 : 1);
