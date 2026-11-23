import crypto from "node:crypto";
import { ECHO, Jar, NAME, SHOWN, WARRIOR, expectError, expectJson, expectStatus, sleep, uncached } from "../support.mjs";
import { ERROR_PAGE, heading, linkToAccount, signIn } from "./pages.mjs";

const SPEC_WAIT_MS = 20_000;
const SPEC_POLL_MS = 250;
const SPEC_TOTAL = 87;

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
  expectStatus(await ctx.client.featsSeen(NAME, { "not-a-feat": 1, slayer: 1 }), 204, `${SEEN} with a key that is not a feat, which is ignored`);
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
    wrong.push(`expected ${SPEC_TOTAL} feats listed (67 general, 20 spec, no gold feats, no Unbroken), saw ${feats.length}`);
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

/** The feat score is written by the feat refresh job after the decode, so the board is read until it lists the fixtures. */
export async function featScoreBoardListsTheFixtures(ctx) {
  const route = "/api/v1/public/leaderboards/feat-score";
  const deadline = Date.now() + SPEC_WAIT_MS;
  for (;;) {
    const rows = expectJson(expectStatus(await ctx.http.api(uncached(route, crypto.randomInt(20, 101))), 200, `GET ${route}`), route).data ?? [];
    const listed = rows.filter((row) => [NAME, ECHO, WARRIOR].includes(row.name));
    const ordered = rows.every((row, i) => i === 0 || rows[i - 1].value >= row.value);
    const inRange = rows.every((row) => Number.isInteger(row.value) && row.value >= 1 && row.value <= 340);
    if (listed.length === 3 && ordered && inRange) return;
    if (Date.now() > deadline) {
      throw new Error(`${route} should list ${NAME}, ${ECHO} and ${WARRIOR} by feat score, highest first, each from 1 to 340 points, ${SPEC_WAIT_MS / 1000}s after the decode, saw ${JSON.stringify(rows.slice(0, 6))}`);
    }
    await sleep(SPEC_POLL_MS);
  }
}

const CARD_NUMBERS = ["value", "progress"];
const CARD_GROUPS = ["pinned", "top", "earned", "unearned"];

function cardFaults(card, catalog) {
  const faults = [];
  const at = `card ${card.key}`;
  const entry = catalog.get(card.key);
  if (!entry) return [`${at}: not in /public/feats`];
  for (const field of ["family", "collection", "counted", "track", "icon", "detail"]) {
    if (card[field] === undefined || card[field] === null) faults.push(`${at}: ${field} is missing`);
  }
  if (card.family !== entry.family || card.collection !== entry.collection) faults.push(`${at}: family and collection differ from the catalog`);
  for (const field of CARD_NUMBERS) {
    if (typeof card[field] !== "number") faults.push(`${at}: ${field} is not a number`);
  }
  if (JSON.stringify(card.thresholds) !== JSON.stringify(entry.thresholds)) faults.push(`${at}: thresholds ${JSON.stringify(card.thresholds)} differ from the catalog's ${JSON.stringify(entry.thresholds)}`);
  if (card.progress < 0 || card.progress > 1) faults.push(`${at}: progress ${card.progress} is outside 0 to 1`);
  const top = card.tier === 5;
  if (top ? card.next !== null || card.left !== null || card.to_next !== null : card.next !== card.thresholds[card.tier] || card.left !== Math.max(0, card.next - card.value) || typeof card.to_next !== "string") {
    faults.push(`${at}: next ${card.next}, left ${card.left} and to_next ${JSON.stringify(card.to_next)} do not follow from tier ${card.tier} and value ${card.value}`);
  }
  if (!top && !String(card.to_next).endsWith(" to " + ["Uncommon", "Rare", "Epic", "Legendary", "Scarlet"][card.tier])) faults.push(`${at}: to_next ${JSON.stringify(card.to_next)} should name the next tier`);
  if (card.share !== null && typeof card.share !== "number") faults.push(`${at}: share is neither a number nor null`);
  if (card.tier === 0 && card.share !== null) faults.push(`${at}: an unearned feat has a share`);
  if (card.spell !== null && !Number.isInteger(card.spell)) faults.push(`${at}: spell is neither a spell id nor null`);
  if (typeof card.live !== "boolean") faults.push(`${at}: live is not a boolean`);
  return faults;
}

