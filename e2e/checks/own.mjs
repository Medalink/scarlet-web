import { ECHO, Jar, expectJson, expectStatus, rollupsRefreshed } from "../support.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";
import { PAGES, STRANGER_SHOWN, holdsWithNoDigitBeside, textOf } from "./professions.mjs";

const OWN_FISHING = "/account/professions/fishing";
const FISHING = PAGES.find((page) => page.slug === "fishing");
const COOKING = PAGES.find((page) => page.slug === "cooking");
const TAILORING = PAGES.find((page) => page.slug === "tailoring");
const PRIVATE_CACHE = "max-age=0, must-revalidate, no-cache, no-store, private";

async function signedInJar(ctx, { linked }) {
  const jar = new Jar();
  await signIn(ctx.http, jar);
  if (linked) await linkToAccount(ctx.http, jar, ctx.client);
  return jar;
}

async function ownPage(ctx, jar, route) {
  const answer = await ctx.http.page(route, { jar });
  if (answer.status === 429) {
    throw new Error(`GET ${route} signed in: the account routes allow 60 requests a minute and refused this one (retry after ${answer.headers.get("retry-after") ?? "unknown"}s): wait a minute between runs, see DEV.md`);
  }
  expectStatus(answer, 200, `GET ${route} signed in`);
  if (ERROR_PAGE.test(answer.text)) throw new Error(`${route}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`);
  return answer;
}

function missingFrom(text, phrases) {
  return phrases.filter((phrase) => !holdsWithNoDigitBeside(text, phrase));
}

/**
 * The account the lane signs in as owns the three uploaded characters. Its own Fishing and Cooking pages show the
 * numbers the public pages of the fixture show, the head says whose they are, and the page is private: no index, no
 * canonical, no shared cache, links that stay under /account.
 */
export async function ownProfessionPagesShowTheAccount(ctx) {
  await rollupsRefreshed(ctx);
  const jar = await signedInJar(ctx, { linked: false });
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const fishing = await ownPage(ctx, jar, OWN_FISHING);
  const text = textOf(fishing.text);
  const wanted = [...FISHING.shows, "1 of your 3 characters", "Everyone's Fishing"];
  const missing = missingFrom(text, wanted);
  if (!heading(FISHING.title).test(fishing.text) || missing.length > 0) {
    throw new Error(`${OWN_FISHING}: expected ${missing.map((phrase) => JSON.stringify(phrase)).join(", ")}, saw ${text.slice(text.indexOf("Fishing"), text.indexOf("Fishing") + 900)}`);
  }
  if (!fishing.text.includes('<meta name="robots" content="noindex">') || fishing.text.includes('rel="canonical"')) {
    throw new Error(`${OWN_FISHING} should say noindex and carry no canonical link`);
  }
  if (text.includes("characters who fish")) throw new Error(`${OWN_FISHING} should leave out the figure of characters who fish: its head says it`);
  if (fishing.headers.get("cache-control") !== PRIVATE_CACHE) {
    throw new Error(`${OWN_FISHING} should be sent with Cache-Control ${PRIVATE_CACHE}, saw ${JSON.stringify(fishing.headers.get("cache-control"))}`);
  }
  if (fishing.text.includes('<meta name="description"')) throw new Error(`${OWN_FISHING} should not carry the public description`);
  if (!fishing.text.includes('href="/account/professions/mining"') || fishing.text.includes('href="/professions/mining"')) {
    throw new Error(`${OWN_FISHING}: the trades should link /account/professions/..., never the public pages`);
  }
  if (!fishing.text.includes('href="/professions/fishing"')) throw new Error(`${OWN_FISHING} should link everyone's Fishing page`);

  const cooking = await ownPage(ctx, jar, "/account/professions/cooking");
  const cookingMissing = missingFrom(textOf(cooking.text), [...COOKING.shows, "1 of your 3 characters"]);
  if (cookingMissing.length > 0) throw new Error(`/account/professions/cooking: expected ${cookingMissing.map((phrase) => JSON.stringify(phrase)).join(", ")}`);

  const hub = expectStatus(await ctx.http.page("/account/professions", { jar, redirect: "manual" }), 302, "GET /account/professions");
  if (new URL(hub.headers.get("location") ?? "", ctx.base).pathname !== OWN_FISHING) {
    throw new Error(`/account/professions should go to the profession with the most attempts, ${OWN_FISHING}, saw ${hub.headers.get("location")}`);
  }
  const hubQuery = "ruleset=some-ruleset&server=pve";
  const kept = expectStatus(await ctx.http.page(`/account/professions?${hubQuery}`, { jar, redirect: "manual" }), 302, "GET /account/professions with a ruleset and a server");
  if (new URL(kept.headers.get("location") ?? "", ctx.base).search !== `?${hubQuery}`) {
    throw new Error(`/account/professions should carry ?${hubQuery} to the profession page, saw ${kept.headers.get("location")}`);
  }
  for (const route of ["/account", "/account/characters"]) {
    const linked = await ownPage(ctx, jar, route);
    if (!linked.text.includes('href="/account/professions"')) throw new Error(`${route} should link Your professions`);
  }
  const unknown = await ctx.http.page("/account/professions/lockpicking", { jar });
  if (unknown.status !== 404) throw new Error(`/account/professions/lockpicking should be 404, saw ${unknown.status}`);
}

