import { STRANGER, WARLOCK, expectError, expectJson, expectStatus } from "../support.mjs";
import { HEAD, SHARDS } from "../warlock-expected.mjs";

const READ = "GET /api/v1/installs/me/class-page";
const PAGE_URL = new RegExp(`^https?://[^/]+/c/${WARLOCK}#class$`);
const BLOCK_TYPES = ["flow", "link", "mirror", "stack", "bag", "rank", "pair", "split", "titled", "columns"];

function* blocksOf(blocks) {
  for (const block of blocks) {
    yield block;
    if (block.type === "titled") yield* blocksOf(block.blocks);
    if (block.type === "columns") yield* blocksOf(block.columns);
  }
}

function modulesOf(page) {
  return [...page.sheet, ...page.party].flatMap((row) => row.modules);
}

function pageOf(answer, route = READ) {
  const pages = expectJson(expectStatus(answer, 200, route), route).characters;
  if (!Array.isArray(pages)) throw new Error(`${route}: expected a characters list, saw ${answer.text.slice(0, 300)}`);
  return pages;
}

/** The three head figures and two soul shard counts are those the fixture's lines work out to, and every block, link and icon is plain data. */
export async function installReadsItsClassPage(ctx) {
  const pages = pageOf(await ctx.client.classPage());
  const page = pages.find((entry) => entry.name === WARLOCK);
  if (!page) throw new Error(`${READ}: expected the class page of ${WARLOCK}, saw ${pages.map((entry) => entry.name).join(", ") || "none"}`);
  const problems = [];
  if (page.class !== "warlock" || page.class_name !== "Warlock" || page.scene !== "warlock") problems.push(`class ${page.class}, ${page.class_name}, ${page.scene}`);
  if (!PAGE_URL.test(page.url ?? "")) problems.push(`url ${page.url}`);
  for (const [label, text] of HEAD.slice(0, 3)) {
    const figure = page.figures.find((entry) => entry.label === label);
    if (!figure || figure.text !== text || typeof figure.value !== "number") problems.push(`figure ${label}: expected the number written ${text}, saw ${JSON.stringify(figure)}`);
  }
  const blocks = [...blocksOf(modulesOf(page).flatMap((module) => module.blocks))];
  const flow = blocks.find((block) => block.type === "flow" && block.left.some((item) => item.label === "Drain Soul"));
  for (const [label, count] of [SHARDS[0], SHARDS[8]]) {
    const item = flow && [...flow.left, ...flow.right].find((entry) => entry.label === label);
    if (!item || String(item.count) !== count) problems.push(`soul shard flow ${label}: expected ${count}, saw ${JSON.stringify(item)}`);
  }
  const unknown = blocks.filter((block) => !BLOCK_TYPES.includes(block.type)).map((block) => block.type);
  if (unknown.length > 0) problems.push(`block types outside ${BLOCK_TYPES.join(", ")}: ${unknown.join(", ")}`);
  const addresses = JSON.stringify(page).match(/"icon":"[^"]*"/g) ?? [];
  const relative = addresses.filter((entry) => !/^"icon":"https?:\/\//.test(entry));
  if (relative.length > 0) problems.push(`icons that are not absolute addresses: ${relative.slice(0, 3).join(", ")}`);
  if (page.comparison !== null) problems.push("a comparison, which needs 20 warlocks and the fixture has one");
  if (problems.length > 0) throw new Error(`${READ} for ${WARLOCK}:\n${problems.join("\n")}`);
}

export async function classPageRefusesWhatIsNotTheInstalls(ctx) {
  expectError(await ctx.client.classPage({ sign: false }), 401, "missing_signature", `an unsigned ${READ}`);
  const stranger = pageOf(await ctx.stranger.classPage());
  if (stranger.some((entry) => entry.name === WARLOCK)) throw new Error(`${READ} for the stranger install served ${WARLOCK}, a character of another install`);
  const named = pageOf(await ctx.stranger.classPage({ character: WARLOCK }), `${READ}/${WARLOCK}`);
  if (named.length > 0) throw new Error(`${READ}/${WARLOCK} from the stranger install served ${named.length} pages of a character it does not own`);
  const own = pageOf(await ctx.client.classPage({ character: WARLOCK.toLowerCase() }), `${READ}/${WARLOCK.toLowerCase()}`);
  if (own.length !== 1 || own[0].name !== WARLOCK) throw new Error(`${READ}/${WARLOCK.toLowerCase()} should serve ${WARLOCK} alone, saw ${own.map((entry) => entry.name).join(", ") || "none"}`);
  expectStatus(await ctx.client.classPage({ character: "no spaces allowed" }), 404, `${READ} with a name that is not Name-Surname`);
  const tailor = pageOf(await ctx.client.classPage({ character: STRANGER }), `${READ}/${STRANGER}`);
  if (tailor.length > 0) throw new Error(`${READ}/${STRANGER} from the first install served ${tailor.length} pages of a character it does not own`);
}
