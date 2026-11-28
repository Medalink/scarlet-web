import { MAGE, Skip } from "../support.mjs";
import { ERROR_PAGE } from "./pages.mjs";

const SPEC = "/talents/mage/frost";
const LAYOUT_NODES = 36;
const LAYOUT_ARROWS = 21;
const BROWSER_MS = 8000;

const browserOf = (ctx) => {
  if (!ctx.browser) throw new Skip("no browser is running: the window check did not pass");
  return ctx.browser;
};

const nodeOf = (html, title) => {
  const node = html.match(new RegExp(`<a [^>]*class="(tt-node[^"]*)"[^>]*title="${title.replaceAll("'", "&#039;")}"[^>]*>([\\s\\S]*?)</a>`));
  if (!node) throw new Error(`${SPEC} draws no node titled "${title}"`);
  return { classes: node[1].split(" "), rate: node[2].match(/<b class="tt-rate">([^<]*)<\/b>/)?.[1] };
};

export async function specDrawsItsTrees(ctx) {
  const answer = await ctx.http.page(SPEC);
  if (answer.status !== 200 || ERROR_PAGE.test(answer.text)) throw new Error(`${SPEC}: expected 200, saw ${answer.status}: ${answer.text.slice(0, 300)}`);
  const html = answer.text;
  const trees = [...html.matchAll(/<div class="tt-tree" data-tree>\s*<h3[^>]*>([^<]*)<\/h3>/g)].map((tree) => tree[1]);
  if (trees.join(",") !== "Arcane,Fire,Frost") throw new Error(`${SPEC} draws the trees ${trees.join(", ")}, not Arcane, Fire, Frost in the order of their columns`);
  const nodes = html.match(/data-node="\d+"/g)?.length ?? 0;
  if (nodes !== LAYOUT_NODES) throw new Error(`${SPEC} draws ${nodes} nodes, the fixture layout has ${LAYOUT_NODES}`);
  const arrows = html.match(/<g class="tt-arrow/g)?.length ?? 0;
  if (arrows !== LAYOUT_ARROWS) throw new Error(`${SPEC} draws ${arrows} arrows, the fixture layout joins ${LAYOUT_ARROWS} pairs`);
  const choices = html.match(/class="tt-node[^"]* choice"/g)?.length ?? 0;
  if (choices !== 1) throw new Error(`${SPEC} draws ${choices} choice nodes, the fixture has one`);
  const untaken = nodeOf(html, "Piercing Ice, 0 of 2 characters");
  if (!untaken.classes.includes("off") || untaken.rate !== "0%") throw new Error(`a talent no character took is not dimmed at 0%: ${JSON.stringify(untaken)}`);
  const half = nodeOf(html, "Cold Snap, 1 of 2 characters, average rank 1.0");
  if (half.classes.includes("off") || half.rate !== "50%") throw new Error(`Cold Snap, which one of two mages took, should read 50%: ${JSON.stringify(half)}`);
  const whole = nodeOf(html, "Ice Barrier, 2 of 2 characters, average rank 1.0");
  if (whole.rate !== "100%") throw new Error(`Ice Barrier, which both mages took, should read 100%: ${JSON.stringify(whole)}`);
  const choice = nodeOf(html, "Blast Wave 1 · Dragon's Breath 1 of 2 characters, average rank 1.0");
  if (!choice.classes.includes("choice") || choice.rate !== "100%") throw new Error(`the choice node should count each spell once and read 100% of the characters that took either: ${JSON.stringify(choice)}`);
  const first = html.match(/<a [^>]*href="([^"]*)"[^>]*title="Blast Wave 1 · Dragon&#039;s Breath 1 of 2 characters[^"]*"/)?.[1];
  if (first !== "/spells/12207") throw new Error(`a tie on a choice node links the first entry of the layout (Blast Wave, /spells/12207), saw ${first}`);
}

