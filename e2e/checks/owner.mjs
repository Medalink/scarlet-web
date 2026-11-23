import { Jar, PROBE, Skip, expectJson, expectStatus } from "../support.mjs";
import { hasNoWindow } from "./browser.mjs";
import { linkToAccount, signIn } from "./pages.mjs";

const TOAST_MS = 5000;
const HEARD_MS = 5000;
const OPEN_TOAST = `document.querySelector(".feat-toast:popover-open")`;
const SERVER_HEARD = `performance.getEntriesByType("resource").some((entry) => entry.initiatorType === "fetch" && entry.name.includes("livewire") && entry.responseEnd > 0)`;

export async function browserFor(ctx) {
  if (!ctx.browser) {
    await hasNoWindow(ctx).catch((error) => {
      if (!(error instanceof Skip)) throw error;
    });
  }
  if (!ctx.browser) throw new Skip("no browser is running: the window check did not pass");
  return ctx.browser;
}

/**
 * Probe-Lane holds one feat nobody was shown. Its owner gets one toast for it, the server hears which tier showed, and
 * the next load of the page carries no toast. A visitor's page never carries one.
 */
export async function ownerSeesNewFeatOnce(ctx) {
  const route = `/c/${PROBE}`;
  const visitor = expectStatus(await ctx.http.page(route), 200, `GET ${route} as a visitor`);
  if (visitor.text.includes("feat-toast")) throw new Error(`${route} as a visitor carries toast markup`);

  const browser = await browserFor(ctx);
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const tab = await browser.open(ctx.base + route, { width: 1280, height: 800, cookies: jar.entries() });
  try {
    await tab.until(`${OPEN_TOAST}?.id === "feat-toast-seasoned"`, TOAST_MS, `the owner's ${route} did not show the Seasoned toast (${tab.problems.join(" | ") || "no page errors"})`);
    const said = await tab.evaluate(`${OPEN_TOAST}.innerText.replace(/\\s+/g, " ").trim()`);
    if (!/^Feat earned Seasoned ?Copper/.test(said)) throw new Error(`the toast should say "Feat earned Seasoned Copper", it says ${JSON.stringify(said)}`);
    await tab.until(SERVER_HEARD, HEARD_MS, "the page did not tell the server which toast showed");

    await tab.navigate(ctx.base + route);
    const left = await tab.evaluate(`[document.querySelectorAll(".feat-toasts").length, document.querySelectorAll(".feat-toast").length].join()`);
    if (left !== "1,0") throw new Error(`after a reload the owner's page should carry the toast stack and no toast (1,0), saw ${left}`);
    if (tab.problems.length > 0) throw new Error(tab.problems.join(" | "));
  } finally {
    await tab.close();
  }
}
