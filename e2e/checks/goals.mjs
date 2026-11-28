import { InstallClient, xorDigest } from "../client.mjs";
import { GOALS, expectJson, expectStatus, loadFixture, sleep } from "../support.mjs";
import { holdsWithNoDigitBeside, textOf } from "./professions.mjs";

const GOALS_LABEL = "scarlet-web e2e goals install";
const DECODE_WAIT_MS = 30_000;
const DECODE_POLL_MS = 250;
const PAGE = `/c/${GOALS}`;

const LEVEL = ["Level 25 of 60", "To level 26 20,000 of 34,000 XP"];
const SKILLS = ["Professions Jewelcrafting 150 of 300 Inscription 300 of 300 Weapons and defense Swords 100 of 225"];
const SKILLS_HIDDEN = ["Language", "Plate Mail"];
const REPUTATION = [
  "Exalted 2 · Revered 1 · Honored 2 · Friendly 1 · Neutral 1 · Unfriendly 1 · Hostile 1",
  "Everlook Revered Darnassus Honored Gnomeregan Exiles Honored Exodar Friendly Argent Dawn Neutral Booty Bay Unfriendly",
  "Show all 9",
  "Ironforge Exalted Stormwind Exalted Bloodsail Buccaneers Hostile",
];
const RECORDS = [
  "Most XP an hour 7,200",
  "Most kills an hour 144",
  "Most gathers an hour 8",
  "Most XP in a session 3,000",
  "Most kills in a session 60",
  "Most gathers in a session 4",
  "Most of one item 12",
  "Linen Cloth",
  "Most quests in a session 5",
  "Most items looted in a session 14",
  "Longest session 30m",
  "levels 24 to 25",
];
const RECORDS_HIDDEN = ["Most gold an hour", "Highest balance", "Most gold looted", "Most kills in a session 500", "Most XP in a session 20,000", "Most items looted in a session 900", "Longest session 10m"];
const SECTIONS = ["goals-level", "goals-skills", "goals-reputation", "goals-records"];

async function settled(client, fixture, what) {
  const deadline = Date.now() + DECODE_WAIT_MS;
  for (;;) {
    const status = expectJson(expectStatus(await client.status(), 200, "GET /api/v1/upload/status"), "the status");
    const seen = (status.characters ?? []).find((character) => character.name === fixture.name);
    if (seen && seen.sessions === fixture.sessions && seen.sessions_waiting === 0) return;
    if (Date.now() > deadline) throw new Error(`${what}: ${fixture.name} was not decoded within ${DECODE_WAIT_MS / 1000}s, the status says ${JSON.stringify(seen)}`);
    await sleep(DECODE_POLL_MS);
  }
}

async function upload(client, fixture, what) {
  const manifest = expectJson(expectStatus(await client.manifest(fixture.name, fixture.groups, fixture.manifest.parts), 200, `${what}: the manifest`), `${what}: the manifest`);
  const need = manifest.need ?? [];
  if (need.length > 0) expectStatus(await client.parts(need.map((id) => fixture.parts.get(id))), 200, `${what}: the parts`);
  const sync = expectJson(expectStatus(await client.sync(fixture.name, fixture.groups, fixture.ids.length, xorDigest(fixture.ids)), 200, `${what}: the sync`), `${what}: the sync`);
  if (sync.match !== true) throw new Error(`${what}: the sync of ${fixture.name} should match, saw ${JSON.stringify(sync)}`);
  await settled(client, fixture, what);
}

async function goalsPage(ctx) {
  const page = expectStatus(await ctx.http.page(PAGE), 200, `GET ${PAGE}`);
  const start = page.text.indexOf('id="goals"');
  return { html: page.text, goals: start < 0 ? "" : textOf(page.text.slice(start, page.text.indexOf("</section>", start))) };
}

function expectShown(goals, phrases, what) {
  const missing = phrases.filter((phrase) => !holdsWithNoDigitBeside(goals, phrase));
  if (missing.length > 0) throw new Error(`${PAGE} ${what}: expected ${missing.map((phrase) => JSON.stringify(phrase)).join(", ")}, saw ${goals.slice(0, 1600)}`);
}

