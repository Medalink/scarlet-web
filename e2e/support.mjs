/**
 * What every lane of the end-to-end checks shares: the HTTP helper that counts requests per rate limiter, the
 * cookie jar, the fixture loader, the status assertion and a bounded pool.
 */

import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const NAME = "Testa-Fixture";
/** NAME as pages draw it: first name and surname. */
export const SHOWN = NAME.replace("-", " ");
export const PROBE = "Probe-Lane";
/** A third uploader that heard the same hardcore death notice as PROBE in the same minute. */
export const ECHO = "Echo-Witness";
/** A character of a second install that no account is linked to, with tailoring crafts the other characters have none of. */
export const STRANGER = "Tailor-Stranger";
/** A second character of the stranger install, with the lines of the class tables (docs/ROWS.md) and no crafts or camps. */
export const LEDGER = "Spell-Ledger";
/** A Warlock of the first install with six hours played over levels 50 to 52, whose lines make every module of the Warlock class page. */
export const WARLOCK = "Shard-Keeper";
/** A Mage of the stranger install with six hours played over levels 50 to 52, whose lines make every module of the Mage class page. */
export const MAGE = "Frost-Weaver";
/** A Druid of the stranger install with six hours played over levels 50 to 52, whose lines make every module of the Druid class page. */
export const DRUID = "Moon-Walker";
/** A Warrior of the first install with six hours played over levels 50 to 52, whose lines make every module of the Warrior class page. */
export const WARRIOR = "Iron-Vanguard";

/** A Paladin of the first install with six hours played over levels 50 to 52, whose lines make every module of the Paladin class page. */
export const PALADIN = "Oath-Bearer";

/** A Hunter of the first install with six hours played over levels 50 to 52, whose lines make every module of the Hunter class page. */
export const HUNTER = "Pack-Warden";

/** A Rogue of the stranger install with six hours played over levels 50 to 52, whose lines make every module of the Rogue class page. */
export const ROGUE = "Pocket-Shade";
export const LOCAL_HOSTS = ["127.0.0.1", "localhost", "[::1]"];
/** A remember cookie makes EdgeCacheable treat the reader as signed in, so a page read reaches the origin, not the 60 s page cache. */
export const ORIGIN_COOKIE = { name: "remember_web_e2e", value: "lane" };
/** True when a caller that guarantees the whole setup (e2e/boot.mjs) asks that a check it set up for never skips. */
export const STRICT = process.env.E2E_STRICT === "1";
const MAX_REDIRECTS = 5;
export const ORIGIN_HEADERS = { Cookie: `${ORIGIN_COOKIE.name}=${ORIGIN_COOKIE.value}` };

const FIXTURES = path.join(path.dirname(fileURLToPath(import.meta.url)), "fixture");

/** The characters of the stranger install: the only ones whose fixture folder is not the first install's. */
export const STRANGER_CHARACTERS = [STRANGER, LEDGER, MAGE, DRUID, ROGUE];

/** The characters of the first install: every folder of e2e/fixture the stranger install does not hold, so a new class fixture needs no list to join. */
export const INSTALL_CHARACTERS = fs.readdirSync(FIXTURES).filter((name) => !STRANGER_CHARACTERS.includes(name)).sort();

export class Skip extends Error {}

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * The limiter a path counts against, or null when none applies. Pages are limited per address, the public API per
 * address, registration per address and hour, signed requests per address and install, and the account routes by
 * `throttle:60,1`.
 */
export function limiterOf(pathname) {
  if (pathname.startsWith("/api/v1/public")) return "public api";
  if (pathname === "/api/v1/installs") return "registration";
  if (pathname.startsWith("/api/v1/")) return "signed api";
  if (/^\/(login|auth|account|link)(\/|$)/.test(pathname)) return "throttle:60,1";
  if (pathname === "/" || pathname === "/demo" || pathname === "/up" || pathname.startsWith("/horizon/") || /\.[a-z0-9]+$/.test(pathname)) return null;
  return "pages";
}