export async function talentsListLeadsWithTheSplit(ctx) {
  const answer = await ctx.http.page("/talents");
  if (answer.status !== 200 || ERROR_PAGE.test(answer.text)) throw new Error(`/talents: expected 200, saw ${answer.status}`);
  const rows = [...answer.text.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/g)].map((row) => [...row[1].matchAll(/<td[^>]*>([\s\S]*?)<\/td>/g)].map((cell) => cell[1].replace(/<[^>]*>/g, "").trim())).filter((cells) => cells.length > 0);
  if (rows.length === 0) throw new Error("/talents lists no spec");
  const odd = rows.filter((cells) => !/^\d+( \/ \d+)*$/.test(cells[0]));
  if (odd.length > 0) throw new Error(`a spec row does not lead with its split: ${JSON.stringify(odd[0])}`);
  const mage = rows.find((cells) => cells.includes("Mage"));
  if (mage?.[0] !== "12 / 0 / 35" || !mage[1].startsWith("Frost")) throw new Error(`the Mage row should lead with 12 / 0 / 35 and name Frost, saw ${JSON.stringify(mage)}; ${MAGE} holds 12 / 0 / 35, the other mage 7 / 1 / 15, and a tie takes the split that sorts first`);
}

export async function downloadStripLoads(ctx) {
  const tab = await browserOf(ctx).open(ctx.base + "/download", { width: 1280, height: 800 });
  try {
    const loaded = `(() => { const shots = [...document.querySelectorAll(".dl-shots img")]; return shots.length === 2 && shots.every((img) => img.complete && img.naturalWidth > 0 && img.naturalHeight > 0); })()`;
    await tab.until(loaded, BROWSER_MS, `the two app screenshots under the download button did not load (${tab.problems.join(" | ") || "no page errors"})`);
    const shown = await tab.evaluate(`[...document.querySelectorAll(".dl-shots img")].map((img) => [img.getAttribute("loading"), img.getAttribute("width"), img.getAttribute("height"), Math.round(img.getBoundingClientRect().width)])`);
    if (!shown.every(([loading, width, height, drawn]) => loading === "lazy" && Number(width) > 0 && Number(height) > 0 && drawn > 200)) throw new Error(`the screenshots are not lazy with a set size or are not drawn: ${JSON.stringify(shown)}`);
  } finally {
    await tab.close();
  }
}

export async function copyButtonsCopyTheirBlock(ctx) {
  const tab = await browserOf(ctx).open(ctx.base + "/developers", { width: 1280, height: 800 });
  try {
    await tab.evaluate(`window.__copied = []; Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText: (text) => { window.__copied.push(text); return Promise.resolve(); } } })`);
    const counts = await tab.evaluate(`({ blocks: document.querySelectorAll("pre.json").length, buttons: document.querySelectorAll(".json-box > [data-copy]").length, examples: document.querySelectorAll("details[data-example] pre.json").length })`);
    if (counts.blocks < 20 || counts.buttons !== counts.blocks) throw new Error(`every code block needs a copy button: ${JSON.stringify(counts)}`);
    if (counts.examples < 20) throw new Error(`the endpoints show ${counts.examples} example responses, far fewer than the ${counts.blocks} code blocks suggest`);
    await tab.evaluate(`document.querySelector(".json-box > [data-copy]").click()`);
    await tab.until(`window.__copied.length === 1 && document.querySelector(".json-box > [data-copy]").textContent === "Copied"`, BROWSER_MS, "the first copy button copied nothing");
    await tab.evaluate(`(() => { const details = document.querySelector("details[data-example]"); details.open = true; details.querySelector("[data-copy]").click(); })()`);
    await tab.until(`window.__copied.length === 2`, BROWSER_MS, "the copy button of an example response copied nothing");
    const [curl, example] = await tab.evaluate(`window.__copied`);
    if (!/^curl http\S+\/api\/v1\/public\/stats$/.test(curl)) throw new Error(`the first block should copy its curl line, copied ${JSON.stringify(curl.slice(0, 120))}`);
    if (!example.startsWith('{\n  "data"') || JSON.parse(example) === null) throw new Error(`an example response should copy as JSON, copied ${JSON.stringify(example.slice(0, 120))}`);
  } finally {
    await tab.close();
  }
}