function expectHidden(goals, phrases, what) {
  const shown = phrases.filter((phrase) => goals.includes(phrase));
  if (shown.length > 0) throw new Error(`${PAGE} ${what}: expected none of ${shown.map((phrase) => JSON.stringify(phrase)).join(", ")}`);
}

function expectSections(html, wanted, what) {
  const seen = SECTIONS.filter((id) => html.includes(`id="${id}"`));
  if (JSON.stringify(seen) !== JSON.stringify(wanted)) throw new Error(`${PAGE} ${what}: expected the sections ${wanted.join(", ") || "none"} in this order, saw ${seen.join(", ") || "none"}`);
  const at = wanted.map((id) => html.indexOf(`id="${id}"`));
  if (at.some((place, index) => index > 0 && place < at[index - 1])) throw new Error(`${PAGE} ${what}: the sections are out of order`);
}

/**
 * Vale-Reader of a third install has skills, reputations and three sessions, one of them under 900 seconds. Its page shows the
 * Level, Skills, Reputation and Records of the app's Character Overview, in that order, from the lines; the app's rules hold
 * (no language, no skill with a ceiling of 1, the tier summary, six bars and the rest behind "Show all", no record from the short
 * session, no gold for a visitor). Removing the collections group takes the Reputation away, removing professions the Skills, and
 * putting each group back and sending its parts again brings the section back.
 */
export async function goalsPageShowsSkillsReputationAndRecords(ctx) {
  const client = new InstallClient(ctx.base, GOALS_LABEL, ctx.http.fetch);
  const registered = await client.register();
  if (registered.status !== 201 && registered.status !== 200) expectStatus(registered, 201, "POST /api/v1/installs for the goals install");
  const fixture = loadFixture(GOALS);
  try {
    await upload(client, fixture, "the first upload");

    const whole = await goalsPage(ctx);
    expectSections(whole.html, SECTIONS, "with every group sent");
    expectShown(whole.goals, [...LEVEL, ...SKILLS, ...REPUTATION, ...RECORDS], "with every group sent");
    expectHidden(whole.goals, [...SKILLS_HIDDEN, ...RECORDS_HIDDEN], "with every group sent");

    for (const [group, gone, kept] of [
      ["collections", "goals-reputation", ["goals-level", "goals-skills", "goals-records"]],
      ["professions", "goals-skills", ["goals-level", "goals-reputation", "goals-records"]],
    ]) {
      expectStatus(await client.request("DELETE", `/api/v1/upload/groups/${group}`), 200, `DELETE groups/${group}`);
      const removed = await goalsPage(ctx);
      expectSections(removed.html, kept, `with ${group} removed (${gone} should go)`);
      expectStatus(await client.request("PUT", `/api/v1/upload/groups/${group}`), 200, `PUT groups/${group}`);
      await upload(client, fixture, `${group} sent again`);
      expectSections((await goalsPage(ctx)).html, SECTIONS, `with ${group} sent again`);
    }

    expectStatus(await client.request("DELETE", "/api/v1/upload/groups/xp"), 200, "DELETE groups/xp");
    const noBar = await goalsPage(ctx);
    expectShown(noBar.goals, [LEVEL[0]], "with xp removed");
    expectHidden(noBar.goals, ["To level"], "with xp removed");
    expectStatus(await client.request("PUT", "/api/v1/upload/groups/xp"), 200, "PUT groups/xp");
    await upload(client, fixture, "xp sent again");

    expectShown((await goalsPage(ctx)).goals, [...LEVEL, ...SKILLS, ...REPUTATION, ...RECORDS], "after the groups came back");
  } finally {
    expectStatus(await client.remove(), 200, "DELETE /api/v1/upload of the goals install");
  }
  const gone = await ctx.http.page(PAGE);
  if (gone.status !== 404) throw new Error(`${PAGE} should be a 404 page once the goals install is removed, saw ${gone.status}`);
}