/** The new fields of GET /installs/me/feats: the catalog version, the score, spec and through of a character, and the fields of every card, beside the ones that were there. */
export async function featsAnswerHasTheNewFields(ctx) {
  const published = expectJson(expectStatus(await ctx.http.api("/api/v1/public" + uncached("/feats", 20)), 200, "GET /api/v1/public/feats"), "the catalog").data;
  const catalog = new Map(published.feats.map((feat) => [feat.key, feat]));
  const deadline = Date.now() + SPEC_WAIT_MS;
  for (;;) {
    const answer = expectJson(expectStatus(await ctx.client.feats(), 200, READ), READ);
    const wall = (answer.characters ?? []).find((entry) => entry.name === NAME);
    if (!wall) throw new Error(`${READ}: expected the feats of ${NAME}, saw ${JSON.stringify(answer).slice(0, 300)}`);
    const faults = [];
    if (answer.catalog !== published.catalog) faults.push(`catalog ${answer.catalog} differs from /public/feats ${published.catalog}`);
    if (!Number.isInteger(wall.score) || wall.score < 1 || wall.score > 340) faults.push(`score ${JSON.stringify(wall.score)} should be a whole number from 1 to 340`);
    if (!Number.isInteger(wall.through) || wall.through <= 0) faults.push(`through ${JSON.stringify(wall.through)} should be the start of the newest session`);
    if (wall.spec !== null && typeof wall.spec !== "string") faults.push(`spec ${JSON.stringify(wall.spec)} should be a name or null`);
    const cards = CARD_GROUPS.flatMap((group) => wall[group]);
    if (cards.length === 0) faults.push("no card in any group");
    for (const card of cards) faults.push(...cardFaults(card, catalog));
    if (faults.length === 0) return;
    if (Date.now() > deadline) throw new Error(`${READ} for ${NAME} is wrong in:\n${faults.slice(0, 12).join("\n")}`);
    await sleep(SPEC_POLL_MS);
  }
}

/** GET /api/v1/public/feats and /feats/{key}: the catalog with its shares, and one feat with who holds the most of it and who reached a tier last. */
export async function publicFeatsAreServed(ctx) {
  const list = expectJson(expectStatus(await ctx.http.api("/api/v1/public" + uncached("/feats", 30)), 200, "GET /api/v1/public/feats"), "the catalog").data;
  const bad = list.feats.filter((feat) => !(feat.key && feat.name && feat.family && feat.collection && ["general", "class", "tree", "role"].includes(feat.scope) && feat.thresholds?.length === 5 && (feat.shares === null || feat.shares.length === 5)));
  if (list.catalog !== 2 || list.feats.length !== 241 || bad.length > 0) {
    throw new Error(`/public/feats should be catalog 2 with 241 feats, each with its scope, five thresholds and shares, saw catalog ${list.catalog}, ${list.feats.length} feats and ${bad.length} faulty: ${JSON.stringify(bad[0])}`);
  }
  const wall = (expectJson(expectStatus(await ctx.client.feats(), 200, READ), READ).characters ?? []).find((entry) => entry.name === NAME);
  const key = wall.earned[0].key;
  const route = `/api/v1/public/feats/${key}`;
  const deadline = Date.now() + SPEC_WAIT_MS;
  for (;;) {
    const feat = expectJson(expectStatus(await ctx.http.api(uncached(route, crypto.randomInt(2, 101))), 200, `GET ${route}`), route).data;
    const sorted = feat.top.every((row, i) => i === 0 || feat.top[i - 1].value >= row.value);
    const listed = feat.top.some((row) => row.character === NAME && row.tier >= 1) && feat.recent.some((row) => row.character === NAME && row.tier >= 1 && row.at.endsWith("Z"));
    if (feat.key === key && feat.top.length <= 20 && feat.recent.length <= 10 && sorted && listed) break;
    if (Date.now() > deadline) throw new Error(`${route} should list ${NAME} in top (highest first, 20 at most) and in recent (10 at most), saw ${JSON.stringify({ top: feat.top.slice(0, 3), recent: feat.recent.slice(0, 3) })}`);
    await sleep(SPEC_POLL_MS);
  }
  expectError(await ctx.http.api("/api/v1/public/feats/not-a-feat"), 404, "not_found", "GET /api/v1/public/feats/not-a-feat");
}

/** /feats: a visitor sees the whole catalog with no character, the owner sees one character's tiers on `compare` and `mine`, and `everyone` shows the catalog again. */
export async function featsHubFollowsTheScope(ctx) {
  const visitor = expectStatus(await ctx.http.page("/feats"), 200, "GET /feats as a visitor");
  if (!heading("241 feats, five tiers each").test(visitor.text) || / holds [0-9]+ feats?/.test(visitor.text)) {
    throw new Error(`/feats as a visitor should be the catalog of 241 feats with no character, saw ${visitor.text.slice(0, 400)}`);
  }
  const jar = new Jar();
  await signIn(ctx.http, jar);
  const me = expectJson(expectStatus(await ctx.client.me(), 200, "GET /api/v1/installs/me"), "installs/me");
  if (me.linked !== true) await linkToAccount(ctx.http, jar, ctx.client);
  const first = SHOWN.split(" ")[0];
  const read = async (route) => {
    const answer = await ctx.http.request(route, { jar, headers: { Accept: "text/html" } });
    expectStatus(answer, 200, `GET ${route} as the owner`);
    if (ERROR_PAGE.test(answer.text)) throw new Error(`${route}: the body carries the markers of an error page: ${answer.text.slice(0, 300)}`);
    return answer.text;
  };
  for (const scope of ["compare", "mine"]) {
    const text = await read(`/feats?as=${NAME}&scope=${scope}`);
    if (!new RegExp(`${first} holds [0-9]+ feats?`).test(text) || !text.includes("Closest to the next tier")) {
      throw new Error(`/feats on ${scope} should say how many feats ${first} holds and list the ones closest to the next tier, saw ${text.slice(0, 400)}`);
    }
  }
  const everyone = await read(`/feats?as=${NAME}&scope=everyone`);
  if (!heading("241 feats, five tiers each").test(everyone) || new RegExp(`${first} holds`).test(everyone)) {
    throw new Error("/feats on everyone should show the catalog without the character's head");
  }
  const page = expectStatus(await ctx.http.page("/feats/slayer"), 200, "GET /feats/slayer");
  if (!heading("Slayer").test(page.text) || !page.text.includes("Reached a tier last")) {
    throw new Error(`/feats/slayer should show the feat, its tiers and who reached one last, saw ${page.text.slice(0, 400)}`);
  }
  expectStatus(await ctx.http.page("/feats/not-a-feat"), 404, "GET /feats/not-a-feat");
}

