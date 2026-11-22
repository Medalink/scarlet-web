import crypto from "node:crypto";
import { NAME, expectError, expectJson, expectStatus, sleep, uncached } from "../support.mjs";

const SPEC_WAIT_MS = 20_000;
const SPEC_POLL_MS = 250;
const SPEC_TOTAL = 95;

const READ = "GET /api/v1/installs/me/feats";
const SEEN = "POST /api/v1/installs/me/feats/seen";

function wallOf(answer) {
  const walls = expectJson(expectStatus(answer, 200, READ), READ).characters ?? [];
  const wall = walls.find((entry) => entry.name === NAME);
  if (!wall) throw new Error(`${READ}: expected the feats of ${NAME}, saw ${answer.text.slice(0, 300)}`);
  return wall;
}

const keysOf = (feats) => feats.map((feat) => feat.key).join(", ");

/** The install has shown nothing yet, so every feat the fixture earned is new; the one reported as shown is not new on the next read. */
export async function shownFeatIsNotNewAgain(ctx) {
  const first = wallOf(await ctx.client.feats());
  const fresh = first.new ?? [];
  if (fresh.length < 2 || fresh.length !== first.held || fresh.some((feat) => feat.from !== 0 || feat.from_name !== null || feat.tier < 1)) {
    throw new Error(`${READ}: expected all ${first.held} earned feats of ${NAME} as new and never shown, saw ${JSON.stringify(fresh).slice(0, 300)}`);
  }

  const [shown, ...rest] = fresh;
  expectStatus(await ctx.client.featsSeen(NAME, { [shown.key]: shown.tier }), 204, SEEN);

  const second = wallOf(await ctx.client.feats());
  if (keysOf(second.new ?? []) !== keysOf(rest) || second.held !== first.held) {
    throw new Error(`${READ} after ${shown.key} was shown: expected ${first.held} feats held and ${keysOf(rest)} new, saw ${second.held} held and ${keysOf(second.new ?? [])} new`);
  }
}

export async function featsRefuseWhatIsNotTheInstalls(ctx) {
  expectError(await ctx.client.feats({ sign: false }), 401, "missing_signature", `an unsigned ${READ}`);
  expectError(await ctx.client.featsSeen("Nobody-Here", { slayer: 1 }), 404, "not_found", `${SEEN} for a character the install does not own`);
  expectError(await ctx.client.featsSeen(NAME, { "not-a-feat": 1 }), 422, "invalid_request", `${SEEN} with a key that is not a feat`);
  expectError(await ctx.client.featsSeen(NAME, { slayer: 6 }), 422, "invalid_request", `${SEEN} with a tier above legend`);
}

function specFactsWrong(feats) {
  const wrong = [];
  const valueOf = (key) => feats.find((feat) => feat.key === key)?.value;
  for (const [key, value] of [["cutthroat", 2], ["eviscerator", 1], ["bruiser", 7400], ["onslaught", 5300]]) {
    if (valueOf(key) !== value) wrong.push(`feats.${key}.value: expected ${value}, saw ${JSON.stringify(valueOf(key))}`);
  }
  for (const key of ["lifegiver", "battle-cry"]) {
    if (feats.some((feat) => feat.key === key)) wrong.push(`feats.${key}: a feat the rogue must never see is stored`);
  }
  if (feats.length !== SPEC_TOTAL) {
    wrong.push(`expected ${SPEC_TOTAL} feats listed (73 general, 26 spec, no gold feats, no Unbroken), saw ${feats.length}`);
  }
  return wrong;
}

/** The spec feats of Echo-Witness are written by a job after the decode, so the answer is read until it holds them. */
export async function specFeatsAreCounted(ctx) {
  const deadline = Date.now() + SPEC_WAIT_MS;
  for (;;) {
    const route = "/api/v1/public" + uncached("/characters/Echo-Witness", crypto.randomInt(2, 101));
    const answer = expectStatus(await ctx.http.api(route), 200, `GET ${route}`);
    const wrong = specFactsWrong(expectJson(answer, route).data.feats ?? []);
    if (wrong.length === 0) return;
    if (Date.now() > deadline) {
      throw new Error(`Echo-Witness is served with other spec feats than the fixture holds, ${SPEC_WAIT_MS / 1000}s after the decode:\n${wrong.join("\n")}`);
    }
    await sleep(SPEC_POLL_MS);
  }
}
