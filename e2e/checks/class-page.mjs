import { CLASS_CHARACTERS, Jar, LEDGER, QUIET, Skip, WARLOCK, expectJson, expectStatus, sleep } from "../support.mjs";
import * as druid from "../druid-expected.mjs";
import * as hunter from "../hunter-expected.mjs";
import * as mage from "../mage-expected.mjs";
import * as paladin from "../paladin-expected.mjs";
import * as priest from "../priest-expected.mjs";
import * as rogue from "../rogue-expected.mjs";
import * as shaman from "../shaman-expected.mjs";
import * as warlock from "../warlock-expected.mjs";
import * as warrior from "../warrior-expected.mjs";
import { CHARACTER_MAP, CHARACTER_TRAILS } from "./map.mjs";
import { browserFor } from "./owner.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const SWITCH = `.own input[type="checkbox"]`;
const SWITCH_MS = 5000;
const ROGUE = "rogue";
const CLASS_SLUGS = Object.keys(CLASS_CHARACTERS);
const BAND_WAIT_MS = 60_000;
const BAND_POLL_MS = 500;
const SECTIONS_BELOW_BAND = ['id="h-records"', 'id="h-stands"', 'id="h-week"'];

function bandLeads(html) {
  const band = html.indexOf('id="h-band"');
  return band >= 0 && SECTIONS_BELOW_BAND.every((mark) => !html.includes(mark) || band < html.indexOf(mark));
}

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

/**
 * What each class's page is read against: the figures worked out from the lines of its fixture character before the page
 * was looked at (e2e/<class>-expected.mjs). `groups` are label and value pairs, `sequences` are runs of lines in order,
 * `words` and `notes` are texts as the page writes them, `callouts` are the lines of each callout of the head scene and
 * `band` is what the character's page shows above This week.
 */
export const PAGES = [
  { slug: "druid", title: "Druid", groups: { head: druid.HEAD, forms: druid.FORMS, travel: druid.TRAVEL, "what each form did": druid.DID, "control and the wild": druid.WILD, party: druid.PARTY, talents: druid.TALENTS }, band: druid.BAND, callouts: druid.CALLOUTS, notes: druid.NOTES },
  { slug: "hunter", title: "Hunter", groups: { head: hunter.HEAD, quiver: hunter.QUIVER, pets: hunter.PETS, shots: hunter.SHOT_RANK, traps: hunter.TRAP_RANK, "Feign Death": hunter.FEIGN, tracking: hunter.TRACKING, party: hunter.PARTY }, sequences: { talents: hunter.TALENTS }, words: [...hunter.AMMO_WORDS, ...hunter.PET_WORDS, ...hunter.ASPECT_WORDS, ...hunter.FEIGN_WORDS, hunter.SHOT_NOTE], band: hunter.BAND, callouts: hunter.CALLOUTS },
  { slug: "mage", title: "Mage", groups: { head: mage.HEAD, conjuring: mage.CONJURING, travel: mage.TRAVEL, casting: mage.CASTING, control: mage.CONTROL, party: mage.PARTY, talents: mage.TALENTS }, band: mage.BAND, callouts: mage.CALLOUTS, notes: mage.NOTES },
  { slug: "paladin", title: "Paladin", groups: { head: paladin.HEAD, Judgement: paladin.JUDGEMENT, blessings: paladin.BLESSING_RANK, undead: paladin.UNDEAD, bubble: paladin.BUBBLE, "Lay on Hands": paladin.LAY_ON_HANDS, heals: paladin.HEALS, control: paladin.CONTROL, party: paladin.PARTY }, sequences: { talents: paladin.TALENTS }, words: [...paladin.SEAL_WORDS, ...paladin.AURA_WORDS, ...paladin.BUBBLE_WORDS, paladin.BLESSING_NOTE, paladin.HEALS_NOTE], band: paladin.BAND, callouts: paladin.CALLOUTS },
  { slug: "priest", title: "Priest", groups: { head: priest.HEAD, healing: priest.HEALING, shadow: priest.SHADOW, support: priest.SUPPORT, talents: priest.TALENTS, party: priest.PARTY }, band: priest.BAND, callouts: priest.CALLOUTS, notes: priest.NOTES, headings: priest.HEADINGS },
  { slug: "rogue", title: "Rogue", groups: { head: rogue.HEAD, stealth: rogue.STEALTH, hands: rogue.HANDS, fighting: rogue.FIGHTING, talents: rogue.TALENTS, party: rogue.PARTY }, band: rogue.BAND, callouts: rogue.CALLOUTS, notes: rogue.NOTES, headings: rogue.HEADINGS },
  { slug: "shaman", title: "Shaman", groups: { head: shaman.HEAD, totems: shaman.TOTEMS, fighting: shaman.FIGHTING, walks: shaman.WALKS, talents: shaman.TALENTS, party: shaman.PARTY }, band: shaman.BAND, callouts: shaman.CALLOUTS, notes: shaman.NOTES, headings: shaman.HEADINGS },
  { slug: "warlock", title: "Warlock", groups: { head: warlock.HEAD, shards: warlock.SHARDS, "Life Tap": warlock.TAPS, demons: warlock.DEMONS, casting: warlock.CASTING, party: warlock.PARTY, talents: warlock.TALENTS }, band: warlock.BAND, callouts: warlock.CALLOUTS },
  { slug: "warrior", title: "Warrior", groups: { head: warrior.HEAD, Execute: warrior.EXECUTE, attacks: warrior.ATTACKS_USED, stance: warrior.STANCE, opens: warrior.OPENS, shouts: warrior.SHOUT_RANK, "damage by spell": warrior.DAMAGE_BY_SPELL, party: warrior.PARTY }, sequences: { talents: warrior.TALENTS }, words: [...warrior.STANCE_SHARES, warrior.SHOUT_UP], band: warrior.BAND, callouts: warrior.CALLOUTS },
];

