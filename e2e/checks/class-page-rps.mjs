import { ROGUE, expectStatus } from "../support.mjs";
import * as rogue from "../rogue-expected.mjs";
import { missing, showsLines, tokensOf } from "./class-page.mjs";
import { ERROR_PAGE, heading } from "./pages.mjs";

const PAGES = [
  { character: ROGUE, slug: "rogue", title: "Rogue", plural: "rogues", groups: { head: rogue.HEAD, stealth: rogue.STEALTH, hands: rogue.HANDS, fighting: rogue.FIGHTING, talents: rogue.TALENTS, party: rogue.PARTY }, band: rogue.BAND, callouts: rogue.CALLOUTS, notes: rogue.NOTES, headings: rogue.HEADINGS },
];

async function pageShows(ctx, page) {
  const route = `/c/${page.character}/${page.slug}`;
  const answer = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(answer.text)) throw new Error(`${route}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`);
  if (!heading(page.title).test(answer.text)) throw new Error(`${route}: expected the heading ${page.title}, saw ${answer.text.slice(0, 300)}`);
  const tokens = tokensOf(answer.text);
  const problems = [];
  for (const [group, pairs] of Object.entries(page.groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  const lost = page.callouts.filter((callout) => !showsLines(tokens, callout)).map(([name]) => name);
  if (lost.length > 0) problems.push(`callouts: ${lost.join(", ")}`);
  const notes = page.notes.filter((note) => !tokens.includes(note));
  if (notes.length > 0) problems.push(`notes: ${notes.join(" | ")}`);
  const headings = page.headings.filter((text) => !tokens.includes(text));
  if (headings.length > 0) problems.push(`headings: ${headings.join(", ")}`);
  if (answer.text.includes(`Against other ${page.plural}`) || answer.text.includes("More than")) problems.push(`a comparison with other ${page.plural}, which needs 20 of them and the fixture has one`);
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);

  const character = `/c/${page.character}`;
  const own = expectStatus(await ctx.http.page(character), 200, character);
  const band = [];
  if (!own.text.includes(`<a href="${route}">${page.title}</a>`)) band.push(`the ${page.title} link in the line of pages under the head`);
  if (!own.text.includes(`<a href="${route}">All ${page.slug} numbers</a>`)) band.push("the band's link");
  const absent = missing(tokensOf(own.text), page.band);
  if (absent.length > 0) band.push(`the band figures ${absent.join(", ")}`);
  if (band.length > 0) throw new Error(`${character} should have ${band.join(", ")}`);
}

export const roguePageShowsItsFigures = (ctx) => pageShows(ctx, PAGES[0]);
