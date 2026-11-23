import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { Browser, browserCommand } from "../cdp.mjs";
import { CLASS_CHARACTERS, NAME, PROBE, SHOWN, Skip, WARLOCK, failOn, pool, rollupsRefreshed } from "../support.mjs";

const CLASS_PAGES = Object.values(CLASS_CHARACTERS).map((name) => `/c/${name}`);
const WIDE = ["/", "/stats", "/map", "/camps", "/professions", "/stats/compare", "/professions/fishing", "/professions/mining", "/professions/cooking", "/professions/tailoring", "/leaderboards", "/feats", "/feats/slayer", "/search?q=Testa", `/c/${NAME}`, `/c/${WARLOCK}`, ...CLASS_PAGES, "/spells/2660", "/encounters/1065", "/items/2862"];
const NARROW = ["/", "/stats", "/camps", "/professions/fishing", "/professions/cooking", "/feats", "/feats/slayer", ...CLASS_PAGES, "/spells/2660", "/encounters/1065"];
const TABS = 4;
const SUGGEST_MS = 5000;

function browserOf(ctx) {
  if (!ctx.browser) throw new Skip("no browser is running: the window check did not pass");
  return ctx.browser;
}

export async function hasNoWindow(ctx) {
  const scratch = fs.mkdtempSync(path.join(os.tmpdir(), "scarlet-web-e2e-browser-"));
  ctx.browser = await Browser.start(scratch, browserCommand(ctx.browserPath), (url) => ctx.http.count(url));
  if (process.platform !== "win32") {
    throw new Skip(`the window check reads the Windows window handle; on ${process.platform} the browser starts headless and is not asked`);
  }
  const handle = ctx.browser.windowHandle();
  if (handle !== "0") {
    ctx.browser.stopNow();
    ctx.browser = null;
    throw new Error(`the browser has a window (MainWindowHandle ${handle}); it was stopped at once and no browser check runs`);
  }
}

async function withScreenshot(ctx, tab, label, error) {
  try {
    fs.mkdirSync(ctx.keepDir, { recursive: true });
    const file = path.join(ctx.keepDir, `${label.replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "")}.png`);
    fs.writeFileSync(file, await tab.screenshot());
    return `${error.message}\n    screenshot: ${file}`;
  } catch {
    return error.message;
  }
}

async function mapArtInstalled(ctx) {
  ctx.mapArt ??= (await ctx.http.request("/map-tiles/manifest.json")).status === 200;
  return ctx.mapArt;
}

export async function mapArtIsServed(ctx) {
  if (await mapArtInstalled(ctx)) return;
  if (process.env.E2E_MAP_ART === "1") throw new Error("the server has map art (E2E_MAP_ART=1), but /map-tiles/manifest.json does not answer 200");
  throw new Skip("this server has no map art (storage/app/maps/manifest.json), so the map art is not checked");
}

function cleanPages(routes, width, strict) {
  return async (ctx) => {
    const browser = browserOf(ctx);
    const ordered = [...routes.filter((route) => !route.startsWith("/professions/")), ...routes.filter((route) => route.startsWith("/professions/"))];
    const problems = await pool(ordered, TABS, async (route) => {
      let tab;
      try {
        if (route.startsWith("/professions/")) await rollupsRefreshed(ctx);
        tab = await browser.open(ctx.base + route, { width, height: width > 600 ? 800 : 844 });
        const found = strict ? [...tab.problems] : [];
        const overflow = await tab.overflow(width);
        if (overflow > 0) found.push(`the page is ${overflow}px wider than the window`);
        if (found.length === 0) return null;
        return `${route} at ${width}: ${await withScreenshot(ctx, tab, `${route === "/" ? "home" : route}-${width}`, new Error(found.join(" | ")))}`;
      } catch (error) {
        return `${route} at ${width}: ${tab ? await withScreenshot(ctx, tab, `${route === "/" ? "home" : route}-${width}`, error) : error.message}`;
      } finally {
        await tab?.close();
      }
    });
    failOn(problems, ordered.length, `pages at ${width} wide`);
  };
}

export const pagesRunClean = cleanPages(WIDE, 1280, true);
export const narrowPagesFit = cleanPages(NARROW, 390, false);

export async function searchSuggestsWhileTyping(ctx) {
  const browser = browserOf(ctx);
  const tab = await browser.open(ctx.base + "/search", { width: 1280, height: 800 });
  try {
    await tab.evaluate(`document.querySelector("#sq").focus()`);
    await tab.send("Input.insertText", { text: "Testa" });
    await tab.until(
      `[...document.querySelectorAll(".find-opt")].some((option) => option.textContent.includes(${JSON.stringify(SHOWN)}))`,
      SUGGEST_MS,
      `typing Testa into the search box never listed ${SHOWN} in the suggestions (${tab.problems.join(" | ") || "no page errors"})`,
    );
  } catch (error) {
    throw new Error(await withScreenshot(ctx, tab, "search-typing", error));
  } finally {
    await tab.close();
  }
}