const REFRESH_WAIT_MS = 60_000;
const REFRESH_POLL_MS = 400;
const IDLE_READS = 3;

/** The path of a public API read with a random `limit`, so a rerun never reads the origin's 30 s cached answer of the last one. */
export function uncached(route, limit) {
  const url = new URL(route, "http://e2e.invalid");
  url.searchParams.set("limit", String(limit));
  return url.pathname + url.search;
}

/** The public API answer of `route`, read once per run and shared by every check that asks for it. */
export function answerOf(ctx, route) {
  ctx.answers ??= new Map();
  ctx.limit ??= crypto.randomInt(2, 101);
  if (!ctx.answers.has(route)) ctx.answers.set(route, ctx.http.api("/api/v1/public" + uncached(route, ctx.limit)));
  return ctx.answers.get(route);
}

/**
 * Settles when the rollups hold the upload. The server keeps an answer read from a rollup for 60 s, an empty one too, so a
 * read made before the refresh job has run shows nothing for a minute. Horizon lists a refresh that waits for its debounce
 * as pending, so no pending job on three reads in a row means the refresh has run. The list answers where APP_ENV is local.
 */
export function rollupsRefreshed(ctx) {
  ctx.refreshed ??= waitForRefresh(ctx.http);
  return ctx.refreshed;
}

async function waitForRefresh(http) {
  const deadline = Date.now() + REFRESH_WAIT_MS;
  let idle = 0;
  while (Date.now() < deadline) {
    const answer = await http.api("/horizon/api/jobs/pending");
    if (answer.status !== 200 || typeof answer.json?.total !== "number") {
      throw new Error(`GET /horizon/api/jobs/pending: expected the job list (it answers where APP_ENV=local), saw ${answer.status}: ${answer.text.slice(0, 300)}`);
    }
    idle = answer.json.total === 0 ? idle + 1 : 0;
    if (idle === IDLE_READS) return;
    await sleep(REFRESH_POLL_MS);
  }
  throw new Error(`Horizon still had jobs pending ${REFRESH_WAIT_MS / 1000}s after the upload was decoded: is its rollups queue running?`);
}

export class Http {
  base;
  counts = new Map();

  constructor(base) {
    this.base = base.replace(/\/+$/, "");
  }

  count(url) {
    const limiter = limiterOf(new URL(url, this.base).pathname);
    if (limiter !== null) this.counts.set(limiter, (this.counts.get(limiter) ?? 0) + 1);
  }

  /**
   * fetch that never leaves the local server: a write is never resent to where a redirect points, and a read follows
   * a redirect only to a local address.
   */
  fetch = async (url, options = {}) => {
    const method = options.method ?? "GET";
    const follows = (options.redirect ?? "follow") === "follow" && (method === "GET" || method === "HEAD");
    let at = String(url);
    for (let hop = 0; ; hop++) {
      this.count(at);
      let response;
      try {
        response = await fetch(at, { ...options, redirect: "manual" });
      } catch (error) {
        throw new Error(`${method} ${at} got no answer: ${error.cause?.code ?? error.cause?.message ?? error.message}`);
      }
      const location = response.headers.get("location");
      if (!follows || response.status < 300 || response.status > 399 || !location) return response;
      const next = new URL(location, at);
      if (!LOCAL_HOSTS.includes(next.hostname)) throw new Error(`${method} ${at} redirects to ${next.origin}, which is not a local server`);
      if (hop === MAX_REDIRECTS) throw new Error(`${method} ${url} was still redirecting after ${MAX_REDIRECTS} hops`);
      await response.body?.cancel();
      at = next.href;
    }
  };

  async request(route, { method = "GET", headers = {}, body, redirect = "follow", jar } = {}) {
    const sent = { ...headers };
    if (jar) sent.Cookie = jar.header();
    const response = await this.fetch(this.base + route, { method, headers: sent, body, redirect });
    if (jar) jar.store(response);
    const text = await response.text();
    return { status: response.status, headers: response.headers, text, json: parseJson(text) };
  }

