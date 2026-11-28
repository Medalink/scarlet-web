/**
 * Checks before a ship: the Scarlet website and API driven over HTTP, and a headless browser for the pages, in under
 * 30 seconds. It uploads the invented characters of e2e/fixture, reads them back through the API, the pages and the
 * browser, and removes them again.
 *
 *   node e2e/run.mjs [--url http://127.0.0.1:8557] [--only upload|api|pages|browser|owner] [--keep <dir>] [--browser <path>]
 *
 * --leave skips the removal at the end, so a dev instance keeps the uploaded characters to look at.
 * Without --url it reads E2E_URL. Only a local address is accepted. The upload lane always runs first, because the
 * other lanes read what it uploaded. The owner lane runs after them, because it links the uploading install to an
 * account, and the removal always runs last. Exit 0 when every check passes, 1 when one
 * fails, 2 on a usage error.
 */

import os from "node:os";
import path from "node:path";
import { InstallClient } from "./client.mjs";
import { Gate } from "./gate.mjs";
import { CLASS_CHARACTERS, ECHO, Http, INSTALL_CHARACTERS, Jar, LOCAL_HOSTS, STRANGER_CHARACTERS, WARLOCK, Skip } from "./support.mjs";
import * as upload from "./checks/upload.mjs";
import * as api from "./checks/api.mjs";
import * as pages from "./checks/pages.mjs";
import * as classPage from "./checks/class-page.mjs";
import * as professions from "./checks/professions.mjs";
import * as browser from "./checks/browser.mjs";
import * as feats from "./checks/feats.mjs";
import * as classPageApi from "./checks/class-page-api.mjs";
import * as owner from "./checks/owner.mjs";
import * as map from "./checks/map.mjs";
import * as own from "./checks/own.mjs";
import * as installs from "./checks/installs.mjs";
import * as removal from "./checks/removal.mjs";
import * as admin from "./checks/admin.mjs";
import * as session from "./checks/session.mjs";
import * as goals from "./checks/goals.mjs";
import * as talents from "./checks/talents.mjs";
import * as keys from "./checks/keys.mjs";

const LANES = ["upload", "api", "pages", "browser", "owner"];
const SERVER_CONNECTIONS = 3;
const INSTALL_LABEL = "scarlet-web e2e install";
const STRANGER_LABEL = "scarlet-web e2e stranger install";

