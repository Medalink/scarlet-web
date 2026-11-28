import { Jar, NAME, SHOWN, WARLOCK, expectJson, expectStatus, sleep } from "../support.mjs";
import { browserFor } from "./owner.mjs";
import { ERROR_PAGE, form, heading, linkToAccount, signIn, tokenIn } from "./pages.mjs";

const OWNER = { headers: { Accept: "text/html" } };
const ECONOMY_HIDDEN = "gold feats hidden by the owner";
const WAIT_MS = 8000;
const AGED_HOURS = 72;
const KEY = { key: "Escape", code: "Escape", windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 };

async function owner(ctx) {
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);
  return jar;
}

const read = async (ctx, jar, route) => expectStatus(await ctx.http.request(route, { jar, ...OWNER }), 200, `GET ${route}`);

const bytesText = (bytes) => {
  if (bytes < 1024) return `${bytes.toLocaleString("en-US")} B`;
  const units = ["KB", "MB", "GB", "TB"];
  let value = bytes;
  let unit = -1;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  const digits = value < 10 ? 1 : 0;
  return `${value.toLocaleString("en-US", { minimumFractionDigits: digits, maximumFractionDigits: digits })} ${units[unit]}`;
};

const countText = (count, noun) => `${count.toLocaleString("en-US")} ${noun}${count === 1 ? "" : "s"}`;

const tileOf = (html, label) => html.match(new RegExp(`<span class="sc-label">${label}</span>\\s*<span class="sc-num[^"]*">([^<]*)`))?.[1]?.trim();

const shown = (name) => name.replace("-", " ");

/** Each of the four account pages loads for the signed-in account under its own heading, with the same section nav marking the page it is. */
export async function accountPagesLoadUnderOneNav(ctx) {
  const jar = await owner(ctx);
  const pages = [
    ["/account", "Account", [SHOWN, "Sign-in methods", "Linked installs"]],
    ["/account/privacy", "Privacy", ["Character pages", "What the app sends"]],
    ["/account/api-keys", "API keys", []],
    ["/account/storage", "Storage", ["What is stored", "Uploads in the last 24 hours", "Full upload history", "Delete everything you uploaded"]],
  ];
  const problems = [];
  for (const [route, label, phrases] of pages) {
    const page = await read(ctx, jar, route);
    if (ERROR_PAGE.test(page.text)) problems.push(`${route} shows an error`);
    if (route !== "/account" && !heading(label).test(page.text)) problems.push(`${route} should be headed ${label}`);
    const current = [...page.text.matchAll(/<a href="([^"]+)" aria-current="page">/g)].map((match) => match[1]).filter((href) => pages.some(([path]) => path === href));
    if (current.join() !== route) problems.push(`${route} should mark only itself in the section nav, saw ${JSON.stringify(current)}`);
    for (const other of pages) if (!page.text.includes(`href="${other[0]}"`)) problems.push(`${route} should link ${other[0]}`);
    for (const phrase of phrases) if (!page.text.includes(phrase)) problems.push(`${route} should carry ${JSON.stringify(phrase)}`);
    if (route === "/account/api-keys" && !/id="key-name"|API keys need a Battle\.net sign-in/.test(page.text)) problems.push("/account/api-keys should show the key form or the line that links Battle.net");
  }
  const history = await read(ctx, jar, "/account/storage/history");
  if (!history.text.includes("<table") || ERROR_PAGE.test(history.text)) problems.push("/account/storage/history should list uploads");
  const moved = await ctx.http.request("/account/settings", { jar, redirect: "manual", ...OWNER });
  if (moved.status !== 301 || new URL(moved.headers.get("location") ?? "", ctx.base).pathname !== "/account") problems.push(`/account/settings should redirect to /account, saw ${moved.status} ${moved.headers.get("location")}`);
  if (problems.length > 0) throw new Error(problems.join("; "));
}

/**
 * The privacy modal of one character opens from its button, keeps focus inside, closes with Escape and returns focus to the
 * button. Turning on Show economy in it, a switch that saves at once, takes the hidden-gold note off the public page, and the
 * row behind it says so.
 */
