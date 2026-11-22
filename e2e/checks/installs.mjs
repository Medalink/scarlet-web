import { InstallClient, xorDigest } from "../client.mjs";
import { ECHO, Jar, expectJson, expectStatus, loadFixture } from "../support.mjs";
import { linkToAccount, signIn } from "./pages.mjs";
import { decoded } from "./upload.mjs";

const SECOND_LABEL = "scarlet-web e2e second pc install";

async function secondPc(ctx) {
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const second = new InstallClient(ctx.base, SECOND_LABEL, ctx.http.fetch);
  const registered = await second.register();
  if (registered.status !== 201 && registered.status !== 200) expectStatus(registered, 201, "POST /api/v1/installs for the second PC");
  for (const app of [ctx.client, second]) {
    const me = expectJson(expectStatus(await app.me(), 200, "GET /api/v1/installs/me"), "installs/me");
    if (me.linked !== true) await linkToAccount(ctx.http, jar, app);
  }
  return second;
}

async function manifest(app, fixture, parts, what) {
  const answer = expectStatus(await app.manifest(fixture.name, fixture.groups, parts), 200, what);
  return expectJson(answer, what);
}

async function sync(app, fixture, ids, what) {
  const answer = expectStatus(await app.sync(fixture.name, fixture.groups, ids.length, xorDigest(ids)), 200, what);
  return expectJson(answer, what);
}

async function heldParts(ctx, fixture, what) {
  const status = expectJson(expectStatus(await ctx.client.status(), 200, "GET /api/v1/upload/status"), "the status");
  const seen = (status.characters ?? []).find((character) => character.name === fixture.name);
  if (!seen) throw new Error(`${what}: the status lists no ${fixture.name}`);
  return seen.parts;
}

function expectAnswer(body, expected, what) {
  for (const [key, value] of Object.entries(expected)) {
    if (JSON.stringify(body[key]) !== JSON.stringify(value)) throw new Error(`${what}: expected ${key} ${JSON.stringify(value)}, saw ${JSON.stringify(body)}`);
  }
}

/**
 * One character played on two PCs of one account. The second PC lists only the first session, which the first PC
 * also holds: that sync removes nothing of the first PC's, each PC's own sync still matches, and a part only the first PC
 * lists goes away when the first PC drops it.
 */
export async function twoInstallsKeepEachOthersParts(ctx) {
  const fixture = loadFixture(ECHO);
  const total = fixture.ids.length;
  const firstSession = fixture.manifest.parts[0].session;
  const shared = fixture.manifest.parts.filter((part) => part.session === firstSession);
  const only = fixture.manifest.parts.filter((part) => part.session !== firstSession);
  if (shared.length === 0 || only.length < 2) throw new Error(`${ECHO} needs two sessions with parts for this check`);
  const sharedIds = shared.map((part) => part.id);
  const second = await secondPc(ctx);

  expectAnswer(await manifest(second, fixture, shared, "the second PC's manifest"), { need: [], present: shared.length, removed: 0 }, "the second PC lists one session");
  const kept = await heldParts(ctx, fixture, "after the second PC listed one session");
  if (kept !== total) throw new Error(`the second PC's sync should leave all ${total} parts of ${ECHO}, the status says ${kept}`);

  expectAnswer(await sync(second, fixture, sharedIds, "the second PC's sync"), { match: true, count: shared.length }, "the second PC's sync matches what it listed");
  expectAnswer(await sync(ctx.client, fixture, fixture.ids, "the first PC's sync"), { match: true, count: total }, "the first PC's sync still matches all its parts");

  expectAnswer(await manifest(second, fixture, shared.slice(1), "the second PC's shorter manifest"), { need: [], removed: 0 }, "the second PC drops a part the first PC lists");
  const afterDrop = await heldParts(ctx, fixture, "after the second PC dropped a part");
  if (afterDrop !== total) throw new Error(`a part the first PC lists should stay when the second PC drops it: ${total} parts expected, the status says ${afterDrop}`);

  const lost = only[only.length - 1];
  const without = fixture.manifest.parts.filter((part) => part.id !== lost.id);
  expectAnswer(await manifest(ctx.client, fixture, without, "the first PC's shorter manifest"), { need: [], removed: 1 }, "the first PC drops a part nobody else lists");
  const afterLoss = await heldParts(ctx, fixture, "after the first PC dropped its own part");
  if (afterLoss !== total - 1) throw new Error(`a part only the first PC listed should go when it drops it: ${total - 1} parts expected, the status says ${afterLoss}`);

  expectAnswer(await manifest(ctx.client, fixture, fixture.manifest.parts, "the first PC's full manifest"), { need: [lost.id], removed: 0 }, "the first PC lists the part again");
  const stored = expectJson(expectStatus(await ctx.client.parts([fixture.parts.get(lost.id)]), 200, "the part sent again"), "the part sent again");
  if (stored.created !== 1) throw new Error(`the part sent again should be created once, saw ${JSON.stringify(stored)}`);
  await decoded(ctx);
}
