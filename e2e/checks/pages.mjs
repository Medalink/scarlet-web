import crypto from "node:crypto";
import { InstallClient } from "../client.mjs";
import { NAME, PROBE, SHOWN, STRICT, Skip, expectStatus, expectJson, failOn, pool, rollupsRefreshed, sleep } from "../support.mjs";

const CONCURRENCY = 6;
export const ERROR_PAGE = /Server Error|Whoops|Stack Trace|Illuminate\\|laravel-exceptions|Undefined (variable|array key)|Call to (undefined|a member)/i;
export const heading = (text) => new RegExp(`<h1[^>]*>\\s*${text}`);

const PAGES = [
  { path: "/", shows: heading("(?:[\\d,]+ )?WoW Forever characters?, recorded by their players") },
  { path: "/stats", shows: heading("All characters") },
  { path: "/loot", shows: heading("Loot") },
  { path: "/loot?view=groups", shows: heading("Loot rules and group size") },
  { path: "/map", shows: heading("World map") },
  { path: "/camps", shows: heading("Camps") },
  { path: "/search?q=Testa", shows: new RegExp(`<h1[^>]*>\\s*Search\\s*<[\\s\\S]*${NAME}`) },
  { path: "/developers", shows: heading("Public API") },
  { path: "/download", shows: heading("Scarlet records what you do in WoW Forever") },
  { path: "/leaderboards", shows: heading("\\d+ boards") },
  { path: "/leaderboards/most-quests", shows: heading("Most quests turned in") },
  { path: "/leaderboards/feat-score", shows: heading("Highest feat score") },
  { path: "/feats", shows: heading("241 feats, five tiers each") },
  { path: "/feats/slayer", shows: heading("Slayer") },
  { path: "/talents", shows: heading("Talents") },
  { path: "/stats/compare", shows: heading("Rulesets compared") },
  { path: "/stats/unidentified", shows: heading("Not identified") },
  { path: "/professions", shows: heading("Twelve professions") },
  { path: "/rulesets", shows: heading("Rulesets compared") },
  { path: "/rulesets/unidentified", shows: heading("Not identified") },
  { path: `/c/${NAME}`, shows: heading(SHOWN) },
  { path: `/c/${NAME}/heat`, shows: /^\{"data":/ },
  { path: "/creatures/113", shows: heading("Stonetusk Boar") },
  { path: "/items/769", shows: heading("Chunk of Boar Meat") },
  { path: "/zones/1436", shows: heading("Westfall") },
  { path: "/quests/783", shows: heading("A Threat Within") },
  { path: "/spells/2660", shows: heading("Rough Sharpening Stone") },
  { path: "/encounters/1065", shows: heading("Encounter 1065") },
];

function tokenIn(html) {
  const token = html.match(/name="_token" value="([^"]+)"/)?.[1];
  if (!token) throw new Error(`the page carries no CSRF token to send back: ${html.slice(0, 300)}`);
  return token;
}

function form(fields) {
  return { headers: { "Content-Type": "application/x-www-form-urlencoded", Accept: "text/html" }, body: new URLSearchParams(fields).toString() };
}

export async function everyPageShowsItself(ctx) {
  const problems = await pool(PAGES, CONCURRENCY, async ({ path, shows }) => {
    const answer = await ctx.http.page(path);
    if (answer.status === 429) expectStatus(answer, 200, path);
    if (answer.status !== 200) return `${path}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`;
    if (ERROR_PAGE.test(answer.text)) return `${path}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`;
    if (!shows.test(answer.text)) return `${path}: expected a body that matches ${shows}, saw ${answer.text.slice(0, 300)}`;
    return null;
  });
  failOn(problems, PAGES.length, "pages");
}

