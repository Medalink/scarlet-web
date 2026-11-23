import { WARRIOR, expectStatus } from "../support.mjs";
import { ATTACKS_USED, BAND, CALLOUTS, DAMAGE_BY_SPELL, EXECUTE, HEAD, OPENS, PARTY, SHOUT_RANK, SHOUT_UP, STANCE, STANCE_SHARES, TALENTS } from "../warrior-expected.mjs";
import { ERROR_PAGE, heading } from "./pages.mjs";
import { missing, showsLines, tokensOf } from "./class-page.mjs";

const route = `/c/${WARRIOR}/warrior`;

export async function warriorPageShowsItsFigures(ctx) {
  const page = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(page.text)) throw new Error(`${route}: the body carries the markers of an error page: ${page.text.slice(0, 300)}`);
  if (!heading("Warrior").test(page.text)) throw new Error(`${route}: expected the heading Warrior, saw ${page.text.slice(0, 300)}`);
  const tokens = tokensOf(page.text);
  const problems = [];
  const groups = { head: HEAD, Execute: EXECUTE, attacks: ATTACKS_USED, stance: STANCE, opens: OPENS, shouts: SHOUT_RANK, "damage by spell": DAMAGE_BY_SPELL, party: PARTY };
  for (const [group, pairs] of Object.entries(groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  const lost = CALLOUTS.filter((callout) => !showsLines(tokens, callout)).map(([name]) => name);
  if (lost.length > 0) problems.push(`callouts: ${lost.join(", ")}`);
  const talents = TALENTS.filter((lines) => !showsLines(tokens, lines)).map(([first, value]) => `${first} ${value}`);
  if (talents.length > 0) problems.push(`talents: the figures of the talent modules that start ${talents.join(", ")}`);
  const unsaid = [...STANCE_SHARES, SHOUT_UP].filter((text) => !page.text.replaceAll("&#039;", "'").includes(text));
  if (unsaid.length > 0) problems.push(`words: ${unsaid.join(", ")}`);
  if (page.text.includes("Against other warriors") || page.text.includes("More than")) problems.push("a comparison with other warriors, which needs 20 of them and the fixture has one");
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);
}

export async function warriorBandLinksItsClassPage(ctx) {
  const character = `/c/${WARRIOR}`;
  const page = expectStatus(await ctx.http.page(character), 200, character);
  const problems = [];
  if (!page.text.includes(`<a href="${route}">Warrior</a>`)) problems.push("the Warrior link in the line of pages under the head");
  if (!page.text.includes(`<a href="${route}">All warrior numbers</a>`)) problems.push("the band's link All warrior numbers");
  const absent = missing(tokensOf(page.text), BAND);
  if (absent.length > 0) problems.push(`the band figures ${absent.join(", ")}`);
  if (page.text.indexOf('id="h-band"') < 0 || page.text.indexOf('id="h-band"') > page.text.indexOf('id="h-week"')) problems.push("the band above This week");
  if (problems.length > 0) throw new Error(`${character} should have ${problems.join(", ")}`);
  const wrong = await ctx.http.page(`${character}/mage`);
  if (wrong.status !== 404) throw new Error(`${character}/mage answered ${wrong.status}: a class page exists only at the character's own class`);
}
