// Full-page screenshots of both pages at desktop (1280) and phone (390) width, into shots/.
// Run from the site folder:  npx -y -p playwright@1.64.0 -c "node tools/shots.mjs"
// The phone pages are captured at 2x: at 3x the landing page passes Chromium's 32,767 px limit.
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const bin = (process.env.PATH || "").split(path.delimiter).find((p) => /_npx/.test(p) && /node_modules[\\/]\.bin[\\/]?$/.test(p));
if (!bin) throw new Error('playwright is not on PATH; run this through: npx -y -p playwright@1.64.0 -c "node tools/shots.mjs"');
const { chromium, devices } = createRequire(import.meta.url)(path.join(bin, "..", "playwright"));

const site = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const PAGES = [["landing", "index.html", 2500], ["character", "character.html", 1500]];
const SIZES = [
  ["desktop-1280", { viewport: { width: 1280, height: 800 } }],
  ["phone-390", { ...devices["iPhone 12"], deviceScaleFactor: 2 }],
];

const browser = await chromium.launch();
for (const [size, options] of SIZES) {
  const context = await browser.newContext(options);
  for (const [name, file, settle] of PAGES) {
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });
    await page.goto(pathToFileURL(path.join(site, file)).href);
    await page.waitForTimeout(settle);
    const out = path.join(site, "shots", `${name}-${size}.png`);
    await page.screenshot({ path: out, fullPage: true });
    const box = await page.evaluate(() => ({ w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight, vw: innerWidth }));
    console.log(`${name}-${size}.png  page ${box.w}x${box.h} css px, viewport ${box.vw}${box.w > box.vw ? "  SIDEWAYS SCROLL" : ""}${errors.length ? "  ERRORS: " + errors.join(" | ") : ""}`);
    await page.close();
  }
  await context.close();
}
await browser.close();
