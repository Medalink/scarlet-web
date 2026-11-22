import { Jar, LEDGER, WARLOCK, expectJson, expectStatus } from "../support.mjs";
import { BAND, CALLOUTS, CASTING, DEMONS, HEAD, PARTY, SHARDS, TALENTS, TAPS } from "../warlock-expected.mjs";
import { browserFor } from "./owner.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const SWITCH = `.own input[type="checkbox"]`;
const SWITCH_MS = 5000;
const ROGUE = "rogue";

function tokensOf(html) {
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

function shows(tokens, [label, value]) {
  return tokens.some((token, at) => token === label && [tokens[at + 1], tokens[at + 2]].some((next) => next === value || next?.startsWith(`${value} `)));
}

function showsLines(tokens, [first, ...rest]) {
  return tokens.some((token, at) => token === first && rest.every((line, offset) => tokens[at + 1 + offset] === line));
}

function missing(tokens, pairs) {
  return pairs.filter((pair) => !shows(tokens, pair)).map(([label, value]) => `${label} ${value}`);
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
  const lost = CALLOUTS.filter((callout) => !showsLines(tokens, callout)).map(([name]) => name);
  if (lost.length > 0) problems.push(`callouts: ${lost.join(", ")}`);
  if (page.text.includes("Against other warlocks") || page.text.includes("More than")) problems.push("a comparison with other warlocks, which needs 20 of them and the fixture has one");
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);
}

export async function characterPageLinksItsClassPage(ctx) {
  const route = `/c/${WARLOCK}`;
  const page = expectStatus(await ctx.http.page(route), 200, route);
  const problems = [];
  if (!page.text.includes(`<a href="${route}/warlock">Warlock</a>`)) problems.push("the Warlock link in the line of pages under the head");
  if (!page.text.includes(`<a href="${route}/warlock">All warlock numbers</a>`)) problems.push("the band's link All warlock numbers");
  const absent = missing(tokensOf(page.text), BAND);
  if (absent.length > 0) problems.push(`the band figures ${absent.join(", ")}`);
  if (page.text.indexOf('id="h-band"') < 0 || page.text.indexOf('id="h-band"') > page.text.indexOf('id="h-week"')) problems.push("the band above This week");
  if (problems.length > 0) throw new Error(`${route} should have ${problems.join(", ")}`);

  const ledger = `/c/${LEDGER}`;
  const other = expectStatus(await ctx.http.page(ledger), 200, ledger);
  const linked = other.text.includes(`<a href="${ledger}/${ROGUE}">`);
  const answer = await ctx.http.page(`${ledger}/${ROGUE}`);
  if (linked !== (answer.status === 200) || (answer.status !== 200 && answer.status !== 404)) {
    throw new Error(`${ledger} ${linked ? "links" : "does not link"} its class page, which answers ${answer.status}: a link must lead to a page and a page with no link must be a 404`);
  }
  if (!linked && other.text.includes('id="h-band"')) throw new Error(`${ledger} has a class band though its class page is a 404`);
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