async function pageOnceStoredBandShows(ctx, route) {
  const deadline = Date.now() + BAND_WAIT_MS;
  let page = expectStatus(await ctx.http.page(route), 200, route);
  while (!page.text.includes('id="h-band"') && Date.now() < deadline) {
    await sleep(BAND_POLL_MS);
    page = expectStatus(await ctx.http.page(route), 200, route);
  }
  return page;
}

/**
 * The class page of a fixture character shows every figure worked out from its lines and no comparison with other
 * classmates, and the character's page links it and draws its band above This week. The head scene draws only where the
 * class's art is on the art store; where it is not, every other figure is still checked and the check ends as a skip that
 * names the callouts it could not read.
 */
export async function pageShowsItsFigures(ctx, page) {
  const character = CLASS_CHARACTERS[page.slug];
  const route = `/c/${character}/${page.slug}`;
  const answer = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(answer.text)) throw new Error(`${route}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`);
  if (!heading(page.title).test(answer.text)) throw new Error(`${route}: expected the heading ${page.title}, saw ${answer.text.slice(0, 300)}`);
  const tokens = tokensOf(answer.text);
  const text = answer.text.replaceAll("&#039;", "'");
  const sceneDrawn = answer.text.includes(`data-scene="${page.slug}"`);
  const problems = [];
  for (const [group, pairs] of Object.entries(page.groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  for (const [group, lines] of Object.entries(page.sequences ?? {})) {
    const lost = lines.filter((one) => !showsLines(tokens, one)).map(([first, value]) => `${first} ${value ?? ""}`.trim());
    if (lost.length > 0) problems.push(`${group}: ${lost.join(", ")}`);
  }
  const lostCallouts = sceneDrawn ? page.callouts.filter((callout) => !showsLines(tokens, callout)).map(([first]) => first) : [];
  if (lostCallouts.length > 0) problems.push(`callouts: ${lostCallouts.join(", ")}`);
  const unsaid = (page.words ?? []).filter((word) => !text.includes(word));
  if (unsaid.length > 0) problems.push(`words: ${unsaid.join(", ")}`);
  const notes = (page.notes ?? []).filter((note) => !tokens.includes(note));
  if (notes.length > 0) problems.push(`notes: ${notes.join(" | ")}`);
  const headings = (page.headings ?? []).filter((one) => !tokens.includes(one));
  if (headings.length > 0) problems.push(`headings: ${headings.join(", ")}`);
  if (answer.text.includes(`Against other ${page.slug}s`) || answer.text.includes("More than")) problems.push(`a comparison with other ${page.slug}s, which needs 20 of them and the fixture has one`);
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);

  const own = `/c/${character}`;
  const band = await pageOnceStoredBandShows(ctx, own);
  const lacks = [];
  if (!band.text.includes(`<a href="${route}">${page.title}</a>`)) lacks.push(`the ${page.title} link in the line of pages under the head`);
  if (!band.text.includes(`<a href="${route}">All ${page.slug} numbers</a>`)) lacks.push(`the band's link All ${page.slug} numbers`);
  const absentBand = missing(tokensOf(band.text), page.band);
  if (absentBand.length > 0) lacks.push(`the band figures ${absentBand.join(", ")}`);
  if (!bandLeads(band.text)) lacks.push("the band first under the line of pages");
  if (lacks.length > 0) throw new Error(`${own} should have ${lacks.join(", ")}`);
  const wrong = await ctx.http.page(`${own}/${page.slug === "mage" ? "warlock" : "mage"}`);
  if (wrong.status !== 404) throw new Error(`${own}/${page.slug === "mage" ? "warlock" : "mage"} answered ${wrong.status}: a class page exists only at the character's own class`);
  if (!sceneDrawn) throw new Skip(`${route} shows every figure, but this server has no ${page.title} art, so the head draws no scene and its callouts are not checked`);
}

