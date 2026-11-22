import { ECHO, answerOf, expectStatus, failOn, pool, rollupsRefreshed } from "../support.mjs";
import { ERROR_PAGE, heading } from "./pages.mjs";

const CONCURRENCY = 6;
const SHOWN = ECHO.replace("-", " ");

export const GATHER_KEYS = ["kind", "totals", "items", "contents", "waits", "per_point", "by_level", "hours", "lures", "lured", "poles", "creatures", "zones", "heat", "board", "rulesets"];
const CRAFT_KEYS = ["profession", "totals", "recipes", "difficulty", "per_point", "by_level", "hours", "reagents", "zones", "board", "rulesets"];

const EMPTY_ANSWER = /saw null|Too few characters/;
const EMPTY_HINT = "the server keeps an answer read from a rollup for 60 s and serves it once more after that while it reads again, so a profession page or answer read before the upload was refreshed (e2e/seo.mjs reads them) shows nothing until a read made after that: run the lane again";

function failOnWithHint(problems, total, noun) {
  try {
    failOn(problems, total, noun);
  } catch (error) {
    if (EMPTY_ANSWER.test(error.message)) error.message += `\n${EMPTY_HINT}`;
    throw error;
  }
}

const rowsOf = (rows, keys) => (rows ?? []).map((row) => keys.map((key) => row[key]).join(":"));

function totalsOf(data, wanted) {
  return Object.entries(wanted).filter(([key, value]) => data.totals?.[key] !== value).map(([key, value]) => `totals.${key}: expected ${value}, saw ${JSON.stringify(data.totals?.[key])}`);
}

function listsOf(data, wanted) {
  return wanted.flatMap(([field, keys, rows]) => {
    const seen = rowsOf(data[field], keys);
    return JSON.stringify(seen) === JSON.stringify(rows) ? [] : [`${field} (${keys.join(":")}): expected ${JSON.stringify(rows)}, saw ${JSON.stringify(seen)}`];
  });
}

const ON_THE_BOARD = (value) => ["board", ["name", "value"], [`${ECHO}:${value}`]];

const READS = [
  {
    slug: "fishing",
    keys: GATHER_KEYS,
    totals: { attempts: 6, looted: 4, characters: 1, skill_ups: 1, items: 3, container_catches: 1 },
    lists: [
      ["items", ["item_id", "quantity", "gathers"], ["6291:2:2", "6303:1:1", "7973:1:1"]],
      ["contents", ["item_id", "opens"], ["7971:1"]],
      ["lures", ["spell_id", "casts"], ["8087:6"]],
      ["poles", ["item_id", "casts"], ["6256:6"]],
      ["zones", ["zone_id", "gathers", "looted"], ["1429:6:4"]],
      ON_THE_BOARD(4),
    ],
    lured: 6,
  },
  {
    slug: "mining",
    keys: GATHER_KEYS,
    totals: { attempts: 3, looted: 3, characters: 1, skill_ups: 1, first_strikes: 2 },
    lists: [["items", ["item_id", "quantity", "gathers"], ["2770:4:3"]], ON_THE_BOARD(3)],
  },
  {
    slug: "herbalism",
    keys: GATHER_KEYS,
    totals: { attempts: 3, looted: 3, characters: 1 },
    lists: [["items", ["item_id", "quantity", "gathers"], ["2447:2:2", "765:1:1"]], ON_THE_BOARD(3)],
  },
  {
    slug: "skinning",
    keys: GATHER_KEYS,
    totals: { attempts: 2, looted: 2, characters: 1 },
    lists: [
      ["items", ["item_id", "quantity", "gathers"], ["2934:2:2", "2318:1:1"]],
      ["creatures", ["npc_id", "gathers"], ["113:2"]],
      ON_THE_BOARD(2),
    ],
  },
  {
    slug: "cooking",
    keys: CRAFT_KEYS,
    totals: { crafts: 3, made: 3, characters: 1, recipes: 1, skill_ups: 1 },
    lists: [
      ["recipes", ["recipe_id", "item_id", "crafts", "skill_ups"], ["2538:2679:3:1"]],
      ["difficulty", ["difficulty", "crafts", "skill_ups"], ["1:2:1", "2:1:0"]],
      ON_THE_BOARD(3),
    ],
  },
  {
    slug: "blacksmithing",
    keys: CRAFT_KEYS,
    totals: { crafts: 2, made: 2, characters: 1, recipes: 1, skill_ups: 0 },
    lists: [
      ["recipes", ["recipe_id", "item_id", "crafts", "skill_ups"], ["2660:2862:2:0"]],
      ["reagents", ["item_id", "quantity"], ["2835:2"]],
      ON_THE_BOARD(2),
    ],
  },
];

