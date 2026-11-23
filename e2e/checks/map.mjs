import { Jar, WARLOCK, expectJson, expectStatus } from "../support.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const PRIVATE_CACHE = "max-age=0, must-revalidate, no-cache, no-store, private";
const CELL = 5;
const ROUTE = 5;

const specOf = (text) => JSON.parse((text.match(/data-world-map="([^"]*)"/)?.[1] ?? "null").replaceAll("&quot;", '"').replaceAll("&amp;", "&"));
const privateAnswer = (answer) => ["no-store", "private"].every((word) => (answer.headers.get("cache-control") ?? "").includes(word));
const offers = (text, route) => text.includes(`href="${route}"`) || text.includes(`value="${route}"`);

const WARLOCK_SHOWN = WARLOCK.replace("-", " ");

export const CHARACTER_MAP = `/map?character=${WARLOCK}`;
export const CHARACTER_TRAILS = `/c/${WARLOCK}/trails`;

function trailsOf(answer, what) {
  const data = expectJson(expectStatus(answer, 200, what), what).data;
  if (!Array.isArray(data?.cells) || !Array.isArray(data?.flights) || data.cells.length % CELL !== 0 || data.flights.length % ROUTE !== 0) {
    throw new Error(`${what}: expected data.cells in fives and data.flights in fives, saw ${answer.text.slice(0, 300)}`);
  }
  const sum = (k) => data.cells.reduce((total, value, n) => (n % CELL === k ? total + value : total), 0);
  return { foot: sum(2), mounted: sum(3), unknown: sum(4), routes: data.flights.length / ROUTE, flights: data.flights };
}

function expectWarlockTrails(trails, what) {
  if (trails.foot === 0 || trails.mounted === 0 || trails.unknown !== 0 || trails.routes !== 1 || trails.flights[4] !== 1) {
    throw new Error(`${what}: ${WARLOCK} walked, rode and flew once from Goldshire to Sentinel Hill, saw ${JSON.stringify(trails)}`);
  }
  const [u0, v0, u1, v1] = trails.flights;
  if (!(u1 < u0 && v1 > v0)) throw new Error(`${what}: the flight should run south-west from Elwynn to Westfall, saw ${JSON.stringify(trails.flights)}`);
}

export async function characterMapShowsItsTrails(ctx) {
  const page = expectStatus(await ctx.http.page(CHARACTER_MAP), 200, `GET ${CHARACTER_MAP}`);
  if (ERROR_PAGE.test(page.text) || !heading(WARLOCK_SHOWN).test(page.text)) throw new Error(`${CHARACTER_MAP} should be headed ${WARLOCK_SHOWN}, saw ${page.text.slice(0, 300)}`);
  for (const phrase of ["Flight paths", "On foot", "Mounted", '<meta name="robots" content="noindex">']) {
    if (!page.text.includes(phrase)) throw new Error(`${CHARACTER_MAP} should carry ${JSON.stringify(phrase)}`);
  }
  if (specOf(page.text)?.trails !== CHARACTER_TRAILS) throw new Error(`${CHARACTER_MAP} should read its trails from ${CHARACTER_TRAILS}`);
  expectWarlockTrails(trailsOf(await ctx.http.api(CHARACTER_TRAILS), `GET ${CHARACTER_TRAILS}`), CHARACTER_TRAILS);

  const character = expectStatus(await ctx.http.page(`/c/${WARLOCK}`), 200, `GET /c/${WARLOCK}`);
  if (character.text.includes('class="legend ch-legend"') && !character.text.includes(`href="${CHARACTER_MAP}"`)) {
    throw new Error(`/c/${WARLOCK} draws its map but its legend does not link ${CHARACTER_MAP}`);
  }
}

export async function ownMapIsClosedToVisitors(ctx) {
  for (const route of ["/account/map", "/account/map/trails"]) {
    const answer = await ctx.http.request(route, { redirect: "manual", headers: { Accept: "text/html" } });
    if (answer.status === 200) throw new Error(`${route} answered a visitor 200`);
  }
}

export async function ownMapShowsTheAccount(ctx) {
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const page = expectStatus(await ctx.http.page("/account/map", { jar }), 200, "GET /account/map signed in");
  if (ERROR_PAGE.test(page.text) || !heading("Your characters").test(page.text)) throw new Error(`/account/map should be headed Your characters, saw ${page.text.slice(0, 300)}`);
  if (page.headers.get("cache-control") !== PRIVATE_CACHE || !page.text.includes('<meta name="robots" content="noindex">')) {
    throw new Error(`/account/map should be private and noindex, saw Cache-Control ${JSON.stringify(page.headers.get("cache-control"))}`);
  }
  if (!offers(page.text, CHARACTER_MAP) || !offers(page.text, "/map")) {
    throw new Error(`/account/map should switch to everyone's map and to ${WARLOCK}'s`);
  }
  const trails = await ctx.http.api("/account/map/trails", { jar });
  if (!privateAnswer(trails)) throw new Error(`/account/map/trails should be private, saw ${JSON.stringify(trails.headers.get("cache-control"))}`);
  const own = trailsOf(trails, "GET /account/map/trails signed in");
  if (own.mounted === 0 || own.routes < 1) throw new Error(`/account/map/trails should hold ${WARLOCK}'s ride and flight, saw ${JSON.stringify(own)}`);

  const world = expectStatus(await ctx.http.page("/map", { jar }), 200, "GET /map signed in");
  if (!offers(world.text, "/account/map")) throw new Error("/map signed in should offer the account's own map");
}
