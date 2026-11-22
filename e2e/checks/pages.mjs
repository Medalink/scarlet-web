import crypto from "node:crypto";
import { InstallClient } from "../client.mjs";
import { NAME, PROBE, SHOWN, STRICT, Skip, expectStatus, expectJson, failOn, pool } from "../support.mjs";

const CONCURRENCY = 6;
const ERROR_PAGE = /Server Error|Whoops|Stack Trace|Illuminate\\|laravel-exceptions|Undefined (variable|array key)|Call to (undefined|a member)/i;
const heading = (text) => new RegExp(`<h1[^>]*>\\s*${text}`);

const PAGES = [
  { path: "/", shows: heading("Scarlet records what you do in WoW Forever") },
  { path: "/demo", shows: heading("Maren Ashlow") },
  { path: "/stats", shows: heading("All characters") },
  { path: "/map", shows: heading("World map") },
  { path: "/camps", shows: heading("Camps") },
  { path: "/professions/fishing", shows: heading("Fishing") },
  { path: "/professions/herbalism", shows: heading("Herbalism") },
  { path: "/professions/mining", shows: heading("Mining") },
  { path: "/professions/skinning", shows: heading("Skinning") },
  { path: "/professions/alchemy", shows: heading("Alchemy") },
  { path: "/professions/blacksmithing", shows: heading("Blacksmithing") },
  { path: "/professions/enchanting", shows: heading("Enchanting") },
  { path: "/professions/engineering", shows: heading("Engineering") },
  { path: "/professions/leatherworking", shows: heading("Leatherworking") },
  { path: "/professions/tailoring", shows: heading("Tailoring") },
  { path: "/professions/cooking", shows: heading("Cooking") },
  { path: "/professions/first-aid", shows: heading("First Aid") },
  { path: "/search?q=Testa", shows: new RegExp(`<h1[^>]*>\\s*Search\\s*<[\\s\\S]*${NAME}`) },
  { path: "/developers", shows: heading("Public API") },
  { path: "/release-notes", shows: heading("Release notes") },
  { path: "/download", shows: heading("Download the Scarlet app") },
  { path: "/leaderboards", shows: heading("Leaderboards") },
  { path: "/leaderboards/most-quests", shows: heading("Most quests turned in") },
  { path: "/talents", shows: heading("Talents") },
  { path: "/rulesets", shows: heading("Rulesets compared") },
  { path: "/hardcore-deaths", shows: heading("Hardcore deaths") },
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

export async function deathIsCountedOnce(ctx) {
  const answer = expectStatus(await ctx.http.page("/hardcore-deaths"), 200, "/hardcore-deaths");
  if (!answer.text.includes("1 death recorded")) {
    throw new Error(`/hardcore-deaths should say 1 death recorded for the notice two uploaders sent, saw ${answer.text.slice(0, 600)}`);
  }
  if (!/Level 23[^<]*1 death/.test(answer.text.replaceAll("&quot;", '"'))) {
    throw new Error("/hardcore-deaths should chart that one death at level 23");
  }
}

export async function scoreboardIsShownWithoutNames(ctx) {
  const character = expectStatus(await ctx.http.page(`/c/${PROBE}`), 200, `/c/${PROBE}`);
  if (!character.text.includes(`/c/${PROBE}/battlegrounds`) || !character.text.includes("Battleground scoreboards, 1 match")) {
    throw new Error(`/c/${PROBE} should link its 1 match with a scoreboard, saw ${character.text.slice(0, 600)}`);
  }
  const answer = expectStatus(await ctx.http.page(`/c/${PROBE}/battlegrounds`), 200, `/c/${PROBE}/battlegrounds`);
  const problems = [];
  if (!/<h1[^>]*>\s*Battlegrounds of Probe Lane/.test(answer.text)) problems.push("the heading");
  if (!answer.text.includes("Warsong Gulch, won")) problems.push("the match, Warsong Gulch, won");
  if ((answer.text.match(/>a player</g) ?? []).length !== 3) problems.push("three rows of a player");
  if (!/<td class="n ?">4<\/td><td class="n ?">9<\/td>/.test(answer.text)) problems.push("4 killing blows and 9 honorable kills in the Alliance row");
  for (const name of ["Pella", "Dorn", "Kesta"]) if (answer.text.includes(name)) problems.push(`no name, but ${name} is shown`);
  if (problems.length > 0) throw new Error(`/c/${PROBE}/battlegrounds: expected ${problems.join(", ")}: ${answer.text.slice(answer.text.indexOf("<table"), answer.text.indexOf("<table") + 900)}`);
}

export async function recipeSpellAndBossLootAreShown(ctx) {
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
  const encounter = expectStatus(await ctx.http.page("/encounters/1065"), 200, "/encounters/1065").text;
  if (!/Chunk of Boar Meat<\/a><\/td><td class="n ?">1<\/td><td class="n ?">2<\/td>/.test(encounter)) {
    problems.push("/encounters/1065 counts the drop two uploaders heard once, from 2 characters");
  }
  if (problems.length > 0) throw new Error(`expected ${problems.join(", ")}`);
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

export async function accountIsClosedToVisitors(ctx) {
  for (const route of ["/account", "/link"]) {
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
  const signedIn = await http.request("/auth/development", { ...form({ _token: tokenIn(login.text) }), method: "POST", jar, redirect: "manual" });
  if (signedIn.status !== 302 || !(signedIn.headers.get("location") ?? "").endsWith("/account")) {
    throw new Error(`POST /auth/development: expected a redirect to /account, saw ${signedIn.status} ${signedIn.headers.get("location")}: ${signedIn.text.slice(0, 300)}`);
  }
}

/** Links the install of `app` to the account `jar` is signed in to, with a link code as the app does it. */
export async function linkToAccount(http, jar, app) {
  const code = expectJson(expectStatus(await app.linkCode(), 201, "POST /api/v1/installs/link-code"), "the link code").code;
  if (!/^[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(code ?? "")) throw new Error(`the link code should look like XXXX-XXXX, saw ${JSON.stringify(code)}`);

  const shown = expectStatus(await http.request(`/link?code=${code}`, { jar, headers: { Accept: "text/html" } }), 200, "GET /link?code=");
  if (!shown.text.includes("The app is linked to your account.") && !shown.text.includes(code)) {
    throw new Error(`/link?code= should show the code or its state, saw ${shown.text.slice(0, 300)}`);
  }
  const confirmed = await http.request("/link", { ...form({ _token: tokenIn(shown.text), code }), method: "POST", jar, redirect: "manual" });
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
  const account = expectStatus(await http.request("/account", { jar, headers: { Accept: "text/html" } }), 200, "GET /account");
  if (!account.text.includes(label)) throw new Error(`/account should list the linked install ${label}, saw ${account.text.slice(0, 300)}`);
  expectStatus(await http.request("/account/characters", { jar, headers: { Accept: "text/html" } }), 200, "GET /account/characters");
}
