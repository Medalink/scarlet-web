import crypto from "node:crypto";
import { InstallClient } from "../client.mjs";
import { NAME, SHOWN, STRICT, Skip, expectStatus, expectJson, failOn, pool } from "../support.mjs";

const CONCURRENCY = 6;
const ERROR_PAGE = /Server Error|Whoops|Stack Trace|Illuminate\\|laravel-exceptions|Undefined (variable|array key)|Call to (undefined|a member)/i;
const heading = (text) => new RegExp(`<h1[^>]*>\\s*${text}`);

const PAGES = [
  { path: "/", shows: heading("Scarlet records what you do in WoW Forever") },
  { path: "/demo", shows: heading("Maren Ashlow") },
  { path: "/stats", shows: heading("All characters") },
  { path: "/map", shows: heading("World map") },
  { path: "/search?q=Testa", shows: new RegExp(`<h1[^>]*>\\s*Search\\s*<[\\s\\S]*${NAME}`) },
  { path: "/developers", shows: heading("Public API") },
  { path: "/release-notes", shows: heading("Release notes") },
  { path: "/download", shows: heading("Download the Scarlet app") },
  { path: "/leaderboards", shows: heading("Leaderboards") },
  { path: "/leaderboards/most-quests", shows: heading("Most quests turned in") },
  { path: "/talents", shows: heading("Talents") },
  { path: "/rulesets", shows: heading("Rulesets compared") },
  { path: "/rulesets/unidentified", shows: heading("Not identified") },
  { path: `/c/${NAME}`, shows: heading(SHOWN) },
  { path: `/c/${NAME}/heat`, shows: /^\{"data":/ },
  { path: "/creatures/113", shows: heading("Stonetusk Boar") },
  { path: "/items/769", shows: heading("Chunk of Boar Meat") },
  { path: "/zones/1436", shows: heading("Westfall") },
  { path: "/quests/783", shows: heading("A Threat Within") },
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

export async function appLinksToAccount(ctx) {
  const { http, jar } = ctx;
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

  const label = `scarlet-web e2e link ${crypto.randomBytes(6).toString("hex")}`;
  const app = new InstallClient(ctx.base, label, http.fetch);
  expectStatus(await app.register(), 201, "POST /api/v1/installs for the linking app");
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

  const me = expectJson(expectStatus(await app.me(), 200, "GET /api/v1/installs/me after the link"), "installs/me after the link");
  if (me.linked !== true) throw new Error(`after the link the install should say linked: true, saw ${JSON.stringify(me)}`);
  const account = expectStatus(await http.request("/account", { jar, headers: { Accept: "text/html" } }), 200, "GET /account");
  if (!account.text.includes(label)) throw new Error(`/account should list the linked install ${label}, saw ${account.text.slice(0, 300)}`);
  expectStatus(await http.request("/account/characters", { jar, headers: { Accept: "text/html" } }), 200, "GET /account/characters");
}