const REGISTRY = [
  { lane: "keys", name: "an account without Battle.net sees one line that links it, and a key it asks for is refused", run: keys.noBattlenetMakesNoKey },
  { lane: "keys", name: "an account with Battle.net makes keys of 30 days by default, 60 and 90, and the expiry is stored and shown, and 91 days or more is refused", run: keys.expiryChoiceIsStoredAndShown },
  { lane: "upload", name: "/up answers 200", run: upload.healthy },
  { lane: "upload", name: "an install registers", run: upload.register },
  { lane: "upload", name: "installs/me answers a signed request", run: upload.meSigned },
  { lane: "upload", name: "installs/me refuses an unsigned request", run: upload.meUnsigned },
  { lane: "upload", name: "installs/me refuses a wrong signature", run: upload.meWrongSignature },
  { lane: "upload", name: "what an earlier run left is removed", run: upload.leftoversRemoved },
  { lane: "upload", name: `${INSTALL_CHARACTERS.join(", ")} upload side by side, and ${STRANGER_CHARACTERS.join(", ")} from another install: sync, manifest, parts, sync`, run: upload.bothUpload },
  { lane: "upload", name: "the same manifest needs nothing", run: upload.sameManifestNeedsNothing },
  { lane: "upload", name: "the upload is decoded", run: upload.decoded },
  { lane: "api", name: "the public API answers 401 missing_key with no key, 401 invalid_token with a wrong, expired or query-string key", run: keys.publicApiNeedsAKey },
  { lane: "api", name: "every public API answer is private, carries its tag and limit, and a preflight for the Authorization header is answered", run: keys.answersArePrivateAndPreflightIsAnswered },
  { lane: "api", name: "every public endpoint answers data and meta", run: api.everyEndpointAnswers },
  { lane: "api", name: "the uploaded character is served with the fixture's facts", run: api.characterIsServed },
  { lane: "api", name: "the records of the fixture are served", run: api.recordsAreServed },
  { lane: "api", name: "search finds the character", run: api.searchFindsTheCharacter },
  { lane: "api", name: "the session list and a session recap are served with the fixture's figures, in pages, and an unknown session is 404", run: api.sessionEndpointsAreServed },
  { lane: "api", name: "an unknown character is 404 not_found", run: api.unknownCharacterIs404 },
  { lane: "api", name: "a bad parameter is 422 invalid_request", run: api.badParameterIs422 },
  { lane: "api", name: "the app reads a character's new feats, and one it showed is not new again", run: feats.shownFeatIsNotNewAgain },
  { lane: "api", name: "the feats requests refuse an unsigned read, another character, an unknown feat and a sixth tier", run: feats.featsRefuseWhatIsNotTheInstalls },
  { lane: "api", name: `the app reads the class page of ${WARLOCK} as data with the fixture's figures`, run: classPageApi.installReadsItsClassPage },
  { lane: "api", name: "the class page request refuses an unsigned read, another install's character and a name that is not Name-Surname", run: classPageApi.classPageRefusesWhatIsNotTheInstalls },
  { lane: "api", name: "the rogue fixture's class, tree and role feats are counted, and no other class's or role's", run: feats.specFeatsAreCounted },
  { lane: "api", name: "the feat-score board lists the fixtures by tiers held, highest first", run: feats.featScoreBoardListsTheFixtures },
  { lane: "api", name: "the app reads the catalog version, the score, the spec and the newest session, and every card carries its place, count, thresholds, progress, share, spell and live flag", run: feats.featsAnswerHasTheNewFields },
  { lane: "api", name: "the app reads the account crests of its characters against the catalog's account thresholds, and an account feat it showed is not new again", run: feats.accountCrestsAreServed },
  { lane: "api", name: "the public feats are the 241 catalog feats with their shares, and one feat lists its top characters and its latest earners, and an unknown feat is 404", run: feats.publicFeatsAreServed },
  { lane: "api", name: "the six professions with recorded lines are served with their numbers", run: professions.apiShowsTheUpload },
  { lane: "pages", name: "every public page answers and shows its own content", run: pages.everyPageShowsItself },
  { lane: "pages", name: "the addresses that moved answer a permanent redirect with the query kept", run: pages.oldAddressesRedirect },
  { lane: "pages", name: "a match's scoreboard is shown without the players' names", run: pages.scoreboardIsShownWithoutNames },
  { lane: "pages", name: "a recipe, a spell and a boss encounter show what the uploads recorded", run: pages.recipeSpellAndBossLootAreShown },
  { lane: "pages", name: "the art answers set no cookie", independent: true, run: pages.artAnswersSetNoCookie },
  { lane: "pages", name: "a missing character is a 404 page", independent: true, run: pages.missingPageIs404("/c/Nobody-Here") },
  { lane: "pages", name: "a profession without a page is a 404 page", independent: true, run: pages.missingPageIs404("/professions/lockpicking") },
  { lane: "pages", name: "a missing creature is a 404 page", independent: true, run: pages.missingPageIs404("/creatures/999999999") },
  { lane: "pages", name: "an id past what the database stores is a 404 page, not an error", independent: true, run: pages.missingPageIs404("/creatures/2147483648") },
  { lane: "pages", name: "an object id past what the database stores is a 404 page", independent: true, run: pages.missingPageIs404("/objects/3000000000") },
  { lane: "pages", name: "the public profession and camps pages stay public: canonical, indexed, edge cacheable, no cookie, no own-page words", run: pages.publicPagesStayPublic },
  { lane: "pages", name: "the account pages are closed to visitors", independent: true, run: pages.accountIsClosedToVisitors },
  { lane: "pages", name: "the account's own world map and its trails are closed to visitors", independent: true, run: map.ownMapIsClosedToVisitors },
  { lane: "pages", name: `${WARLOCK}'s world map shows its walk, its ride and its flight, and the map on its character page links it`, run: map.characterMapShowsItsTrails },
  { lane: "pages", name: "the admin pages are a 404 to visitors", independent: true, run: admin.adminIsHiddenFromVisitors },
  { lane: "pages", name: "the app links to an account", independent: true, run: pages.appLinksToAccount },
  { lane: "pages", name: "the twelve profession pages show what the upload recorded", run: professions.pagesShowTheUpload },
  ...classPage.PAGES.map((page) => ({ lane: "pages", name: `${CLASS_CHARACTERS[page.slug]}'s class page shows the figures worked out from its lines and no comparison under twenty ${page.slug}s, and its character page links it and shows the band`, run: (ctx) => classPage.pageShowsItsFigures(ctx, page) })),
  { lane: "pages", name: "a character's page links its class page and draws its band from the stored row, and a class with no page has neither", run: classPage.characterPageLinksItsClassPage },
  { lane: "pages", name: "a character with no class rows has no band and no link to a class page", run: classPage.characterWithNoClassRowsHasNoBand },
  { lane: "pages", name: "another class's slug, an unknown slug and an unknown character are 404 pages", run: classPage.otherAddressesAre404 },
  { lane: "pages", name: "a session page shows the recap of its fixture session and answers 404 for a session, a character or a start that is not there", run: session.sessionPageShowsTheUpload },
  { lane: "pages", name: "the talent page of a spec draws each tree in the game layout with the pick rate of every talent, a dimmed 0% for one nobody took and one choice node", run: talents.specDrawsItsTrees },
  { lane: "pages", name: "the talents list leads each spec with its most common tree split", run: talents.talentsListLeadsWithTheSplit },
  { lane: "browser", name: "the browser has no window", independent: true, run: browser.hasNoWindow },
  { lane: "browser", name: "the map art is installed", independent: true, run: browser.mapArtIsServed },
  { lane: "browser", name: "the map page draws density without a key, from /map/density, and sends nothing to the public API", run: keys.mapDensityNeedsNoKey },
  { lane: "browser", name: "the key pages run clean at 1280", run: browser.pagesRunClean },
  { lane: "browser", name: "the narrow pages fit at 390", run: browser.narrowPagesFit },
  { lane: "browser", name: "the download page loads the two app screenshots under its button", run: talents.downloadStripLoads },
  { lane: "browser", name: "every code block of the developer docs has a copy button that copies its text, and the endpoints show example responses", run: talents.copyButtonsCopyTheirBlock },
  { lane: "browser", name: "search suggests while typing", run: browser.searchSuggestsWhileTyping },
  { lane: "browser", name: "every date on a character page and a session page is rewritten into the contract's form with an exact hover text", run: browser.datesReadInTheBrowser },
  { lane: "owner", name: "the owner sees a toast for a new feat once, and a visitor sees none", run: owner.ownerSeesNewFeatOnce },
  { lane: "owner", name: "the admin opens the admin pages privately, /admin/api counts the upload and a character asked for by name, /admin/clients shows the install clients, and /admin/characters finds the characters", run: admin.adminSeesTheApiCalls },
  { lane: "owner", name: "a character the owner made private has no class page, world map or trails for a visitor, and has them again once public", run: classPage.hiddenCharacterHidesItsClassPage },
  { lane: "owner", name: "the owner turns the session pages off and a visitor gets a 404 while the owner keeps the page", run: session.sessionSwitchHidesThePage },
  { lane: "owner", name: "the feats page shows the catalog to a visitor and one character on compare and mine, and a feat has its own page", run: feats.featsHubFollowsTheScope },
  { lane: "owner", name: "the account's own profession pages show its characters and stay private", run: own.ownProfessionPagesShowTheAccount },
  { lane: "owner", name: "the account's own Tailoring page holds nothing of a character from another install, which everyone's page shows", run: own.ownPagesLeaveOutOtherInstalls },
  { lane: "owner", name: "the account's own camps page shows its campfire visit and sits among its thirteen own pages", run: own.ownCampsPageShowsTheAccount },
  { lane: "owner", name: "the account's own world map shows its characters' rides and flights and stays private", run: map.ownMapShowsTheAccount },
  { lane: "owner", name: "a character page shows the Skills, Reputation and Records of the app from the lines uploaded, drops each with its sharing group and shows it again once the group is back", run: goals.goalsPageShowsSkillsReputationAndRecords },
  { lane: "removal", name: `${ECHO} on two installs of one account: a sync of one keeps the other's parts, and a part only one lists goes when it drops it`, run: installs.twoInstallsKeepEachOthersParts },
  { lane: "removal", name: "removal answers and empties the status", run: removal.removalAnswersAndEmptiesStatus },
  { lane: "removal", name: "the stranger install's upload is removed too", run: removal.strangerIsRemoved },
  { lane: "removal", name: "the character is no longer served", run: removal.characterIsNoLongerServed },
  { lane: "removal", name: "the owner of a character sees its Highest balance until the economy group is removed, though the opening balance rows stay stored", run: goals.ownerHighestBalanceFollowsEconomy },
  { lane: "removal", name: "an account with no character sees its profession page in the plain state", run: own.accountWithNoCharacterSeesThePlainState },
  { lane: "removal", name: "an account with no character sees its camps page in the plain state", run: own.accountWithNoCharacterSeesTheCampsPlainState },
  { lane: "removal", name: "the lane's API keys are deleted again", run: keys.keysAreDeleted },
];