export async function privacyModalOpensClosesAndSaves(ctx) {
  const browser = await browserFor(ctx);
  const jar = await owner(ctx);
  const opener = `button[aria-label="Privacy of ${SHOWN}"]`;
  const dialog = `dialog[open]`;
  const economy = `${dialog} input[aria-label="Show economy for ${SHOWN}"]`;
  const publicPage = `/c/${NAME}`;
  const hidden = async () => (await ctx.http.page(publicPage)).text.includes(ECONOMY_HIDDEN);
  const wasHidden = await hidden();
  const tab = await browser.open(`${ctx.base}/account/privacy`, { width: 1280, height: 800, cookies: jar.entries() });
  const press = async () => {
    for (const type of ["rawKeyDown", "keyUp"]) await tab.send("Input.dispatchKeyEvent", { type, ...KEY });
  };
  try {
    await tab.until(`document.querySelector('${opener}') !== null && !document.querySelector('${dialog}')`, WAIT_MS, `the privacy page has no Privacy button for ${SHOWN} (${tab.problems.join(" | ") || "no page errors"})`);
    await tab.evaluate(`document.querySelector('${opener}').focus()`);
    await tab.evaluate(`document.querySelector('${opener}').click()`);
    await tab.until(`document.querySelector('${dialog}') !== null`, WAIT_MS, "the modal did not open");
    const inside = await tab.evaluate(`document.querySelector('${dialog}').contains(document.activeElement)`);
    if (!inside) throw new Error("focus should move into the modal when it opens");
    const switches = await tab.evaluate(`[...document.querySelectorAll('${dialog} input[type=checkbox]')].map((box) => box.getAttribute('aria-label')).join('|')`);
    if (switches !== `Public page for ${SHOWN}|Show economy for ${SHOWN}|Show sessions for ${SHOWN}`) throw new Error(`the modal should carry the three switches of ${SHOWN}, saw ${switches}`);

    await press();
    await tab.until(`document.querySelector('${dialog}') === null`, WAIT_MS, "Escape did not close the modal");
    const back = await tab.evaluate(`document.activeElement === document.querySelector('${opener}')`);
    if (!back) throw new Error("focus should return to the Privacy button when the modal closes");

    await tab.evaluate(`document.querySelector('${opener}').click()`);
    await tab.until(`document.querySelector('${economy}') !== null`, WAIT_MS, "the modal did not reopen");
    await tab.evaluate(`document.querySelector('${economy}').click()`);
    for (const deadline = Date.now() + WAIT_MS; (await hidden()) === wasHidden; await sleep(250)) {
      if (Date.now() > deadline) throw new Error(`switching Show economy in the modal did not change ${publicPage}`);
    }
    const stillOpen = await tab.evaluate(`document.querySelector('${dialog}') !== null`);
    if (!stillOpen) throw new Error("the modal should stay open while a switch saves");
    const row = await tab.evaluate(`document.body.innerText.includes('${SHOWN}')`);
    if (!row) throw new Error("the privacy page lost the character row");
  } finally {
    const now = await hidden();
    if (now !== wasHidden) {
      await tab.evaluate(`document.querySelector('${economy}')?.click()`);
      for (const deadline = Date.now() + WAIT_MS; (await hidden()) !== wasHidden && Date.now() < deadline; await sleep(250));
    }
    await tab.close();
  }
  if ((await hidden()) !== wasHidden) throw new Error(`Show economy for ${SHOWN} was not put back`);
}

async function age(ctx, jar, character, hours) {
  const page = await read(ctx, jar, "/account/storage");
  const answer = await ctx.http.request("/auth/development/age-uploads", { ...form({ _token: tokenIn(page.text), character, hours: String(hours) }), method: "POST", jar, redirect: "manual" });
  if (answer.status !== 302) throw new Error(`POST /auth/development/age-uploads: expected a redirect, saw ${answer.status}: ${answer.text.slice(0, 200)}`);
}

