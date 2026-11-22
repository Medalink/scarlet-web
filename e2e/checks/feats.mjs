import { NAME, expectError, expectJson, expectStatus } from "../support.mjs";

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