function usage(message) {
  console.error(`${message}\nusage: node e2e/run.mjs [--url http://127.0.0.1:<port>] [--only ${LANES.join("|")}] [--keep <dir>] [--browser <path>]\n(without --url the lane reads E2E_URL)`);
  process.exit(2);
}

function parseArguments(argv) {
  const options = {};
  for (let at = 0; at < argv.length; at += 2) {
    const flag = argv[at];
    if (flag === "--leave") {
      options.leave = true;
      at -= 1;
      continue;
    }
    if (!["--url", "--only", "--keep", "--browser"].includes(flag) || argv[at + 1] === undefined) usage(`unknown or incomplete argument ${flag}`);
    options[flag.slice(2)] = argv[at + 1];
  }
  return options;
}

const options = parseArguments(process.argv.slice(2));
const target = options.url ?? process.env.E2E_URL;
if (!target) usage("no server given");
let base;
try {
  base = new URL(target);
} catch {
  usage(`${target} is not a URL`);
}
if (!LOCAL_HOSTS.includes(base.hostname)) {
  console.error(`the lane only runs against a local server: ${base.hostname} is refused, because the lane deletes what it uploads`);
  process.exit(2);
}
const only = options.only ? options.only.split(",") : LANES;
for (const lane of only) if (!LANES.includes(lane)) usage(`unknown lane ${lane}`);

