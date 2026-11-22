import { NAME, escapeRegExp, expectError, expectJson, expectStatus, failOn, pool } from "../support.mjs";

const CONCURRENCY = 6;
const RULESET = "unidentified";
const RECORDS = { characters: NAME, creatures: "113", items: "769", quests: "783", zones: "1436" };

const SHAPES = [
  ["/stats", ["totals", "coverage", "rulesets"]],
  ["/rulesets", ["slug", "label", "kind", "hardcore", "recorded"]],
  ["/rulesets/{slug}", ["totals", "coverage", "rulesets"]],
  ["/rulesets/{slug}/series/{series}", ["levels", "reached", "time_per_level"]],
  ["/leaderboards", ["board", "title", "description", "value"]],
  ["/leaderboards/{board}", ["rank", "name", "race", "class", "value", "ruleset", "hardcore", "deaths", "since", "at"]],
  ["/search", ["characters", "creatures", "items", "zones", "quests", "pages"]],
  ["/characters/{name}", ["name", "race", "class", "faction", "level", "recorded_seconds", "ruleset", "week", "feats", "standings"]],
  ["/characters/{name}/series/{series}", ["pace"]],
  ["/creatures/{id}", ["id", "name", "facts", "kills", "drops", "cells"]],
  ["/items/{id}", ["id", "name", "sources", "dropped", "source_count", "best_rate"]],
  ["/zones/{id}", ["id", "name", "rect", "played", "deaths", "killers", "creatures"]],
  ["/quests/{id}", ["id", "name", "totals", "levels", "zones"]],
  ["/map/heat", ["cells"]],
  ["/map/density", ["level", "columns", "rows", "cells"]],
];

function templateExpression(template) {
  return new RegExp("^" + template.split(/\{[a-z]+\}/).map(escapeRegExp).join("[^/?]+") + "(\\?.*)?$");
}

/** The templates and the example paths the /developers page lists, which it renders from App\Support\PublicApiCatalog. */
export async function listedEndpoints(ctx) {
  const page = expectStatus(await ctx.http.page("/developers"), 200, "GET /developers");
  const templates = [...page.text.matchAll(/<h2><b>GET<\/b>\/api\/v1\/public(\/[^<]*)<\/h2>/g)].map((match) => match[1]);
  const examples = [...new Set([...page.text.matchAll(/curl\s+\S*?\/api\/v1\/public(\/[^\s<]*)</g)].map((match) => match[1].replaceAll("&amp;", "&")))];
  return { templates, examples };
}

function fixtureExample(example) {
  const [pathname, query] = example.split("?");
  const parts = pathname.split("/");
  if (parts[1] in RECORDS) parts[2] = RECORDS[parts[1]];
  if (parts[1] === "rulesets" && parts[2] !== "all" && parts.length === 3) parts[2] = RULESET;
  return parts.join("/") + (query ? "?" + query : "");
}

function missingKeys(data, keys) {
  const row = Array.isArray(data) ? data[0] : data;
  if (row === undefined) return [];
  return keys.filter((key) => !(key in row));
}

async function answerOf(ctx, route) {
  ctx.answers ??= new Map();
  if (!ctx.answers.has(route)) ctx.answers.set(route, ctx.http.api("/api/v1/public" + route));
  return ctx.answers.get(route);
}