/** Storage lists the uploads of the last 24 hours only, and the full history lists the ones before them as well. */
export async function storageListsOnlyTheLastDay(ctx) {
  const jar = await owner(ctx);
  const recent = shown(WARLOCK);
  const older = SHOWN;
  const uploaded = async (route, id) => {
    const html = (await read(ctx, jar, route)).text;
    const table = html.match(new RegExp(`<table[^>]*id="${id}"[\\s\\S]*?</table>`))?.[0] ?? "";
    return { html, table };
  };
  const before = await uploaded("/account/storage", "recent-uploads");
  for (const name of [recent, older]) if (!before.table.includes(`<td class="py-2 pr-4">${name}</td>`)) throw new Error(`${name} uploaded a moment ago and should be in the last 24 hours`);
  await age(ctx, jar, NAME, AGED_HOURS);
  try {
    const after = await uploaded("/account/storage", "recent-uploads");
    if (after.table.includes(`<td class="py-2 pr-4">${older}</td>`)) throw new Error(`${older}'s parts are ${AGED_HOURS} hours old and should not be in the last 24 hours`);
    if (!after.table.includes(`<td class="py-2 pr-4">${recent}</td>`)) throw new Error(`${recent} should stay in the last 24 hours`);
    if (!after.html.includes('href="/account/storage/history"')) throw new Error("the storage page should link the full upload history");
    const full = await uploaded("/account/storage/history", "history");
    for (const name of [recent, older]) if (!full.table.includes(`<td class="py-2 pr-4">${name}</td>`)) throw new Error(`the full history should list ${name}`);
    const rows = (table) => (table.match(/<tr class="border-t/g) ?? []).length;
    if (rows(full.table) <= rows(after.table)) throw new Error(`the full history should list more uploads than the last 24 hours (${rows(full.table)} against ${rows(after.table)})`);
    if (full.html.includes(">Older<")) throw new Error("a history of one page should have no Older link");
    expectStatus(await ctx.http.request("/account/storage/history?page=2", { jar, ...OWNER }), 200, "GET /account/storage/history?page=2");
    expectStatus(await ctx.http.request("/account/storage/history?page=0", { jar, ...OWNER, redirect: "manual" }), 302, "GET /account/storage/history?page=0");
  } finally {
    await age(ctx, jar, NAME, -AGED_HOURS);
  }
}

/** The totals of the storage page are the parts, sizes and sessions the install's own status reports, per character and in all. */
export async function storageTotalsMatchTheUpload(ctx) {
  const jar = await owner(ctx);
  const status = expectJson(expectStatus(await ctx.client.status(), 200, "GET /api/v1/upload/status"), "upload status");
  const characters = status.characters;
  const sum = (key) => characters.reduce((total, character) => total + character[key], 0);
  const html = (await read(ctx, jar, "/account/storage")).text;
  const problems = [];
  const expected = [["Parts", sum("parts").toLocaleString("en-US")], ["Size", bytesText(sum("raw_bytes"))], ["Characters", String(characters.length)], ["Sessions", sum("sessions").toLocaleString("en-US")]];
  for (const [label, value] of expected) if (tileOf(html, label) !== value) problems.push(`the ${label} tile should say ${value}, it says ${tileOf(html, label)}`);
  if (!(Number(tileOf(html, "Installs")) >= 1)) problems.push(`the Installs tile should count the linked installs, it says ${tileOf(html, "Installs")}`);
  for (const character of characters) {
    const row = `<span>${shown(character.name)}</span><b>${bytesText(character.raw_bytes)} · ${countText(character.parts, "part")}</b>`;
    if (!html.includes(row)) problems.push(`the size by character should say ${row}`);
  }
  const groups = [...html.matchAll(/<div class="row"><span>[^<]+<\/span><b>[^<]* · (\d[\d,]*) parts?<\/b>/g)];
  const inGroups = groups.slice(characters.length).reduce((total, match) => total + Number(match[1].replaceAll(",", "")), 0);
  if (inGroups < sum("parts")) problems.push(`the sharing groups should hold all ${sum("parts")} parts, they hold ${inGroups}`);
  if (problems.length > 0) throw new Error(problems.join("; "));
}
