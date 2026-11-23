import { DRUID, Jar, LEDGER, MAGE, NAME, PROBE, Skip, WARLOCK, expectJson, expectStatus, sleep } from "../support.mjs";
import * as druid from "../druid-expected.mjs";
import * as mage from "../mage-expected.mjs";
import { BAND, CALLOUTS, CASTING, DEMONS, HEAD, PARTY, SHARDS, TALENTS, TAPS } from "../warlock-expected.mjs";
import { browserFor } from "./owner.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const SWITCH = `.own input[type="checkbox"]`;
const SWITCH_MS = 5000;
const ROGUE = "rogue";
const CLASS_SLUGS = ["druid", "hunter", "mage", "paladin", "priest", "rogue", "shaman", "warlock", "warrior"];
const BAND_WAIT_MS = 60_000;
const BAND_POLL_MS = 500;

export function tokensOf(html) {
  return html
    .replace(/<(script|style)[\s\S]*?<\/\1>/g, " ")
    .replace(/<[^>]+>/g, "|")
    .replaceAll("&#039;", "'")
    .replaceAll("&amp;", "&")
    .replaceAll("&quot;", '"')
    .split("|")
    .map((token) => token.replace(/\s+/g, " ").trim())
    .filter((token) => token !== "");
}

export function shows(tokens, [label, value]) {
  return tokens.some((token, at) => token === label && [tokens[at + 1], tokens[at + 2]].some((next) => next === value || next?.startsWith(`${value} `)));
}

export function showsLines(tokens, [first, ...rest]) {
  return tokens.some((token, at) => token === first && rest.every((line, offset) => tokens[at + 1 + offset] === line));
}

export function missing(tokens, pairs) {
  return pairs.filter((pair) => !shows(tokens, pair)).map(([label, value]) => `${label} ${value}`);
}

async function pageOnceStoredBandShows(ctx, route) {
  const deadline = Date.now() + BAND_WAIT_MS;
  let page = expectStatus(await ctx.http.page(route), 200, route);
  while (!page.text.includes('id="h-band"') && Date.now() < deadline) {
    await sleep(BAND_POLL_MS);
    page = expectStatus(await ctx.http.page(route), 200, route);
  }
  return page;
}

export async function warlockPageShowsItsFigures(ctx) {
  const route = `/c/${WARLOCK}/warlock`;
  const page = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(page.text)) throw new Error(`${route}: the body carries the markers of an error page: ${page.text.slice(0, 300)}`);
  if (!heading("Warlock").test(page.text)) throw new Error(`${route}: expected the heading Warlock, saw ${page.text.slice(0, 300)}`);
  const tokens = tokensOf(page.text);
  const problems = [];
  const groups = { head: HEAD, "shards": SHARDS, "Life Tap": TAPS, demons: DEMONS, casting: CASTING, party: PARTY, talents: TALENTS };
  for (const [group, pairs] of Object.entries(groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  const sceneDrawn = page.text.includes('data-scene="warlock"');
  const lost = sceneDrawn ? CALLOUTS.filter((callout) => !showsLines(tokens, callout)).map(([name]) => name) : [];
  if (lost.length > 0) problems.push(`callouts: ${lost.join(", ")}`);
  if (page.text.includes("Against other warlocks") || page.text.includes("More than")) problems.push("a comparison with other warlocks, which needs 20 of them and the fixture has one");
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);
  if (!sceneDrawn) throw new Skip(`${route} shows every figure, but this server has no Warlock art, so the head draws no scene and its demon callouts are not checked`);
}

export async function characterPageLinksItsClassPage(ctx) {
  const route = `/c/${WARLOCK}`;
  const page = await pageOnceStoredBandShows(ctx, route);
  const problems = [];
  if (!page.text.includes(`<a href="${route}/warlock">Warlock</a>`)) problems.push("the Warlock link in the line of pages under the head");
  if (!page.text.includes(`<a href="${route}/warlock">All warlock numbers</a>`)) problems.push("the band's link All warlock numbers");
  const absent = missing(tokensOf(page.text), BAND);
  if (absent.length > 0) problems.push(`the band figures ${absent.join(", ")}`);
  if (page.text.indexOf('id="h-band"') < 0 || page.text.indexOf('id="h-band"') > page.text.indexOf('id="h-week"')) problems.push("the band above This week");
  if (problems.length > 0) throw new Error(`${route} should have ${problems.join(", ")}`);

  const ledger = `/c/${LEDGER}`;
  const answer = await ctx.http.page(`${ledger}/${ROGUE}`);
  const other = answer.status === 200 ? await pageOnceStoredBandShows(ctx, ledger) : expectStatus(await ctx.http.page(ledger), 200, ledger);
  const linked = other.text.includes(`<a href="${ledger}/${ROGUE}">`);
  if (linked !== (answer.status === 200) || (answer.status !== 200 && answer.status !== 404)) {
    throw new Error(`${ledger} ${linked ? "links" : "does not link"} its class page, which answers ${answer.status}: a link must lead to a page and a page with no link must be a 404`);
  }
  if (!linked && other.text.includes('id="h-band"')) throw new Error(`${ledger} has a class band though its class page is a 404`);
}