const gate = await Gate.start(base.origin, SERVER_CONNECTIONS);
const http = new Http(gate.origin);
const ctx = {
  http,
  base: gate.origin,
  client: new InstallClient(gate.origin, INSTALL_LABEL, http.fetch),
  stranger: new InstallClient(gate.origin, STRANGER_LABEL, http.fetch),
  jar: new Jar(),
  keyJar: new Jar(),
  keepDir: options.keep ?? path.join(os.tmpdir(), "scarlet-web-e2e-shots"),
  browserPath: options.browser ?? null,
  browser: null,
};

const EOL = "\n";
const indented = (message) => String(message).split(EOL).map((line) => `  ${line}`).join(EOL);
const STAGES = [["keys"], ["upload", "api", "pages", "browser"], ["owner"], ["removal"]];
const uploaded = Promise.withResolvers();
let failed = 0;
let skipped = 0;
let total = 0;

async function runLane(lane) {
  const lines = [];
  for (const check of REGISTRY.filter((entry) => entry.lane === lane)) {
    let at = performance.now();
    const took = () => `${(performance.now() - at).toFixed(0).padStart(5)}ms`;
    total += 1;
    try {
      if (lane !== "keys" && lane !== "upload" && lane !== "removal" && !check.independent) {
        const ready = await uploaded.promise;
        if (!ready) throw new Skip("the upload lane did not finish cleanly");
        at = performance.now();
      }
      await check.run(ctx);
      lines.push(`ok   ${took()}  ${check.lane}  ${check.name}`);
    } catch (error) {
      if (error instanceof Skip) {
        skipped += 1;
        lines.push(`skip ${check.lane} ${check.name}: ${error.message}`);
        continue;
      }
      failed += 1;
      lines.push(`FAIL ${took()}  ${check.lane}  ${check.name}`, indented(error.message));
    }
  }
  if (lane === "upload") uploaded.resolve(!lines.some((line) => line.startsWith("FAIL")));
  return lines;
}

process.on("SIGINT", () => process.exit(130));
process.on("SIGTERM", () => process.exit(143));
const started = performance.now();
try {
  for (const stage of STAGES) {
    const lanes = stage.filter((lane) => lane === "keys" || lane === "upload" || (lane === "removal" && !options.leave) || only.includes(lane));
    for (const lines of await Promise.all(lanes.map(runLane))) console.log(lines.join(EOL));
  }
} finally {
  ctx.browser?.stopNow();
  gate.close();
}
const counts = [...http.counts].map(([limiter, count]) => `${limiter} ${count}`).join(", ");
console.log(`requests by limiter: ${counts}; repeated after a server reset: ${gate.retried}`);
console.log(`e2e: ${total} checks, ${failed} failed, ${skipped} skipped, ${((performance.now() - started) / 1000).toFixed(1)}s`);
process.exit(failed > 0 ? 1 : 0);