export async function oldAddressesRedirect(ctx) {
  const moved = [
    ["/demo", "/"],
    ["/release-notes?v=1.0.0", "/download?v=1.0.0#release-notes"],
    ["/loot/groups?source=c1", "/loot?source=c1&view=groups"],
    ["/rulesets", "/stats/compare"],
    ["/rulesets/unidentified", "/stats/unidentified"],
    ["/hardcore-deaths", /^\/stats(\/[a-z0-9-]+#deaths)?$/],
    ["/account/camps?ruleset=x", "/camps?ruleset=x&scope=mine"],
    ["/account/map", "/map?scope=mine"],
    ["/account/map/trails", "/map/trails?scope=mine"],
    ["/account/characters", "/account"],
    ["/account/professions", "/professions?scope=mine"],
    ["/account/professions/fishing", "/professions/fishing?scope=mine"],
    [`/c/${NAME}/heat`, `/map/trails?character=${NAME}`],
    [`/c/${NAME}/trails`, `/map/trails?character=${NAME}`],
  ];
  const problems = [];
  for (const [from, to] of moved) {
    const answer = await ctx.http.request(from, { redirect: "manual", headers: { Accept: "text/html" } });
    const location = answer.headers.get("location");
    const path = location === null ? null : location.replace(/^https?:\/\/[^/]+/, "") || "/";
    const ok = to instanceof RegExp ? path !== null && to.test(path) : path === to;
    if (answer.status !== 301 || !ok) problems.push(`${from}: expected 301 to ${to}, saw ${answer.status} ${location}`);
  }
  failOn(problems, moved.length, "old addresses");
}

export async function scoreboardIsShownWithoutNames(ctx) {
  const character = expectStatus(await ctx.http.page(`/c/${PROBE}`), 200, `/c/${PROBE}`);
  const problems = [];
  if (!character.text.includes('id="battlegrounds"')) problems.push("the Battlegrounds block in Combat");
  if (!character.text.includes("Warsong Gulch, won")) problems.push("the match, Warsong Gulch, won");
  if ((character.text.match(/>a player</g) ?? []).length !== 3) problems.push("three rows of a player");
  if (!/<td class="[^"]*">4<\/td><td class="[^"]*">9<\/td>/.test(character.text)) problems.push("4 killing blows and 9 honorable kills in the Alliance row");
  for (const name of ["Pella", "Dorn", "Kesta"]) if (character.text.includes(name)) problems.push(`no name, but ${name} is shown`);
  if (problems.length > 0) throw new Error(`/c/${PROBE}: expected ${problems.join(", ")}: ${character.text.slice(character.text.indexOf('id="battlegrounds"'), character.text.indexOf('id="battlegrounds"') + 900)}`);
  const old = await ctx.http.request(`/c/${PROBE}/battlegrounds`, { redirect: "manual", headers: { Accept: "text/html" } });
  if (old.status !== 301 || !old.headers.get("location")?.endsWith(`/c/${PROBE}#battlegrounds`)) throw new Error(`/c/${PROBE}/battlegrounds should be a 301 to /c/${PROBE}#battlegrounds, saw ${old.status} to ${old.headers.get("location")}`);
}

export async function recipeSpellAndBossLootAreShown(ctx) {
  await rollupsRefreshed(ctx);
  const problems = [];
  const spell = expectStatus(await ctx.http.page("/spells/2660"), 200, "/spells/2660").text;
  if (!/<td>Rough Stone<\/a><\/td>|>Rough Stone<\/a>/.test(spell)) problems.push("/spells/2660 lists the reagent Rough Stone");
  for (const shown of ["3.0 s", "Creates a Rough Sharpening Stone.", "What it makes and needs"]) {
    if (!spell.includes(shown)) problems.push(`/spells/2660 shows ${shown}`);
  }
  const made = expectStatus(await ctx.http.page("/items/2862"), 200, "/items/2862").text;
  if (!made.includes("Recipes that make it") || !made.includes("/spells/2660")) problems.push("/items/2862 lists the recipe that makes it");
  const used = expectStatus(await ctx.http.page("/items/2835"), 200, "/items/2835").text;
  if (!used.includes("Recipes that use it")) problems.push("/items/2835 lists the recipe that uses it");
  const dropped = expectStatus(await ctx.http.page("/items/769"), 200, "/items/769").text;
  if (!dropped.includes("Boss encounters that dropped it") || !dropped.includes("/encounters/1065")) problems.push("/items/769 lists the encounter that dropped it");
  const skinned = expectStatus(await ctx.http.page("/items/2318"), 200, "/items/2318").text;
  if (!skinned.includes("Where it&#039;s gathered") && !skinned.includes("Where it's gathered")) problems.push("/items/2318 shows where it's gathered");
  if (!skinned.includes("/creatures/113")) problems.push("/items/2318 names the Stonetusk Boar it is skinned from");
  const pearl = expectStatus(await ctx.http.page("/items/7971"), 200, "/items/7971").text;
  if (!pearl.includes("/items/7973")) problems.push("/items/7971 names the Big-mouth Clam it came in");
  const encounter = expectStatus(await ctx.http.page("/encounters/1065"), 200, "/encounters/1065").text;
  if (!/Chunk of Boar Meat<\/a><\/td><td class="n ?">1<\/td><td class="n ?">2<\/td>/.test(encounter)) {
    problems.push("/encounters/1065 counts the drop two uploaders heard once, from 2 characters");
  }
  if (problems.length > 0) throw new Error(`expected ${problems.join(", ")}`);
}

/** The art answers PHP serves set no cookie, so the edge may cache them (docs/HOSTING.md, "Edge cache"). */
export async function artAnswersSetNoCookie(ctx) {
  const routes = ["/scene-art/camp/models.json", "/dungeon-maps/1/1.webp", "/map-tiles/manifest.json", "/item-icons/1.png", "/spell-icons/1.png"];
  const problems = await pool(routes, CONCURRENCY, async (route) => {
    const answer = await ctx.http.page(route);
    const cookies = answer.headers.getSetCookie();
    if (cookies.length > 0) return `${route}: sets ${cookies.join(", ")}`;
    if (answer.status === 200 && !answer.headers.get("cache-control")?.includes("public")) {
      return `${route}: a 200 without a public Cache-Control (${answer.headers.get("cache-control")})`;
    }
    return null;
  });
  failOn(problems, routes.length, "art answers");
}

export function missingPageIs404(route) {
  return async (ctx) => {
    const answer = await ctx.http.page(route);
    expectStatus(answer, 404, route);
    if (ERROR_PAGE.test(answer.text.replace(/<title>[^<]*<\/title>/, ""))) {
      throw new Error(`${route}: expected a 404 page, saw the markers of an error page: ${answer.text.slice(0, 300)}`);
    }
  };
}

const PUBLIC_PAGES = ["/professions/fishing", "/camps"];

/** A visitor with no cookie reads the public pages: they keep what the edge cache needs and none of the words of a player's own page. */
export async function publicPagesStayPublic(ctx) {
  await rollupsRefreshed(ctx);
  const problems = await pool(PUBLIC_PAGES, CONCURRENCY, async (route) => {
    const answer = await ctx.http.request(route, { headers: { Accept: "text/html" } });
    if (answer.status !== 200) return `${route}: expected 200, saw ${answer.status}`;
    const wrong = [];
    if (!answer.text.includes('<link rel="canonical" href="')) wrong.push("no canonical link");
    if (answer.text.includes("noindex")) wrong.push("a noindex");
    if (!answer.headers.get("cache-control")?.includes("public")) wrong.push(`no public Cache-Control (${answer.headers.get("cache-control")})`);
    if (answer.headers.getSetCookie().length > 0) wrong.push(`a Set-Cookie (${answer.headers.getSetCookie().join(", ")})`);
    if (/Everyone(&#0?39;|')s/.test(answer.text)) wrong.push("the words Everyone's of an own page");
    return wrong.length > 0 ? `${route}: ${wrong.join(", ")}` : null;
  });
  failOn(problems, PUBLIC_PAGES.length, "public pages");
}

export async function accountIsClosedToVisitors(ctx) {
  for (const route of ["/account", "/account/settings", "/link"]) {
    const answer = await ctx.http.page(route, { redirect: "manual" });
    const location = answer.headers.get("location") ?? "";
    if (answer.status !== 302 || new URL(location, ctx.base).pathname !== "/login") {
      throw new Error(`${route}: expected a redirect to /login for a visitor, saw ${answer.status} ${location}: ${answer.text.slice(0, 300)}`);
    }
  }
  expectStatus(await ctx.http.page("/login"), 200, "GET /login");
}

/** Signs `jar` in through the development sign-in. Skipped where the server has it off. */
export async function signIn(http, jar) {
  const login = expectStatus(await http.request("/login", { jar, headers: { Accept: "text/html" } }), 200, "GET /login");
  if (!login.text.includes('action="/auth/development"')) {
    const off = "the development sign-in is off: set SCARLET_DEV_LOGIN=true in the server's .env";
    if (STRICT) throw new Error(`${off} (E2E_STRICT=1 means the server was started with it, so the login page lost its form)`);
    throw new Skip(off);
  }
  let signedIn = await http.request("/auth/development", { ...form({ _token: tokenIn(login.text) }), method: "POST", jar, redirect: "manual" });
  if (signedIn.status === 429) {
    await sleep(Math.min(Number(signedIn.headers.get("retry-after") ?? 60), 65) * 1000);
    const again = expectStatus(await http.request("/login", { jar, headers: { Accept: "text/html" } }), 200, "GET /login");
    signedIn = await http.request("/auth/development", { ...form({ _token: tokenIn(again.text) }), method: "POST", jar, redirect: "manual" });
  }
  if (signedIn.status !== 302 || !(signedIn.headers.get("location") ?? "").endsWith("/account")) {
    throw new Error(`POST /auth/development: expected a redirect to /account, saw ${signedIn.status} ${signedIn.headers.get("location")}: ${signedIn.text.slice(0, 300)}`);
  }
}

/** Links the install of `app` to the account `jar` is signed in to, the way the app does it: the code, the
 *  `redeem` line of the link-code answer signed by the install's key in the address, and both back in the confirm. */
export async function linkToAccount(http, jar, app) {
  const issued = expectJson(expectStatus(await app.linkCode(), 201, "POST /api/v1/installs/link-code"), "the link code");
  const code = issued.code;
  if (!/^[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(code ?? "")) throw new Error(`the link code should look like XXXX-XXXX, saw ${JSON.stringify(code)}`);
  if (typeof issued.redeem !== "string" || issued.redeem === "") throw new Error(`the link code answer should carry a redeem line, saw ${JSON.stringify(issued)}`);
  const redeem = app.redeem(issued.redeem);

  const shown = expectStatus(await http.request(`/link?code=${code}&redeem=${encodeURIComponent(redeem)}`, { jar, headers: { Accept: "text/html" } }), 200, "GET /link?code=");
  if (!shown.text.includes("The app is linked to your account.") && !shown.text.includes(code)) {
    throw new Error(`/link?code= should show the code or its state, saw ${shown.text.slice(0, 300)}`);
  }
  if (!shown.text.includes('name="redeem" value="' + redeem)) {
    throw new Error(`/link?code= should carry the redeem signature into its form, saw ${shown.text.slice(shown.text.indexOf("<form"), shown.text.indexOf("</form>"))}`);
  }
  const confirmed = await http.request("/link", { ...form({ _token: tokenIn(shown.text), code, redeem }), method: "POST", jar, redirect: "manual" });
  if (confirmed.status !== 302 || !(confirmed.headers.get("location") ?? "").endsWith("/account")) {
    throw new Error(`POST /link: expected a redirect to /account, saw ${confirmed.status} ${confirmed.headers.get("location")}: ${confirmed.text.slice(0, 300)}`);
  }
}

export async function appLinksToAccount(ctx) {
  const { http, jar } = ctx;
  await signIn(http, jar);

  const label = `scarlet-web e2e link ${crypto.randomBytes(6).toString("hex")}`;
  const app = new InstallClient(ctx.base, label, http.fetch);
  expectStatus(await app.register(), 201, "POST /api/v1/installs for the linking app");
  await linkToAccount(http, jar, app);

  const me = expectJson(expectStatus(await app.me(), 200, "GET /api/v1/installs/me after the link"), "installs/me after the link");
  if (me.linked !== true) throw new Error(`after the link the install should say linked: true, saw ${JSON.stringify(me)}`);
  expectStatus(await http.request("/account", { jar, headers: { Accept: "text/html" } }), 200, "GET /account");
  const settings = expectStatus(await http.request("/account/settings", { jar, headers: { Accept: "text/html" } }), 200, "GET /account/settings");
  if (!settings.text.includes(label)) throw new Error(`/account/settings should list the linked install ${label}, saw ${settings.text.slice(0, 300)}`);
  expectStatus(await http.request("/account", { jar, headers: { Accept: "text/html" } }), 200, "GET /account");
}
