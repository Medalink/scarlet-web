// T3's html_preview loads one self-contained file, so this inlines the shared CSS and JS.
//   node tools/preview-bundle.mjs <output folder>
// The site itself needs no build step; this is only for previews.
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const site = join(dirname(fileURLToPath(import.meta.url)), "..");
const out = process.argv[2];
if (!out) throw new Error("usage: node tools/preview-bundle.mjs <output folder>");
mkdirSync(out, { recursive: true });

for (const page of ["index.html", "character.html"]) {
  let html;
  try { html = readFileSync(join(site, page), "utf8"); } catch { continue; }
  html = html
    .replace(/<link rel="icon"[^>]*>\n?/, "")
    .replace(/<link rel="stylesheet" href="(assets\/[^"]+)">/g, (_, f) => `<style>\n${readFileSync(join(site, f), "utf8")}\n</style>`)
    .replace(/<script src="(assets\/[^"]+)"><\/script>/g, (_, f) => `<script>\n${readFileSync(join(site, f), "utf8").replace(/<\/script/g, "<\\/script")}\n</script>`);
  // html_preview grows its frame to fit the page and rejects viewport units, so pin the hero to a laptop height.
  html = html.replace(/min\(100svh, ([\d.]+rem)\)/g, "48rem");
  writeFileSync(join(out, page), html);
  console.log(`${page}: ${html.length} chars`);
}
