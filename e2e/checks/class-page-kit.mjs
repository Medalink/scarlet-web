import { expectStatus } from "../support.mjs";
import { ERROR_PAGE, heading } from "./pages.mjs";
import { missing, showsLines, tokensOf } from "./class-page.mjs";

/**
 * Reads a class page against the figures worked out from its fixture: `groups` are label and value pairs, `sequences`
 * are the lines of a callout or a talent module in order, and `words` are texts that must appear as they are written.
 */
export async function pageShowsItsFigures(ctx, { route, name, groups, sequences = {}, words = [] }) {
  const page = expectStatus(await ctx.http.page(route), 200, route);
  if (ERROR_PAGE.test(page.text)) throw new Error(`${route}: the body carries the markers of an error page: ${page.text.slice(0, 300)}`);
  if (!heading(name).test(page.text)) throw new Error(`${route}: expected the heading ${name}, saw ${page.text.slice(0, 300)}`);
  const tokens = tokensOf(page.text);
  const problems = [];
  for (const [group, pairs] of Object.entries(groups)) {
    const absent = missing(tokens, pairs);
    if (absent.length > 0) problems.push(`${group}: ${absent.join(", ")}`);
  }
  for (const [group, lines] of Object.entries(sequences)) {
    const lost = lines.filter((one) => !showsLines(tokens, one)).map(([first, value]) => `${first} ${value ?? ""}`.trim());
    if (lost.length > 0) problems.push(`${group}: ${lost.join(", ")}`);
  }
  const text = page.text.replaceAll("&#039;", "'");
  const unsaid = words.filter((one) => !text.includes(one));
  if (unsaid.length > 0) problems.push(`words: ${unsaid.join(", ")}`);
  const plural = `${name.toLowerCase()}s`;
  if (page.text.includes(`Against other ${plural}`) || page.text.includes("More than")) problems.push(`a comparison with other ${plural}, which needs 20 of them and the fixture has one`);
  if (problems.length > 0) throw new Error(`${route} should show every figure worked out from the fixture, but lacks:\n${problems.join("\n")}`);
}

/** The character page's line of pages, the band of five figures above "This week", and a 404 for another class's slug. */
export async function bandLinksItsClassPage(ctx, { character, slug, name, band }) {
  const route = `/c/${character}`;
  const page = expectStatus(await ctx.http.page(route), 200, route);
  const problems = [];
  if (!page.text.includes(`<a href="${route}/${slug}">${name}</a>`)) problems.push(`the ${name} link in the line of pages under the head`);
  if (!page.text.includes(`<a href="${route}/${slug}">All ${slug} numbers</a>`)) problems.push(`the band's link All ${slug} numbers`);
  const absent = missing(tokensOf(page.text), band);
  if (absent.length > 0) problems.push(`the band figures ${absent.join(", ")}`);
  if (page.text.indexOf('id="h-band"') < 0 || page.text.indexOf('id="h-band"') > page.text.indexOf('id="h-week"')) problems.push("the band above This week");
  if (problems.length > 0) throw new Error(`${route} should have ${problems.join(", ")}`);
  const wrong = await ctx.http.page(`${route}/mage`);
  if (wrong.status !== 404) throw new Error(`${route}/mage answered ${wrong.status}: a class page exists only at the character's own class`);
}