/** The account block of GET /installs/me/feats: every card read against the catalog's account thresholds, and what the app showed is not new again. */
export async function accountCrestsAreServed(ctx) {
  const published = expectJson(expectStatus(await ctx.http.api("/api/v1/public" + uncached("/feats", 21)), 200, "GET /api/v1/public/feats"), "the catalog").data;
  const catalog = new Map(published.feats.map((feat) => [feat.key, feat]));
  const general = published.feats.filter((feat) => feat.account !== "none");
  const answer = expectJson(expectStatus(await ctx.client.feats(), 200, READ), READ);
  const account = answer.account;
  if (!account) throw new Error(`${READ}: expected an account block for the install's characters, saw ${JSON.stringify(answer).slice(0, 200)}`);
  const faults = [];
  if (account.total !== general.length) faults.push(`total ${account.total} should be the ${general.length} feats with an account crest`);
  if (account.cards.length !== account.total) faults.push(`${account.cards.length} cards for a total of ${account.total}`);
  const held = account.cards.filter((card) => card.tier > 0).length;
  if (account.held !== held) faults.push(`held ${account.held} but ${held} cards have a tier`);
  const counted = [1, 2, 3, 4, 5].map((tier) => account.cards.filter((card) => card.tier === tier).length);
  if ([1, 2, 3, 4, 5].some((tier, i) => account.tiers[tier] !== counted[i])) faults.push(`tiers ${JSON.stringify(account.tiers)} differ from the cards ${JSON.stringify(counted)}`);
  for (const card of account.cards) {
    const entry = catalog.get(card.key);
    if (!entry || entry.account === "none") {
      faults.push(`${card.key}: not a feat with an account crest`);
      continue;
    }
    const reached = entry.account_thresholds.filter((at) => card.value >= at).length;
    if (card.tier !== reached) faults.push(`${card.key}: tier ${card.tier} for ${card.value} against ${entry.account_thresholds.join(", ")} is ${reached}`);
    if (JSON.stringify(card.thresholds) !== JSON.stringify(entry.account_thresholds)) faults.push(`${card.key}: thresholds ${JSON.stringify(card.thresholds)} are not the catalog's account thresholds`);
    if (entry.account === "sum" && card.holder !== null) faults.push(`${card.key}: a sum feat has a holder`);
    if (entry.account === "max" && card.value > 0 && typeof card.holder !== "string") faults.push(`${card.key}: a max feat with a count has no holder`);
    if (card.progress < 0 || card.progress > 1) faults.push(`${card.key}: progress ${card.progress}`);
  }
  if (account.new.length !== held) faults.push(`${account.new.length} new account feats for ${held} held on a fresh install`);
  if (faults.length > 0) throw new Error(`${READ} account block is wrong in:\n${faults.slice(0, 12).join("\n")}`);

  const shown = account.cards.find((card) => card.tier > 0);
  expectStatus(await ctx.client.request("POST", "/api/v1/installs/me/feats/seen", { body: JSON.stringify({ account: true, feats: { [shown.key]: shown.tier, "not-a-feat": 1 } }) }), 204, `${SEEN} for the account`);
  const after = expectJson(expectStatus(await ctx.client.feats(), 200, READ), READ).account;
  if (after.new.some((item) => item.key === shown.key) || after.new.length !== held - 1) throw new Error(`${READ} after ${shown.key} was shown for the account: expected ${held - 1} new and not ${shown.key}, saw ${JSON.stringify(after.new).slice(0, 200)}`);
  expectError(await ctx.client.request("POST", "/api/v1/installs/me/feats/seen", { body: JSON.stringify({ feats: { slayer: 1 } }) }), 422, "invalid_request", `${SEEN} with neither a character nor the account`);
  expectError(await ctx.client.request("POST", "/api/v1/installs/me/feats/seen", { body: JSON.stringify({ account: true, character: NAME, feats: { slayer: 1 } }) }), 422, "invalid_request", `${SEEN} with a character and the account`);
}
