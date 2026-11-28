import { Skip, expectError, expectJson, expectStatus } from "../support.mjs";
import { callKeys, deleteKeys, expireKeys, linkBattlenet, makeKey, signInDevelopment } from "../keys-client.mjs";

export const KEY_NAME = "e2e key";
const EXPIRING = "e2e expiring key";
const SIXTY = "e2e sixty days";
const NINETY = "e2e ninety days";
const SETTINGS = "/account/settings";
const DAY_MS = 86_400_000;
const NO_KEY_WORDS = ["Authorization: Bearer", "Battle.net", "scarletweb.io/account"];

const requestOf = (ctx) => (route, options = {}) => ctx.http.request(route, { ...options, jar: ctx.keyJar });

/** The days from now to each expiry the module lists, in the order it lists them (newest key first). */
function expiryDays(html) {
  return [...html.matchAll(/ · expires <time datetime="([^"]+)" data-when="day"/g)].map(([, at]) => (Date.parse(at) - Date.now()) / DAY_MS);
}

/**
 * The development account has no Battle.net sign-in yet: its settings page shows the one line that links Battle.net in
 * place of the form, and a call to make a key, sent anyway, makes none.
 */
export async function noBattlenetMakesNoKey(ctx) {
  const request = requestOf(ctx);
  await signInDevelopment(request);
  const page = expectStatus(await request(SETTINGS, { headers: { Accept: "text/html" } }), 200, `GET ${SETTINGS}`);
  if (page.text.includes('id="key-name"')) throw new Skip("the development account already has Battle.net, as it does on a server an earlier run left");
  if (!page.text.includes('id="key-battlenet"') || !/API keys need a Battle\.net sign-in/.test(page.text)) {
    throw new Error(`${SETTINGS} should show the line that links Battle.net to an account without it`);
  }
  if (page.text.includes('id="key-name"') || page.text.includes('id="key-days"')) throw new Error(`${SETTINGS} should hold no form to make a key for an account without Battle.net`);
  if (!/<form[^>]*action="\/auth\/development"[\s\S]*?name="as" value="battlenet"/.test(page.text)) throw new Error(`${SETTINGS} should offer the development Battle.net link, since this server has no Battle.net client`);

  const made = await makeKey(request, KEY_NAME, 30);
  if (made.key !== null) throw new Error("an account without Battle.net made a key");
  if (!made.html.includes("Link Battle.net to make a key.")) throw new Error(`the refusal should say why: ${made.html.slice(0, 300)}`);
}

/**
 * Linking Battle.net opens the form. A key lasts 30 days unless 60 or 90 is chosen, the choice is kept and listed with
 * its date, 91 days and more are refused, and the key of 30 days is the one every other read of the lane sends.
 */
