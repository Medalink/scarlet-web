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
  const buffer = await page.screenshot({ path: file, fullPage: true });
  const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - innerWidth));
  await page.close();
  return { buffer, errors, overflow };
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