export async function characterPageLinksItsClassPage(ctx) {
  const route = `/c/${WARLOCK}`;
  const page = await pageOnceStoredBandShows(ctx, route);
  const problems = [];
  if (!page.text.includes(`<a href="${route}/warlock">Warlock</a>`)) problems.push("the Warlock link in the line of pages under the head");
  if (!page.text.includes(`<a href="${route}/warlock">All warlock numbers</a>`)) problems.push("the band's link All warlock numbers");
  const absent = missing(tokensOf(page.text), warlock.BAND);
  if (absent.length > 0) problems.push(`the band figures ${absent.join(", ")}`);
  if (!bandLeads(page.text)) problems.push("the band first under the line of pages");
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
  const route = `/c/${QUIET}`;
  const page = expectStatus(await ctx.http.page(route), 200, route);
  const problems = [];
  if (page.text.includes('id="h-band"')) problems.push(`${route} draws a class band`);
  const linked = CLASS_SLUGS.filter((slug) => page.text.includes(`<a href="${route}/${slug}"`));
  if (linked.length > 0) problems.push(`${route} links its class page ${linked.join(", ")}`);
  const answer = await ctx.http.page(`${route}/priest`);
  if (answer.status !== 404) problems.push(`${route}/priest answered ${answer.status}`);
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
    for (const route of [classPage, character, CHARACTER_MAP, CHARACTER_TRAILS]) {
      const visitor = await ctx.http.page(route);
      if (visitor.status !== 404) problems.push(`${route} answered a visitor ${visitor.status}`);
    }
    expectStatus(await ctx.http.request("/map", { headers: { Accept: "text/html" } }), 200, "GET /map as an anonymous visitor");
    const anonymous = await ctx.http.request(CHARACTER_MAP, { headers: { Accept: "text/html" } });
    if (anonymous.status !== 404) problems.push(`${CHARACTER_MAP} answered an anonymous visitor ${anonymous.status} after /map was cached`);
    for (const route of [classPage, CHARACTER_MAP, CHARACTER_TRAILS]) {
      const own = await owner(route);
      if (own.status !== 200) problems.push(`${route} answered its owner ${own.status}`);
    }
    if (problems.length > 0) throw new Error(`a private character should hide its class page from visitors and keep it for its owner: ${problems.join(", ")}`);
  } finally {
    await setPublic(true);
    await tab.close();
  }
  expectStatus(await ctx.http.page(classPage), 200, `${classPage} once the page is public again`);
}
