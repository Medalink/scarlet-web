import { Jar, NAME, SHOWN, expectJson, expectStatus, sleep } from "../support.mjs";
import { browserFor } from "./owner.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const SWITCH_MS = 8000;
const SWITCH = `input[aria-label="Show sessions for ${SHOWN}"]`;

async function firstSession(ctx) {
  const data = expectJson(expectStatus(await ctx.http.api(`/api/v1/public/characters/${NAME}/sessions`), 200, `GET /api/v1/public/characters/${NAME}/sessions`), "the sessions answer").data;
  const oldest = data.sessions.at(-1);
  if (!oldest) throw new Error(`${NAME} has no session in its answer`);
  return oldest.start;
}

export async function sessionPageShowsTheUpload(ctx) {
  const start = await firstSession(ctx);
  const route = `/c/${NAME}/sessions/${start}`;
  const page = expectStatus(await ctx.http.page(route), 200, `GET ${route}`);
  if (ERROR_PAGE.test(page.text) || !heading("Level 5 to 6 in Elwynn Forest").test(page.text)) throw new Error(`${route} should be headed "Level 5 to 6 in Elwynn Forest", saw ${page.text.slice(0, 300)}`);
  for (const phrase of ["1,605", "Defias Thug", "Stonetusk Boar", "Linen Cloth", "Reached level 6", "Experience every five minutes", "Where the time went", "data-route=", "data-strip=", NAME.replace("-", " ")]) {
    if (!page.text.includes(phrase)) throw new Error(`${route} should carry ${JSON.stringify(phrase)}`);
  }
  for (const phrase of ["Against ", "Best of the last 30 sessions", "Gold"]) {
    if (page.text.includes(phrase)) throw new Error(`${route} should not carry ${JSON.stringify(phrase)}: the character has too few earlier sessions and keeps its economy private`);
  }
  for (const missing of [`/c/${NAME}/sessions/${start + 1}`, `/c/${NAME}/sessions/abc`, `/c/Nobody-Here/sessions/${start}`]) {
    const answer = await ctx.http.page(missing);
    if (answer.status !== 404) throw new Error(`${missing} should answer 404, saw ${answer.status}`);
  }
}

/** The owner turns the session pages off on /account: a visitor gets a 404, the owner keeps the page, and turning them on again opens it. */
export async function sessionSwitchHidesThePage(ctx) {
  const browser = await browserFor(ctx);
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);

  const route = `/c/${NAME}/sessions/${await firstSession(ctx)}`;
  const tab = await browser.open(`${ctx.base}/account/privacy`, { width: 1280, height: 800, cookies: jar.entries() });
  const visitorSees = async (status) => {
    for (const deadline = Date.now() + SWITCH_MS; Date.now() < deadline; await sleep(250)) {
      if ((await ctx.http.page(route)).status === status) return;
    }
    throw new Error(`${route} never answered a visitor ${status}`);
  };
  const setSessions = async (on) => {
    await tab.until(`document.querySelector('${SWITCH}') !== null`, SWITCH_MS, `the privacy page has no "Show sessions" switch for ${SHOWN} (${tab.problems.join(" | ") || "no page errors"})`);
    await tab.evaluate(`(() => { const box = document.querySelector('${SWITCH}'); if (box.checked !== ${on}) box.click(); })()`);
    await visitorSees(on ? 200 : 404);
  };
  try {
    await setSessions(false);
    const own = await ctx.http.request(route, { jar, headers: { Accept: "text/html" } });
    if (own.status !== 200) throw new Error(`${route} answered its owner ${own.status} with the switch off`);
    expectStatus(await ctx.http.page(`/c/${NAME}`), 200, `/c/${NAME} with the session pages off`);
  } finally {
    await setSessions(true);
    await tab.close();
  }
}