export async function apiShowsTheUpload(ctx) {
  await rollupsRefreshed(ctx);
  const problems = await pool(READS, CONCURRENCY, async ({ slug, keys, totals, lists, lured }) => {
    const route = `/professions/${slug}`;
    const answer = await answerOf(ctx, route);
    if (answer.status === 429) expectStatus(answer, 200, route);
    if (answer.status !== 200) return `${route}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`;
    const data = answer.json?.data;
    if (!data || !("meta" in answer.json)) return `${route}: expected {data, meta}, saw ${answer.text.slice(0, 300)}`;
    const missing = keys.filter((key) => !(key in data));
    if (missing.length > 0) return `${route}: data lacks ${missing.join(", ")}: ${answer.text.slice(0, 300)}`;
    const wrong = [...totalsOf(data, totals), ...listsOf(data, lists)];
    if (lured !== undefined && data.lured !== lured) wrong.push(`lured: expected ${lured}, saw ${JSON.stringify(data.lured)}`);
    return wrong.length > 0 ? `${route}: ${wrong.join("; ")}` : null;
  });
  failOnWithHint(problems, READS.length, "profession answers");
}

const escaped = (phrase) => phrase.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const holdsWithNoDigitBeside = (text, phrase) => new RegExp(`(?<![0-9])${escaped(phrase)}(?![0-9])`).test(text);

const textOf = (html) => html.replace(/<(script|style|svg)[\s\S]*?<\/\1>/g, " ").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ");

const PAGES = [
  { slug: "fishing", title: "Fishing", shows: ["6 casts", "Casts that catch 67%", "Raw Brilliant Smallfish", "Raw Slitherskin Mackerel", "Big-mouth Clam", "Black Pearl", "Shiny Bauble", "Fishing Pole", SHOWN] },
  { slug: "herbalism", title: "Herbalism", shows: ["Picks that give an herb 100%", "Peacebloom", "Silverleaf", SHOWN] },
  { slug: "mining", title: "Mining", shows: ["3 strikes", "Copper Ore", "First strike on a vein 67%", "Later strike on the same vein 33%", SHOWN] },
  { slug: "skinning", title: "Skinning", shows: ["2 skins", "Ruined Leather Scraps", "Light Leather", "Stonetusk Boar", SHOWN] },
  { slug: "alchemy", title: "Alchemy", shows: [] },
  { slug: "blacksmithing", title: "Blacksmithing", shows: ["2 crafts", "Rough Sharpening Stone", "What the crafts used Rough Stone 2 used", SHOWN] },
  { slug: "enchanting", title: "Enchanting", shows: [] },
  { slug: "engineering", title: "Engineering", shows: [] },
  { slug: "leatherworking", title: "Leatherworking", shows: [] },
  { slug: "tailoring", title: "Tailoring", shows: [] },
  { slug: "cooking", title: "Cooking", shows: ["3 crafts", "Charred Wolf Meat", "Medium 2 50%", "Easy 1 0%", SHOWN] },
  { slug: "first-aid", title: "First Aid", shows: [] },
];

export async function pagesShowTheUpload(ctx) {
  await rollupsRefreshed(ctx);
  const problems = await pool(PAGES, CONCURRENCY, async ({ slug, title, shows }) => {
    const route = `/professions/${slug}`;
    const answer = await ctx.http.page(route);
    if (answer.status === 429) expectStatus(answer, 200, route);
    if (answer.status !== 200) return `${route}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`;
    if (ERROR_PAGE.test(answer.text)) return `${route}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`;
    if (!heading(title).test(answer.text)) return `${route}: expected the heading ${title}, saw ${answer.text.slice(0, 300)}`;
    const text = textOf(answer.text);
    const missing = shows.filter((shown) => !holdsWithNoDigitBeside(text, shown));
    return missing.length > 0 ? `${route}: expected ${missing.map((shown) => JSON.stringify(shown)).join(", ")}, saw ${text.slice(text.indexOf(title), text.indexOf(title) + 900)}` : null;
  });
  failOnWithHint(problems, PAGES.length, "profession pages");
}