export async function expiryChoiceIsStoredAndShown(ctx) {
  const request = requestOf(ctx);
  await linkBattlenet(request);
  const page = expectStatus(await request(SETTINGS, { headers: { Accept: "text/html" } }), 200, `GET ${SETTINGS}`);
  if (page.text.includes('id="key-battlenet"') || !page.text.includes('id="key-name"')) throw new Error(`${SETTINGS} should show the form once Battle.net is linked`);
  const options = [...page.text.matchAll(/<option value="(\d+)">Expires in (\d+) days<\/option>/g)].map(([, value, shown]) => `${value}:${shown}`);
  if (options.join() !== "30:30,60:60,90:90") throw new Error(`the form should offer 30, 60 and 90 days, offers ${JSON.stringify(options)}`);

  const first = await makeKey(request, KEY_NAME, 30);
  if (first.key === null || !/^\d+\|[A-Za-z0-9]{20,}$/.test(first.key)) throw new Error(`the module should show the new key once, saw ${JSON.stringify(first.key)}`);
  const [thirty] = expiryDays(first.html);
  if (!(thirty > 29.5 && thirty < 30.5)) throw new Error(`a key made for 30 days should list an expiry 30 days out, saw ${thirty}`);

  const sixty = await makeKey(request, SIXTY, 60);
  const ninety = await makeKey(request, NINETY, 90);
  if (sixty.key === null || ninety.key === null) throw new Error("the module made no key for 60 or 90 days");
  const longestFirst = (days) => days.map((day) => Math.round(day)).sort((a, b) => b - a).join();
  const listed = longestFirst(expiryDays(await callKeys(request)));
  if (listed !== "90,60,30") throw new Error(`the keys should list expiries 90, 60 and 30 days out, saw ${listed}`);
  const reloaded = longestFirst(expiryDays(expectStatus(await request(SETTINGS, { headers: { Accept: "text/html" } }), 200, `GET ${SETTINGS}`).text));
  if (reloaded !== "90,60,30") throw new Error(`a reload of ${SETTINGS} should list the stored expiries 90, 60 and 30, saw ${reloaded}`);

  for (const days of [91, 120, 0]) {
    const refused = await makeKey(request, "e2e too long", days);
    if (refused.key !== null) throw new Error(`a key for ${days} days was made; at most 90 are allowed`);
    if (!refused.html.includes("A key lasts 1 to 90 days.")) throw new Error(`a key for ${days} days should be refused with the allowed range, saw ${refused.html.slice(0, 300)}`);
  }
  await deleteKeys(request, SIXTY);
  await deleteKeys(request, NINETY);
  ctx.http.key = first.key;
}

/** No key, a wrong key, a key in the query string and an expired key are each a 401, and only the first has its own code. */
export async function publicApiNeedsAKey(ctx) {
  const route = "/api/v1/public/stats?limit=3";
  const missing = await ctx.http.api(route, { key: false });
  expectError(missing, 401, "missing_key", `GET ${route} with no key`);
  const message = expectJson(missing, "the missing_key answer").error.message;
  for (const word of NO_KEY_WORDS) if (!message.includes(word)) throw new Error(`the missing_key message should mention ${JSON.stringify(word)}, saw ${JSON.stringify(message)}`);
  if (!(missing.headers.get("cache-control") ?? "").includes("no-store")) throw new Error("a refusal should be Cache-Control no-store");

  expectError(await ctx.http.api(route, { key: false, headers: { Authorization: "Bearer 1|not-a-key-of-this-site" } }), 401, "invalid_token", "GET with a wrong key");
  expectError(await ctx.http.api(`${route}&token=${ctx.http.key}&api_key=${ctx.http.key}&key=${ctx.http.key}`, { key: false }), 401, "missing_key", "GET with the key in the query string");

  const request = requestOf(ctx);
  const made = await makeKey(request, EXPIRING, 30);
  if (made.key === null) throw new Error("the module made no key to expire");
  expectStatus(await ctx.http.api(route, { headers: { Authorization: `Bearer ${made.key}` } }), 200, "GET with the key that is about to expire");
  await expireKeys(request, EXPIRING);
  expectError(await ctx.http.api(route, { headers: { Authorization: `Bearer ${made.key}` } }), 401, "invalid_token", "GET with an expired key");
  const html = await callKeys(request);
  const row = html.slice(html.indexOf(EXPIRING), html.indexOf(EXPIRING) + 1500);
  if (!/ · expired <time/.test(row)) throw new Error("the module should list the expired key as expired, with its date");
  if (!row.includes('<span class="sc-state">Expired</span>')) throw new Error("the module should mark the expired key Expired");
  if (!row.includes('wire:click="delete(')) throw new Error("the module should keep the expired key deletable");
  await deleteKeys(request, EXPIRING);
}

