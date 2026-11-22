/**
 * Decodes the Spell-Ledger fixture with the real scarlet-part binary and compares the rows of the six class tables
 * (docs/ROWS.md, "The class tables") with e2e/ledger-expected.mjs, which was worked out by hand from the fixture's
 * lines in e2e/ledger-lines.mjs. A second decode of the same parts must write the same bytes. It needs no server.
 *
 *   node e2e/class-rows.mjs [--bin <scarlet-part>]
 *
 * Without --bin it uses SCARLET_PART_BIN, then part/target/release/scarlet-part (build it with `cargo build --release`
 * in part/). Exit 0 when every table matches, 1 when a row differs, 2 on a usage error.
 */

import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { EXPECTED } from "./ledger-expected.mjs";

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), "..");
const FIXTURE = path.join(ROOT, "e2e", "fixture", "Spell-Ledger");
const MAX_OUTPUT = 64 * 1024 * 1024;

function usage(message) {
  console.error(`${message}\nusage: node e2e/class-rows.mjs [--bin <scarlet-part>]`);
  process.exit(2);
}

function binary(argv) {
  if (argv.length > 0 && (argv[0] !== "--bin" || argv.length !== 2)) usage(`unknown or incomplete argument ${argv[0]}`);
  const found = argv[1] ?? process.env.SCARLET_PART_BIN ?? ["scarlet-part", "scarlet-part.exe"].map((name) => path.join(ROOT, "part", "target", "release", name)).find((file) => fs.existsSync(file));
  if (!found || !fs.existsSync(found)) usage("no scarlet-part binary found");
  return found;
}

function decode(bin) {
  const manifest = JSON.parse(fs.readFileSync(path.join(FIXTURE, "manifest.json"), "utf8"));
  const files = manifest.parts.map((part) => path.join(FIXTURE, `${part.id}.part`));
  const run = spawnSync(bin, ["decode", ...files], { encoding: "utf8", maxBuffer: MAX_OUTPUT });
  if (run.status !== 0) {
    console.error(`scarlet-part decode failed (${run.status}): ${run.stderr.slice(0, 500)}`);
    process.exit(1);
  }
  return run.stdout;
}

const bin = binary(process.argv.slice(2));
const output = decode(bin);
const rows = output.trim().split("\n").map((line) => JSON.parse(line));
let failed = 0;

if (decode(bin) !== output) {
  failed += 1;
  console.log("FAIL a second decode of the same parts wrote other bytes");
}

for (const [table, shape] of Object.entries(EXPECTED)) {
  const keyOf = (row) => JSON.stringify(shape.key.map((column) => row[column]));
  const actual = new Map(rows.filter((row) => row.t === table).map((row) => [keyOf(row), shape.columns.map((column) => row[column])]));
  const wanted = new Map(shape.rows.map((values) => [keyOf(Object.fromEntries(shape.columns.map((column, at) => [column, values[at]]))), values]));
  let matching = 0;
  for (const [key, values] of wanted) {
    const got = actual.get(key);
    if (got === undefined) {
      failed += 1;
      console.log(`FAIL ${table} ${key}: no such row`);
    } else if (JSON.stringify(got) !== JSON.stringify(values)) {
      failed += 1;
      console.log(`FAIL ${table} ${key}\n  expected ${JSON.stringify(values)}\n  saw      ${JSON.stringify(got)}`);
    } else {
      matching += 1;
    }
  }
  for (const [key, values] of actual) {
    if (!wanted.has(key)) {
      failed += 1;
      console.log(`FAIL ${table} ${key}: a row the fixture does not expect ${JSON.stringify(values)}`);
    }
  }
  console.log(`${table}: ${matching} of ${wanted.size} expected rows match, ${actual.size} written`);
}

console.log(failed === 0 ? "class rows: every table matches" : `class rows: ${failed} differences`);
process.exit(failed === 0 ? 0 : 1);