  api(route, options = {}) {
    return this.request(route, { ...options, headers: { Accept: "application/json", ...options.headers } });
  }

  page(route, options = {}) {
    return this.request(route, { ...options, headers: { Accept: "text/html", ...ORIGIN_HEADERS, ...options.headers } });
  }
}

export class Jar {
  #cookies = new Map();

  store(response) {
    for (const cookie of response.headers.getSetCookie()) {
      const [pair] = cookie.split(";");
      const at = pair.indexOf("=");
      if (at > 0) this.#cookies.set(pair.slice(0, at).trim(), pair.slice(at + 1).trim());
    }
  }

  header() {
    return [...this.#cookies].map(([name, value]) => `${name}=${value}`).join("; ");
  }

  entries() {
    return [...this.#cookies].map(([name, value]) => ({ name, value }));
  }
}

export function parseJson(text) {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

/** Throws when the answer is not `status`: a 429 names the rate-limit values, anything else says what was seen. */
export function expectStatus(answer, status, what) {
  if (answer.status === 429) {
    const wait = answer.headers?.get("retry-after") ?? "unknown";
    throw new Error(`${what}: the server's rate limit refused the lane (retry after ${wait}s): set SCARLET_RATE_REGISTRATION_PER_IP and SCARLET_RATE_PUBLIC_ANONYMOUS in the server's .env, see DEV.md, "Checks before a ship"`);
  }
  if (answer.status !== status) {
    throw new Error(`${what}: expected ${status}, saw ${answer.status}: ${answer.text.slice(0, 300)}`);
  }
  return answer;
}

export function expectJson(answer, what) {
  if (answer.json === null) throw new Error(`${what}: the body is not JSON: ${answer.text.slice(0, 300)}`);
  return answer.json;
}

export function expectError(answer, status, code, what) {
  expectStatus(answer, status, what);
  const seen = expectJson(answer, what).error?.code;
  if (seen !== code) throw new Error(`${what}: expected error code ${code}, saw ${answer.text.slice(0, 300)}`);
}

const fixtures = new Map();

/** A fixture character: its manifest, its part texts by id (each checked against its SHA-256 name) and its groups. */
export function loadFixture(name) {
  if (!fixtures.has(name)) {
    const dir = path.join(FIXTURES, name);
    const manifest = JSON.parse(fs.readFileSync(path.join(dir, "manifest.json"), "utf8"));
    const parts = new Map();
    for (const part of manifest.parts) {
      const bytes = fs.readFileSync(path.join(dir, part.id + ".part"));
      const hash = crypto.createHash("sha256").update(bytes).digest("hex");
      if (hash !== part.id) throw new Error(`the fixture part ${name}/${part.id} hashes to ${hash}`);
      parts.set(part.id, bytes.toString("utf8"));
    }
    const groups = [...new Set(manifest.parts.map((part) => part.group))].sort();
    const sessions = new Set(manifest.parts.map((part) => part.session)).size;
    fixtures.set(name, { name, manifest, parts, groups, sessions, ids: [...parts.keys()] });
  }
  return fixtures.get(name);
}

/** Runs `work` over `items`, at most `atMost` at a time, and returns the results in order. */
export async function pool(items, atMost, work) {
  const results = new Array(items.length);
  let next = 0;
  const workers = Array.from({ length: Math.min(atMost, items.length) }, async () => {
    while (next < items.length) {
      const index = next++;
      results[index] = await work(items[index], index);
    }
  });
  await Promise.all(workers);
  return results;
}

/** Throws one Error listing every non-null problem a pool returned. */
export function failOn(problems, total, noun) {
  const broken = problems.filter(Boolean);
  if (broken.length > 0) throw new Error(`${broken.length} of ${total} ${noun} failed:\n${broken.join("\n")}`);
}

export function escapeRegExp(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