/**
 * The stranger is a character of another install that is never linked to the account, and it crafts Tailoring, which
 * the account's own characters never do. Everyone's Tailoring page shows its crafts and the account's own Tailoring page
 * is the plain state without its name, so an own page that read everyone's rows would fail here.
 */
export async function ownPagesLeaveOutOtherInstalls(ctx) {
  await rollupsRefreshed(ctx);
  const jar = await signedInJar(ctx, { linked: false });
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const everyones = textOf(expectStatus(await ctx.http.page("/professions/tailoring"), 200, "GET /professions/tailoring").text);
  const absent = missingFrom(everyones, TAILORING.shows);
  if (absent.length > 0) {
    throw new Error(`/professions/tailoring should show the stranger's rows: expected ${absent.map((phrase) => JSON.stringify(phrase)).join(", ")}, saw ${everyones.slice(everyones.indexOf("Tailoring"), everyones.indexOf("Tailoring") + 600)}`);
  }

  const own = textOf((await ownPage(ctx, jar, "/account/professions/tailoring")).text);
  const wanted = ["0 of your 3 characters", "Scarlet records this once a character of yours has recorded crafts."];
  const missing = missingFrom(own, wanted);
  const leaked = [STRANGER_SHOWN, "Bolt of Linen Cloth", "2 crafts", "Too few characters"].filter((phrase) => own.includes(phrase));
  if (missing.length > 0 || leaked.length > 0) {
    throw new Error(`/account/professions/tailoring should be the plain state (${wanted.join(" and ")}) with nothing of the stranger, missing ${JSON.stringify(missing)}, holding ${JSON.stringify(leaked)}, saw ${own.slice(own.indexOf("Tailoring"), own.indexOf("Tailoring") + 600)}`);
  }
}

/** Once the upload is removed the signed-in account owns no character: its pages are the plain state, never the public numbers. */
export async function accountWithNoCharacterSeesThePlainState(ctx) {
  const jar = await signedInJar(ctx, { linked: false });
  const page = await ownPage(ctx, jar, OWN_FISHING);
  const text = textOf(page.text);
  const wanted = ["No play recorded for your characters yet", "Scarlet records this once a character of yours has recorded casts."];
  const missing = wanted.filter((phrase) => !text.includes(phrase));
  if (missing.length > 0 || text.includes("Too few characters") || text.includes(ECHO.replace("-", " "))) {
    throw new Error(`${OWN_FISHING} of an account with no character should say ${wanted.map((phrase) => JSON.stringify(phrase)).join(" and ")} and nothing else, saw ${text.slice(text.indexOf("Fishing"), text.indexOf("Fishing") + 600)}`);
  }
  const hub = expectStatus(await ctx.http.page("/account/professions", { jar, redirect: "manual" }), 302, "GET /account/professions");
  if (new URL(hub.headers.get("location") ?? "", ctx.base).pathname !== OWN_FISHING) {
    throw new Error(`/account/professions of an account with no character should go to ${OWN_FISHING}, saw ${hub.headers.get("location")}`);
  }
}

const OWN_CAMPS = "/account/camps";
const CAMP_SHOWS = [
  "Visits 1", "Characters 1", "Median visit 1m 30s", "Earn Camp Benefits 100%", "Camp Benefits you leave with",
  "Basic Campfire 100% of visits", "Elwynn Forest 1 visit", "1 of your 3 characters", "Your characters by time by the fire", ECHO.replace("-", " "), "Everyone's camps", "When you camp",
];
const CAMP_STRANGER = ["Visits 2", "Characters 2"];