export async function characterWithNoClassRowsHasNoBand(ctx) {
  const problems = [];
  for (const name of [NAME, PROBE]) {
    const route = `/c/${name}`;
    const page = expectStatus(await ctx.http.page(route), 200, route);
    if (page.text.includes('id="h-band"')) problems.push(`${route} draws a class band`);
    const linked = CLASS_SLUGS.filter((slug) => page.text.includes(`<a href="${route}/${slug}"`));
    if (linked.length > 0) problems.push(`${route} links its class page ${linked.join(", ")}`);
  }
  if (problems.length > 0) throw new Error(`a character whose sessions counted no cast has no class page, so no band and no link to one: ${problems.join(", ")}`);
}

export async function otherAddressesAre404(ctx) {
  const problems = [];
  for (const route of [`/c/${WARLOCK}/mage`, `/c/${WARLOCK}/not-a-class`, `/c/Nobody-Here/warlock`]) {
    const answer = await ctx.http.page(route);
    if (answer.status !== 404) problems.push(`${route} answered ${answer.status}`);
  }
  if (problems.length > 0) throw new Error(`a class page exists only for a known character at its own class: ${problems.join(", ")}`);
}

export async function hiddenCharacterHidesItsClassPage(ctx) {
  const browser = await browserFor(ctx);
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const character = `/c/${WARLOCK}`;
  const classPage = `${character}/warlock`;
  const tab = await browser.open(ctx.base + character, { width: 1280, height: 800, cookies: jar.entries() });
  const setPublic = async (on) => {
    await tab.until(`document.querySelector('${SWITCH}') !== null`, SWITCH_MS, `the owner's ${character} has no public switch (${tab.problems.join(" | ") || "no page errors"})`);
    await tab.evaluate(`(() => { const box = document.querySelector('${SWITCH}'); if (box.checked !== ${on}) box.click(); })()`);
    await tab.until(`document.querySelector(".own .state")?.textContent.includes(${JSON.stringify(on ? "Public page" : "Private page")})`, SWITCH_MS, `the owner bar never said ${on ? "Public" : "Private"} page`);
  };
  const owner = (route) => ctx.http.request(route, { jar, headers: { Accept: "text/html" } });
  try {
    await setPublic(false);
    const problems = [];
    for (const route of [classPage, character]) {
      const visitor = await ctx.http.page(route);
      if (visitor.status !== 404) problems.push(`${route} answered a visitor ${visitor.status}`);
    }
    const own = await owner(classPage);
    if (own.status !== 200) problems.push(`${classPage} answered its owner ${own.status}`);
    if (problems.length > 0) throw new Error(`a private character should hide its class page from visitors and keep it for its owner: ${problems.join(", ")}`);
  } finally {
    await setPublic(true);
    await tab.close();
  }
  expectStatus(await ctx.http.page(classPage), 200, `${classPage} once the page is public again`);
}

async function classPageShowsItsFigures(ctx, { name, slug, title, plural, groups, band, callouts, notes }) {
  const route = `/c/${name}/${slug}`;
  const page = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(page.text)) throw new Error(`${route}: the body carries the markers of an error page: ${page.text.slice(0, 300)}`);
  if (!heading(title).test(page.text)) throw new Error(`${route}: expected the heading ${title}, saw ${page.text.slice(0, 300)}`);
  const tokens = tokensOf(page.text);
  const problems = [];
  for (const [group, pairs] of Object.entries(groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  const lost = callouts.filter((callout) => !showsLines(tokens, callout)).map(([first]) => first);
  if (lost.length > 0) problems.push(`callouts: ${lost.join(", ")}`);
  const unsaid = notes.filter((note) => !tokens.includes(note));
  if (unsaid.length > 0) problems.push(`notes: ${unsaid.join(" | ")}`);
  if (page.text.includes(`Against other ${plural}`) || page.text.includes("More than")) problems.push(`a comparison with other ${plural}, which needs 20 of them and the fixture has one`);
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);
  const character = expectStatus(await ctx.http.page(`/c/${name}`), 200, `/c/${name}`);
  const absentBand = missing(tokensOf(character.text), band);
  const wanted = [`<a href="/c/${name}/${slug}">${title}</a>`, `<a href="/c/${name}/${slug}">All ${slug} numbers</a>`].filter((link) => !character.text.includes(link));
  const order = character.text.indexOf('id="h-band"') < 0 || character.text.indexOf('id="h-band"') > character.text.indexOf('id="h-week"');
  if (absentBand.length > 0 || wanted.length > 0 || order) throw new Error(`/c/${name} should link its class page and show the band above This week; missing figures: ${absentBand.join(", ")}; missing links: ${wanted.join(", ")}; band above This week: ${!order}`);
}

export async function magePageShowsItsFigures(ctx) {
  await classPageShowsItsFigures(ctx, {
    name: MAGE,
    slug: "mage",
    title: "Mage",
    plural: "mages",
    groups: { head: mage.HEAD, conjuring: mage.CONJURING, travel: mage.TRAVEL, casting: mage.CASTING, control: mage.CONTROL, party: mage.PARTY, talents: mage.TALENTS },
    band: mage.BAND,
    callouts: mage.CALLOUTS,
    notes: mage.NOTES,
  });
}

export async function druidPageShowsItsFigures(ctx) {
  await classPageShowsItsFigures(ctx, {
    name: DRUID,
    slug: "druid",
    title: "Druid",
    plural: "druids",
    groups: { head: druid.HEAD, forms: druid.FORMS, travel: druid.TRAVEL, "what each form did": druid.DID, "control and the wild": druid.WILD, party: druid.PARTY, talents: druid.TALENTS },
    band: druid.BAND,
    callouts: druid.CALLOUTS,
    notes: druid.NOTES,
  });
}
