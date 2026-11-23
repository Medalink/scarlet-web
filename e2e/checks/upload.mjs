import { xorDigest } from "../client.mjs";
import { ECHO, LEDGER, MAGE, NAME, PROBE, STRANGER, WARLOCK, expectError, expectJson, expectStatus, loadFixture, rollupsRefreshed, sleep } from "../support.mjs";

const DECODE_WAIT_MS = 20_000;
const DECODE_POLL_MS = 250;
const ZERO_DIGEST = "0".repeat(64);

export async function healthy(ctx) {
  expectStatus(await ctx.http.request("/up"), 200, "GET /up");
}

export async function register(ctx) {
  for (const client of [ctx.client, ctx.stranger]) {
    const answer = await client.register();
    if (answer.status !== 201 && answer.status !== 200) expectStatus(answer, 201, "POST /api/v1/installs");
    if (typeof expectJson(answer, "POST /api/v1/installs").install?.id !== "string") {
      throw new Error(`POST /api/v1/installs: no install id in: ${answer.text.slice(0, 300)}`);
    }
  }
}

export async function meSigned(ctx) {
  const answer = expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me");
  const me = expectJson(answer, "GET /api/v1/installs/me");
  if (me.install?.id !== ctx.client.installId || typeof me.linked !== "boolean") {
    throw new Error(`GET /api/v1/installs/me: expected this install and a linked flag, saw ${answer.text.slice(0, 300)}`);
  }
}

export async function meUnsigned(ctx) {
  expectError(await ctx.client.me({ sign: false }), 401, "missing_signature", "an unsigned GET /api/v1/installs/me");
}

export async function meWrongSignature(ctx) {
  expectError(await ctx.client.me({ corrupt: true }), 401, "bad_signature", "a wrongly signed GET /api/v1/installs/me");
}

export async function leftoversRemoved(ctx) {
  expectStatus(await ctx.client.remove(), 200, "DELETE /api/v1/upload");
  expectStatus(await ctx.stranger.remove(), 200, "DELETE /api/v1/upload of the stranger install");
}

export function uploads(name, install = "client") {
  return async (ctx) => {
    const fixture = loadFixture(name);
    const client = ctx[install];
    const count = fixture.ids.length;
    const digest = xorDigest(fixture.ids);

    const first = expectJson(expectStatus(await client.sync(name, fixture.groups, count, ZERO_DIGEST), 200, "the first sync"), "the first sync");
    if (first.match !== false || first.count !== 0) {
      throw new Error(`the first sync of ${name} should say match: false, count: 0, saw ${JSON.stringify(first)}`);
    }

    const manifest = expectStatus(await client.manifest(name, fixture.groups, fixture.manifest.parts), 200, "the manifest");
    const need = expectJson(manifest, "the manifest").need ?? [];
    if (need.length !== count || !fixture.ids.every((id, at) => need[at] === id)) {
      throw new Error(`the manifest of ${name} should need all ${count} parts in order, saw ${manifest.text.slice(0, 300)}`);
    }

    const parts = expectStatus(await client.parts(need.map((id) => fixture.parts.get(id))), 200, "the parts request");
    const stored = expectJson(parts, "the parts request");
    if (stored.created !== count || (stored.stored ?? []).length !== count || !need.every((id) => stored.stored.includes(id))) {
      throw new Error(`the parts request should store all ${count} ids and create ${count}, saw ${parts.text.slice(0, 300)}`);
    }

    const last = expectJson(expectStatus(await client.sync(name, fixture.groups, count, digest), 200, "the last sync"), "the last sync");
    if (last.match !== true || last.count !== count || last.digest !== digest) {
      throw new Error(`the last sync of ${name} should say match: true, count: ${count}, saw ${JSON.stringify(last)}`);
    }
  };
}

export async function bothUpload(ctx) {
  await Promise.all([uploads(NAME)(ctx), uploads(PROBE)(ctx), uploads(ECHO)(ctx), uploads(WARLOCK)(ctx), uploads(STRANGER, "stranger")(ctx), uploads(LEDGER, "stranger")(ctx), uploads(MAGE, "stranger")(ctx)]);
}

export async function sameManifestNeedsNothing(ctx) {
  const fixture = loadFixture(NAME);
  const reversed = [...fixture.manifest.parts].reverse();
  const answer = expectStatus(await ctx.client.manifest(NAME, fixture.groups, reversed), 200, "the repeated manifest");
  const body = expectJson(answer, "the repeated manifest");
  if (body.need?.length !== 0 || body.present !== fixture.ids.length) {
    throw new Error(`the same manifest should need nothing and find ${fixture.ids.length} present, saw ${answer.text.slice(0, 300)}`);
  }
}

export async function decoded(ctx) {
  const wanted = [[loadFixture(NAME), "client"], [loadFixture(PROBE), "client"], [loadFixture(ECHO), "client"], [loadFixture(WARLOCK), "client"], [loadFixture(STRANGER), "stranger"], [loadFixture(LEDGER), "stranger"], [loadFixture(MAGE), "stranger"]];
  const deadline = Date.now() + DECODE_WAIT_MS;
  for (;;) {
    const answers = {};
    for (const install of ["client", "stranger"]) {
      const answer = expectStatus(await ctx[install].status(), 200, "GET /api/v1/upload/status");
      answers[install] = { answer, characters: expectJson(answer, "GET /api/v1/upload/status").characters ?? [] };
    }
    const done = wanted.every(([fixture, install]) => {
      const seen = answers[install].characters.find((character) => character.name === fixture.name);
      return seen && seen.parts === fixture.ids.length && seen.sessions === fixture.sessions && seen.sessions_waiting === 0;
    });
    if (done) {
      await rollupsRefreshed(ctx);
      return;
    }
    if (Date.now() > deadline) {
      throw new Error(`the upload was not decoded within ${DECODE_WAIT_MS / 1000}s (is Horizon running?), the statuses say: ${Object.values(answers).map(({ answer }) => answer.text.slice(0, 300)).join(" and ")}`);
    }
    await sleep(DECODE_POLL_MS);
  }
}
