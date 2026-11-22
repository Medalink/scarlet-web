// Screenshots every gallery page, the landing page and the demo character from the real local server at 1280 and
// 390 wide, next to the comp each one rebuilds, and prints per page the console errors, the horizontal overflow
// in pixels and, at 1280, the share of pixels that differ from the comp.
//
//   npx -y -p playwright@1.64.0 -c "node e2e/shots.mjs [--url http://127.0.0.1:8478] [--only stats,world] [--out DIR]"
//
// Playwright's browsers are cached where PLAYWRIGHT_BROWSERS_PATH points (E:\Dev\caches\ms-playwright).
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const bin = (process.env.PATH || "").split(path.delimiter).find((p) => /_npx/.test(p) && /node_modules[\\/]\.bin[\\/]?$/.test(p));
if (!bin) throw new Error('playwright is not on PATH; run this through: npx -y -p playwright@1.64.0 -c "node e2e/shots.mjs"');
const { chromium } = createRequire(import.meta.url)(path.join(bin, "..", "playwright"));

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const arg = (name, fallback) => { const i = process.argv.indexOf("--" + name); return i > 0 ? process.argv[i + 1] : fallback; };
const base = arg("url", "http://127.0.0.1:8478");
const out = arg("out", "E:\\Dev\\scratch\\scarlet-api\\shots");
const only = arg("only", "") ? arg("only", "").split(",") : null;

// name, path on the server, the comp it is compared with
const comps = path.join(repo, "docs", "comps");
const site = path.join(repo, "static-site");
const PAGES = [
  ["landing", "/", path.join(site, "index.html")],
  ["demo", "/demo", path.join(site, "character.html")],
  ...["stats", "world", "rulesets", "leaderboards", "search", "creature", "item", "zone", "quest", "character", "account", "login", "link", "developers"]
    .map((name) => [name, "/design/" + name, path.join(comps, name + ".html")]),
].filter(([name]) => !only || only.includes(name));
const WIDTHS = [1280, 390];
const TOLERANCE = 24; // a channel may differ this much before a pixel counts as different

fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();

// Compares two PNGs in the browser, so no image library is needed.
async function compare(a, b) {
  const page = await browser.newPage();
  const result = await page.evaluate(async ({ a, b, tolerance }) => {
    const load = (src) => new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = reject; i.src = "data:image/png;base64," + src; });
    const [x, y] = [await load(a), await load(b)];
    const w = Math.max(x.width, y.width), h = Math.max(x.height, y.height);
    const read = (img) => { const c = document.createElement("canvas"); c.width = w; c.height = h; const g = c.getContext("2d", { willReadFrequently: true }); g.fillStyle = "#ff00ff"; g.fillRect(0, 0, w, h); g.drawImage(img, 0, 0); return g.getImageData(0, 0, w, h).data; };
    const [p, q] = [read(x), read(y)];
    let diff = 0;
    for (let i = 0; i < p.length; i += 4) if (Math.abs(p[i] - q[i]) > tolerance || Math.abs(p[i + 1] - q[i + 1]) > tolerance || Math.abs(p[i + 2] - q[i + 2]) > tolerance) diff++;
    return { ratio: diff / (w * h), heights: [x.height, y.height] };
  }, { a: a.toString("base64"), b: b.toString("base64"), tolerance: TOLERANCE });
  await page.close();
  return result;
}

async function shoot(context, url, file) {
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });
  page.on("requestfailed", (r) => errors.push("request failed: " + r.url()));
  await page.goto(url, { waitUntil: "load" });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(1500);
  // A world map loads its tiles after the page; wait until the first screenful is in.
  if (await page.$("canvas[data-world-map]")) await page.waitForTimeout(3500);
  const buffer = await page.screenshot({ path: file, fullPage: true });
  const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - innerWidth));
  await page.close();
  return { buffer, errors, overflow };
}

// The real pages (--real): every public page on the data the server holds, with motion allowed. The routes that name a
// record (a character, a creature, an item, a zone, a quest) are found through the public API, so no name is written here.
// Output page-<name>-<width>.png; the report has no comp diff, only overflow, console errors and page errors.
async function realPages() {
  const api = "/api/v1/public";
  const get = async (route) => (await fetch(base + api + route, { headers: { Accept: "application/json" } })).json();
  // The ruleset with the most characters, a record of each kind that has data, and the top character of a board.
  const rulesets = (await get("/rulesets")).data ?? [];
  const busiest = [...rulesets].sort((a, b) => (b.characters ?? 0) - (a.characters ?? 0))[0]?.slug;
  const hit = async (kind, q, pick = () => true) => (await get(`/search?kind=${kind}&q=${q}&limit=25`)).data?.find(pick)?.path;
  const board = (await get("/leaderboards/most-quests?limit=1")).data?.[0]?.name;
  const zone = (await get("/rulesets/all/series/deaths")).data?.zones?.[0]?.zone_id;
  const pages = [
    ["stats", "/stats"], ["rulesets", "/rulesets"], ["map", "/map"], ["camps", "/camps"], ["fishing", "/professions/fishing"], ["cooking", "/professions/cooking"], ["tailoring", "/professions/tailoring"], ["developers", "/developers"], ["landing", "/"], ["demo", "/demo"],
    ["leaderboards", "/leaderboards/most-quests"], ["leaderboards-fastest", "/leaderboards/fastest-to-60"], ["leaderboards-class", "/leaderboards/most-quests?class=WARLOCK"],
    ["search", "/search?q=th"], ["search-items", "/search?q=th&kind=items"],
  ];
  if (busiest) pages.push(["stats-ruleset", "/rulesets/" + busiest]);
  const found = [
    ["character", board ? "/c/" + encodeURIComponent(board) : null],
    ["creature", await hit("creatures", "sc", (h) => h.kills > 0)],
    ["item", await hit("items", "th")],
    ["quest", await hit("quests", "th")],
    ["zone", zone === undefined ? null : "/zones/" + zone],
  ];
  for (const [name, route] of found) if (route) pages.push([name, route]);
  return pages.filter(([name]) => !only || only.includes(name));
}

