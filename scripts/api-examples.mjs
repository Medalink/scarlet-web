/**
 * Writes the example answers of the developer docs (resources/api-examples.json) from a running site that holds the
 * e2e fixture: the characters of e2e/fixture, which are invented. It reads the curl line of every endpoint off the
 * /developers page, swaps the catalog's example ids for ids the fixture holds, calls each one, shortens the lists
 * and keeps the answer as pretty JSON. An endpoint the fixture has no data for is left without an example. It exits
 * 1, and writes nothing, when the site lists a character, or an answer holds a Name-Realm style value, that is not
 * one of the fixture folders of e2e/fixture or a character the e2e line files define, so a real player's name never
 * gets in.
 *
 *   node e2e/run.mjs --url http://127.0.0.1:8612 --leave
 *   node scripts/api-examples.mjs --url http://127.0.0.1:8612
 *
 * Run it again after the fixture or an answer changes; the file is the same for the same data.
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), "..");
const args = process.argv.slice(2);
const option = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
const base = option("--url", process.env.E2E_URL ?? "").replace(/\/$/, "");
const out = path.resolve(root, option("--out", "resources/api-examples.json"));
if (!/^http:\/\/(127\.0\.0\.1|localhost|\[::1\])(:\d+)?$/.test(base)) {
  console.error("usage: node scripts/api-examples.mjs --url http://127.0.0.1:<port>  (a local site that holds the e2e fixture)");
  process.exit(2);
}

const e2e = path.join(root, "e2e");
const QUOTED_NAME = /"([A-Z][a-z]+-[A-Z][a-z]+)"/g;
const LINE_FILES = fs.readdirSync(e2e).filter((file) => /-lines\.mjs$|^(build-fixture|addon-counts)\.mjs$/.test(file));
const ALLOWED = new Set([
  ...fs.readdirSync(path.join(e2e, "fixture")),
  ...LINE_FILES.flatMap((file) => [...fs.readFileSync(path.join(e2e, file), "utf8").matchAll(QUOTED_NAME)].map(([, name]) => name)),
]);
const CHARACTER = "Frost-Weaver";
const REWRITES = [
  [/\/characters\/[^/?]+/, `/characters/${CHARACTER}`],
  [/\/creatures\/\d+/, "/creatures/6"],
  [/\/items\/\d+/, "/items/2589"],
  [/\/zones\/-?\d+/, "/zones/1429"],
  [/q=ash/, "q=elw"],
  [/\/rulesets\/normal/, "/rulesets/unidentified"],
];
const CHANGES_EVERY_READ = new Set(["updated_at"]);
const KEPT_ROWS = [3, 2, 1];
const MAX_CHARACTERS = 2600;
const LISTED_ROWS = 100;
const NAME = /\b[A-Z][a-z]+-[A-Z][a-z]+\b/g;

const get = async (url) => {
  const response = await fetch(url, { headers: { Accept: "application/json" } });
  return { status: response.status, text: await response.text() };
};

const shorten = (value, rows) => {
  if (Array.isArray(value)) return value.slice(0, rows).map((item) => shorten(item, rows));
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, CHANGES_EVERY_READ.has(key) ? "2026-11-28T00:00:00.000000Z" : shorten(item, rows)]));
  return value;
};

const listed = async () => {
  const names = new Set();
  const boards = JSON.parse((await get(`${base}/api/v1/public/leaderboards`)).text).data.map((board) => board.board);
  for (const board of boards) {
    for (let cursor = ""; cursor !== null; ) {
      const answer = JSON.parse((await get(`${base}/api/v1/public/leaderboards/${board}?limit=${LISTED_ROWS}${cursor ? `&cursor=${cursor}` : ""}`)).text);
      for (const row of answer.data) names.add(row.name);
      cursor = answer.meta?.next_cursor ?? null;
    }
  }
  return names;
};

const strangers = [...(await listed())].filter((name) => !ALLOWED.has(name));
if (strangers.length) {
  console.error(`the site lists characters that are not fixture characters, so nothing was written:\n${strangers.join("\n")}`);
  process.exit(1);
}

const page = await get(`${base}/developers`);
if (page.status !== 200) throw new Error(`/developers answered ${page.status}`);
const decode = (text) => text.replaceAll("&amp;", "&").replaceAll("&lt;", "<").replaceAll("&gt;", ">").replaceAll("&quot;", '"').replaceAll("&#039;", "'");
const lines = [...page.text.matchAll(/<section class="ep" id="ep-([a-z0-9-]+)">[\s\S]*?<pre class="json">curl ([^<\s]+)<\/pre>/g)].map(([, name, url]) => [name, decode(url)]);
if (lines.length === 0) throw new Error("the developers page lists no curl line");

const examples = {};
const skipped = [];
const foreign = new Set();
for (const [name, url] of lines) {
  let target = new URL(url).pathname + new URL(url).search;
  for (const [pattern, replacement] of REWRITES) target = target.replace(pattern, replacement);
  if (/\/sessions\/\d+/.test(target)) {
    const list = JSON.parse((await get(`${base}/api/v1/public/characters/${CHARACTER}/sessions?limit=1`)).text);
    target = target.replace(/\/sessions\/\d+/, `/sessions/${list.data.sessions[0].start}`);
  }
  const answer = await get(base + target);
  if (answer.status !== 200) {
    skipped.push(`${name} (${answer.status} ${target})`);
    continue;
  }
  const parsed = JSON.parse(answer.text);
  let text = JSON.stringify(shorten(parsed, KEPT_ROWS[0]), null, 2);
  for (const rows of KEPT_ROWS.slice(1)) if (text.length > MAX_CHARACTERS) text = JSON.stringify(shorten(parsed, rows), null, 2);
  for (const [found] of text.matchAll(NAME)) if (!ALLOWED.has(found)) foreign.add(`${name}: ${found}`);
  examples[name] = text.replaceAll(base, "https://scarlet.example");
}

if (foreign.size) {
  console.error(`Name-Realm values that are not fixture characters, so nothing was written:\n${[...foreign].join("\n")}`);
  process.exit(1);
}
fs.writeFileSync(out, JSON.stringify(examples, null, 2) + "\n");
console.log(`${Object.keys(examples).length} examples written to ${path.relative(root, out)}`);
if (skipped.length) console.log(`no example for: ${skipped.join(", ")}`);