/** Echo-Witness sits by a campfire for 90 seconds: the account's own camps page shows that visit, among the thirteen own pages. */
export async function ownCampsPageShowsTheAccount(ctx) {
  await rollupsRefreshed(ctx);
  const jar = await signedInJar(ctx, { linked: false });
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const camps = await ownPage(ctx, jar, OWN_CAMPS);
  const missing = missingFrom(textOf(camps.text), CAMP_SHOWS);
  if (!heading("Camps").test(camps.text) || missing.length > 0) {
    throw new Error(`${OWN_CAMPS}: expected ${missing.map((phrase) => JSON.stringify(phrase)).join(", ")}, saw ${textOf(camps.text).slice(0, 900)}`);
  }
  if (!camps.text.includes('<meta name="robots" content="noindex">') || camps.text.includes('rel="canonical"')) {
    throw new Error(`${OWN_CAMPS} should say noindex and carry no canonical link`);
  }
  if (camps.headers.get("cache-control") !== PRIVATE_CACHE) {
    throw new Error(`${OWN_CAMPS} should be sent with Cache-Control ${PRIVATE_CACHE}, saw ${JSON.stringify(camps.headers.get("cache-control"))}`);
  }
  if (camps.text.includes('<meta name="description"') || !/<title>Your camps/.test(camps.text)) {
    throw new Error(`${OWN_CAMPS} should be titled "Your camps" and carry no public description`);
  }
  const everyones = textOf(expectStatus(await ctx.http.page("/camps"), 200, "GET /camps").text);
  const hidden = missingFrom(everyones, CAMP_STRANGER);
  if (hidden.length > 0) {
    throw new Error(`/camps should count the stranger's campfire visit too: expected ${hidden.map((phrase) => JSON.stringify(phrase)).join(", ")}, saw ${everyones.slice(0, 900)}`);
  }
  if (!everyones.includes(STRANGER_SHOWN)) throw new Error(`/camps should list the stranger on its board, saw ${everyones.slice(-900)}`);
  const mixed = [...CAMP_STRANGER, STRANGER_SHOWN].filter((phrase) => textOf(camps.text).includes(phrase));
  if (mixed.length > 0) throw new Error(`${OWN_CAMPS} holds the stranger: ${JSON.stringify(mixed)}`);
  const nav = camps.text.match(/<nav class="trades"[\s\S]*?<\/nav>/)?.[0] ?? "";
  const links = [...nav.matchAll(/href="([^"]+)"/g)].map((match) => match[1]);
  if (links.length !== 13 || links.some((link) => !link.startsWith("/account/")) || !nav.includes('href="/account/camps" aria-current="page"')) {
    throw new Error(`${OWN_CAMPS}: the nav should hold the twelve professions and Camps, all under /account, Camps current, saw ${JSON.stringify(links)}`);
  }
  if (!camps.text.includes('href="/camps"')) throw new Error(`${OWN_CAMPS} should link everyone's camps page`);

  const fishing = await ownPage(ctx, jar, OWN_FISHING);
  if (!fishing.text.includes('href="/account/camps"')) throw new Error(`${OWN_FISHING} should link Camps in the nav`);
  for (const route of ["/account", "/account/characters"]) {
    const linked = await ownPage(ctx, jar, route);
    if (!linked.text.includes('href="/account/camps"')) throw new Error(`${route} should link Your camps`);
  }
}

/** Once the upload is removed the account's camps page is the plain state. */
export async function accountWithNoCharacterSeesTheCampsPlainState(ctx) {
  const jar = await signedInJar(ctx, { linked: false });
  const text = textOf((await ownPage(ctx, jar, OWN_CAMPS)).text);
  const wanted = ["No play recorded for your characters yet", "Scarlet records this once a character of yours has camped."];
  const missing = wanted.filter((phrase) => !text.includes(phrase));
  if (missing.length > 0 || text.includes("Too few characters") || text.includes(ECHO.replace("-", " "))) {
    throw new Error(`${OWN_CAMPS} of an account with no character should say ${wanted.map((phrase) => JSON.stringify(phrase)).join(" and ")} and nothing else, saw ${text.slice(0, 600)}`);
  }
}
