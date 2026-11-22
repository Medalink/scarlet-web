/**
 * What every lane of the end-to-end checks shares: the HTTP helper that counts requests per rate limiter, the
 * cookie jar, the fixture loader, the status assertion and a bounded pool.
 */

import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const NAME = "Testa-Fixture";
export const PROBE = "Probe-Lane";
export const LOCAL_HOSTS = ["127.0.0.1", "localhost", "[::1]"];
/** A remember cookie makes EdgeCacheable treat the reader as signed in, so a page read reaches the origin, not the 60 s page cache. */
export const ORIGIN_COOKIE ={ name: "remember_web_e2e", value: "lane" };
export const ORIGIN_HEADERS = { Cookie: `${ORIGIN_COOKIE.name}=${ORIGIN_COOKIE.value}` };

const FIXTURES = path.join(path.dirname(fileURLToPath(import.meta.url)), "fixture");

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
  if (pathname === "/" || pathname === "/demo" || pathname === "/up" || /\.[a-z0-9]+$/.test(pathname)) return null;
  return "pages";
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

  fetch = async (url, options = {}) => {
    this.count(url);
    try {
      return await fetch(url, options);
    } catch (error) {
      throw new Error(`${options.method ?? "GET"} ${url} got no answer: ${error.cause?.code ?? error.cause?.message ?? error.message}`);
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