// Plain link clicks with motion allowed: a cross-page transition only starts on a click, never on goto, so this is the
// pass that shows whether a transition throws. Prints the page errors and console errors of each click.
const CLICKS = [["/", "a.btn[href='/demo']"], ["/demo", "a.brand"], ["/", ".nav a[href='/stats']"], ["/stats", "a.brand"], ["/stats", ".nav a[href='/map']"], ["/map", "a.brand"]];
async function clickPass() {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, reducedMotion: "no-preference" });
  let failures = 0;
  for (const [from, selector] of CLICKS) {
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error" && !/fonts\.g/.test(m.text())) errors.push(m.text()); });
    await page.goto(base + from, { waitUntil: "load" });
    await page.waitForTimeout(800);
    const link = await page.$(selector);
    if (!link) { errors.push("no link " + selector); } else { await link.click(); await page.waitForTimeout(1500); }
    failures += errors.length;
    console.log("click".padEnd(14), (from + " " + selector).slice(0, 38).padEnd(40), errors.length ? errors.join(" | ") : "none");
    await page.close();
  }
  await context.close();
  return failures;
}

// The width of the map stage on a sign-in or link page and on its gallery copy must agree (not a 1 px column).
async function stagePass() {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  let failures = 0;
  for (const name of ["login", "link"]) {
    const widths = [];
    for (const route of ["/" + name, "/design/" + name]) {
      const page = await context.newPage();
      await page.goto(base + route, { waitUntil: "load" });
      widths.push(await page.evaluate(() => Math.round(document.querySelector(".stage")?.getBoundingClientRect().width ?? -1)));
      await page.close();
    }
    const ok = widths[0] > 100 && widths[0] === widths[1];
    failures += ok ? 0 : 1;
    console.log("stage".padEnd(14), `/${name} ${widths[0]}px, /design/${name} ${widths[1]}px`.padEnd(40), ok ? "same" : "DIFFERENT");
  }
  await context.close();
  return failures;
}

if (process.argv.includes("--real")) {
  console.log("page".padEnd(14), "width".padEnd(6), "overflow".padEnd(9), "console and page errors");
  let failures = 0;
  for (const width of WIDTHS) {
    const context = await browser.newContext({ viewport: { width, height: width > 600 ? 800 : 844 }, reducedMotion: "no-preference" });
    for (const [name, route] of await realPages()) {
      const built = await shoot(context, base + route, path.join(out, `page-${name}-${width}.png`));
      const errors = [...new Set(built.errors)].filter((e) => !/fonts\.g/.test(e));
      failures += errors.length + (built.overflow > 0 ? 1 : 0);
      console.log(name.padEnd(14), String(width).padEnd(6), (built.overflow + "px").padEnd(9), errors.length ? errors.join(" | ") : "none");
    }
    await context.close();
  }
  failures += await clickPass();
  failures += await stagePass();
  await browser.close();
  process.exit(failures ? 1 : 0);
}

console.log("page".padEnd(13), "width".padEnd(6), "overflow".padEnd(9), "diff".padEnd(8), "height built/comp".padEnd(18), "console errors");
for (const width of WIDTHS) {
  const context = await browser.newContext({ viewport: { width, height: width > 600 ? 800 : 844 }, reducedMotion: "reduce" });
  for (const [name, route, comp] of PAGES) {
    const built = await shoot(context, base + route, path.join(out, `design-${name}-${width}.png`));
    const ref = await shoot(context, pathToFileURL(comp).href, path.join(out, `comp-${name}-${width}.png`));
    const { ratio, heights } = await compare(built.buffer, ref.buffer);
    const errors = [...new Set(built.errors)].filter((e) => !/fonts\.g/.test(e));
    console.log(name.padEnd(13), String(width).padEnd(6), (built.overflow + "px").padEnd(9), (ratio * 100).toFixed(2).padStart(5) + "%  ", `${heights[0]}/${heights[1]}`.padEnd(18), errors.length ? errors.join(" | ") : "none", ref.overflow ? `(comp overflows ${ref.overflow}px)` : "");
  }
  await context.close();
}
await browser.close();