/** Every answer is private to the caller, carries the key's tag and limit, and a browser's preflight for the header is answered. */
export async function answersArePrivateAndPreflightIsAnswered(ctx) {
  const route = "/api/v1/public/rulesets?limit=4";
  const answer = expectStatus(await ctx.http.api(route), 200, `GET ${route}`);
  const cache = answer.headers.get("cache-control") ?? "";
  if (!/\bprivate\b/.test(cache) || !/\bmax-age=\d+/.test(cache) || /public|s-maxage/.test(cache)) throw new Error(`a public API answer should be Cache-Control private, saw ${JSON.stringify(cache)}`);
  if (!(answer.headers.get("vary") ?? "").includes("Authorization") || !answer.headers.get("etag")) throw new Error("a public API answer should vary by Authorization and carry an ETag");
  if (!answer.headers.get("x-ratelimit-limit")) throw new Error("a public API answer should carry X-RateLimit-Limit");
  const again = await ctx.http.api(route, { headers: { "If-None-Match": answer.headers.get("etag") } });
  if (again.status !== 304 || !/\bprivate\b/.test(again.headers.get("cache-control") ?? "")) throw new Error(`the same read with its ETag should be a private 304, saw ${again.status} ${again.headers.get("cache-control")}`);

  const preflight = await ctx.http.request("/api/v1/public/stats", {
    method: "OPTIONS",
    headers: { Origin: "https://reader.example", "Access-Control-Request-Method": "GET", "Access-Control-Request-Headers": "authorization" },
  });
  const allowed = (preflight.headers.get("access-control-allow-headers") ?? "").toLowerCase();
  if (preflight.status !== 204 || !allowed.includes("authorization") || preflight.headers.get("access-control-allow-origin") !== "*") {
    throw new Error(`a preflight for the Authorization header should be a 204 that allows it, saw ${preflight.status} ${JSON.stringify(Object.fromEntries(preflight.headers))}`);
  }
}

/** The site's own map reads its density from /map/density with no key, and nothing a page loads goes to /api/v1/public. */
export async function mapDensityNeedsNoKey(ctx) {
  const route = "/map/density?layer=paths&level=1";
  const answer = expectStatus(await ctx.http.api(route, { key: false }), 200, `GET ${route} with no key`);
  const data = expectJson(answer, route).data;
  if (data?.level !== 1 || typeof data?.columns !== "number" || !Array.isArray(data?.cells)) throw new Error(`${route} should answer the level's columns, rows and cells, saw ${answer.text.slice(0, 200)}`);
  if (!(answer.headers.get("cache-control") ?? "").includes("s-maxage")) throw new Error(`${route} should be cached like a page, saw ${answer.headers.get("cache-control")}`);
  expectError(await ctx.http.api("/map/density?layer=nonsense", { key: false }), 422, "invalid_request", "GET /map/density?layer=nonsense");
  if (ctx.browser === null) throw new Skip("no browser is running, so the map page is not loaded");

  const tab = await ctx.browser.open(ctx.base + "/map", { width: 1280, height: 800 });
  try {
    const sought = `performance.getEntriesByType("resource").some((entry) => entry.name.includes("/map/density") && entry.responseStatus === 200)`;
    try {
      await tab.until(sought, 15_000, "the map page did not read /map/density");
    } catch (error) {
      if (process.env.E2E_MAP_ART !== "1") throw new Skip(`this server has no map art, so the map page draws no density: ${error.message}`);
      throw error;
    }
    const stray = await tab.evaluate(`performance.getEntriesByType("resource").filter((entry) => entry.name.includes("/api/v1/public")).map((entry) => entry.name)`);
    if (stray.length > 0) throw new Error(`the map page should send nothing to /api/v1/public, it sent ${stray.join(" ")}`);
    if (tab.problems.length > 0) throw new Error(tab.problems.join(" | "));
  } finally {
    await tab.close();
  }
}

/** The lane's own keys are deleted again, so a server that is kept holds none of them. */
export async function keysAreDeleted(ctx) {
  const request = requestOf(ctx);
  await deleteKeys(request, KEY_NAME);
  const html = await callKeys(request);
  for (const name of [KEY_NAME, EXPIRING, SIXTY, NINETY]) if (html.includes(`Delete the key ${name}?`)) throw new Error(`the key ${name} is still listed`);
}
