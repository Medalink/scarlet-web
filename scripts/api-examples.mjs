/**
 * Writes the example answers of the developer docs (resources/api-examples.json) from a running site that holds the
 * e2e fixture: the characters of e2e/fixture, which are invented. It reads the curl line of every endpoint off the
 * /developers page, swaps the catalog's example ids for ids the fixture holds, calls each one, shortens the lists
 * and keeps the answer as pretty JSON. An endpoint the fixture has no data for is left without an example. It exits
 * 1 when an answer holds a character name that is not a fixture folder, so a real player's name never gets in.
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

const FIXTURE_CHARACTERS = fs.readdirSync(path.join(root, "e2e", "fixture"));
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
  for (const [found] of text.matchAll(NAME)) if (!FIXTURE_CHARACTERS.includes(found)) foreign.add(`${name}: ${found}`);
  examples[name] = text.replaceAll(base, "https://scarlet.example");
}

if (foreign.size) {
  console.error(`names that are not fixture characters:\n${[...foreign].join("\n")}`);
  process.exit(1);
}
fs.writeFileSync(out, JSON.stringify(examples, null, 2) + "\n");
console.log(`${Object.keys(examples).length} examples written to ${path.relative(root, out)}`);
if (skipped.length) console.log(`no example for: ${skipped.join(", ")}`);