export async function everyEndpointAnswers(ctx) {
  const { templates, examples } = await listedEndpoints(ctx);
  if (templates.length < SHAPES.length) {
    throw new Error(`/developers lists ${templates.length} endpoints, fewer than the ${SHAPES.length} this lane knows: ${templates.join(" ")}`);
  }
  const uncovered = templates.filter((template) => !examples.some((example) => templateExpression(template).test(example)));
  if (uncovered.length > 0) throw new Error(`/developers lists no example for: ${uncovered.join(", ")}`);

  const paths = examples.map(fixtureExample);
  const problems = await pool(paths, CONCURRENCY, async (route) => {
    const answer = await answerOf(ctx, route);
    if (answer.status === 429) expectStatus(answer, 200, route);
    if (answer.status !== 200) return `${route}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`;
    if (answer.json === null || !("data" in answer.json) || !("meta" in answer.json)) {
      return `${route}: expected {data, meta}, saw ${answer.text.slice(0, 300)}`;
    }
    const template = templates.find((entry) => templateExpression(entry).test(route));
    const shape = SHAPES.find(([entry]) => entry === template);
    const missing = shape ? missingKeys(answer.json.data, shape[1]) : [];
    return missing.length > 0 ? `${route}: data lacks ${missing.join(", ")}: ${answer.text.slice(0, 300)}` : null;
  });
  failOn(problems, paths.length, "public endpoints");
}

export async function characterIsServed(ctx) {
  const answer = expectStatus(await answerOf(ctx, `/characters/${NAME}`), 200, `GET /api/v1/public/characters/${NAME}`);
  const data = expectJson(answer, "the character answer").data;
  const facts = {
    name: NAME, race: "Human", class: "WARRIOR", faction: "Alliance", level: 6, first_level: 5, whole_life: false,
    ruleset: "unidentified", recorded_seconds: 255,
  };
  const wrong = Object.entries(facts).filter(([key, value]) => data[key] !== value).map(([key, value]) => `${key}: expected ${JSON.stringify(value)}, saw ${JSON.stringify(data[key])}`);
  const slayer = data.feats?.find((feat) => feat.key === "slayer")?.value;
  if (slayer !== 2) wrong.push(`feats.slayer.value: expected 2 kills, saw ${JSON.stringify(slayer)}`);
  if (data.week?.quests !== 1) wrong.push(`week.quests: expected 1, saw ${JSON.stringify(data.week?.quests)}`);
  if (wrong.length > 0) throw new Error(`${NAME} is served with other facts than the fixture holds:\n${wrong.join("\n")}`);
}

export async function recordsAreServed(ctx) {
  const wanted = [
    ["/creatures/113", (data) => data.name === "Stonetusk Boar", "the name Stonetusk Boar"],
    ["/items/769", (data) => data.name === "Chunk of Boar Meat", "the name Chunk of Boar Meat"],
    ["/quests/783", (data) => data.name === "A Threat Within", "the name A Threat Within"],
    ["/zones/1436", (data) => data.name === "Westfall", "the name Westfall"],
    ["/zones/1429", (data) => data.name === "Elwynn Forest", "the name Elwynn Forest"],
  ];
  const problems = await pool(wanted, CONCURRENCY, async ([route, holds, what]) => {
    const answer = await answerOf(ctx, route);
    if (answer.status === 429) expectStatus(answer, 200, route);
    if (answer.status !== 200) return `${route}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`;
    return holds(answer.json?.data ?? {}) ? null : `${route}: expected ${what}, saw ${answer.text.slice(0, 300)}`;
  });
  failOn(problems, wanted.length, "record answers");
}

export async function searchFindsTheCharacter(ctx) {
  const answer = expectStatus(await ctx.http.api("/api/v1/public/search?kind=characters&q=Testa"), 200, "GET /api/v1/public/search?kind=characters&q=Testa");
  const hits = expectJson(answer, "the search answer").data ?? [];
  if (!hits.some((hit) => hit.name === NAME && hit.path === `/c/${NAME}`)) {
    throw new Error(`search for Testa should list ${NAME} at /c/${NAME}, saw ${answer.text.slice(0, 300)}`);
  }
}

export async function unknownCharacterIs404(ctx) {
  expectError(await ctx.http.api("/api/v1/public/characters/Nobody-Here"), 404, "not_found", "an unknown character");
}

export async function badParameterIs422(ctx) {
  const answer = await ctx.http.api("/api/v1/public/search?q=T");
  expectError(answer, 422, "invalid_request", "a search text of one letter");
  if (!answer.json.error.fields?.q) throw new Error(`the 422 should name the field q, saw ${answer.text.slice(0, 300)}`);
}
