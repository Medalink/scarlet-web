/**
 * Checks before a ship: the Scarlet website and API driven over HTTP, and a headless browser for the pages, in under
 * 30 seconds. It uploads three invented characters (e2e/fixture), reads them back through the API, the pages and the
 * browser, and removes them again.
 *
 *   node e2e/run.mjs [--url http://127.0.0.1:8557] [--only upload|api|pages|browser|owner] [--keep <dir>] [--browser <path>]
 *
 * Without --url it reads E2E_URL. Only a local address is accepted. The upload lane always runs first, because the
 * other lanes read what it uploaded. The owner lane runs after them, because it links the uploading install to an
 * account, and the removal always runs last. Exit 0 when every check passes, 1 when one
 * fails, 2 on a usage error.
 */

import os from "node:os";
import path from "node:path";
import { InstallClient } from "./client.mjs";
import { Gate } from "./gate.mjs";
import { ECHO, Http, Jar, LOCAL_HOSTS, NAME, PROBE, STRANGER, Skip } from "./support.mjs";
import * as upload from "./checks/upload.mjs";
import * as api from "./checks/api.mjs";
import * as pages from "./checks/pages.mjs";
import * as professions from "./checks/professions.mjs";
import * as browser from "./checks/browser.mjs";
import * as feats from "./checks/feats.mjs";
import * as owner from "./checks/owner.mjs";
import * as own from "./checks/own.mjs";
import * as installs from "./checks/installs.mjs";
import * as removal from "./checks/removal.mjs";

const LANES = ["upload", "api", "pages", "browser", "owner"];
const SERVER_CONNECTIONS = 3;
const INSTALL_LABEL = "scarlet-web e2e install";
const STRANGER_LABEL = "scarlet-web e2e stranger install";

const REGISTRY = [
  { lane: "upload", name: "/up answers 200", run: upload.healthy },
  { lane: "upload", name: "an install registers", run: upload.register },
  { lane: "upload", name: "installs/me answers a signed request", run: upload.meSigned },
  { lane: "upload", name: "installs/me refuses an unsigned request", run: upload.meUnsigned },
  { lane: "upload", name: "installs/me refuses a wrong signature", run: upload.meWrongSignature },
  { lane: "upload", name: "what an earlier run left is removed", run: upload.leftoversRemoved },
  { lane: "upload", name: `${NAME}, ${PROBE} and ${ECHO} upload side by side, and ${STRANGER} from another install: sync, manifest, parts, sync`, run: upload.bothUpload },
  { lane: "upload", name: "the same manifest needs nothing", run: upload.sameManifestNeedsNothing },
  { lane: "upload", name: "the upload is decoded", run: upload.decoded },
  { lane: "api", name: "every public endpoint answers data and meta", run: api.everyEndpointAnswers },
  { lane: "api", name: "the uploaded character is served with the fixture's facts", run: api.characterIsServed },
  { lane: "api", name: "the records of the fixture are served", run: api.recordsAreServed },
  { lane: "api", name: "search finds the character", run: api.searchFindsTheCharacter },
  { lane: "api", name: "an unknown character is 404 not_found", run: api.unknownCharacterIs404 },
  { lane: "api", name: "a bad parameter is 422 invalid_request", run: api.badParameterIs422 },
  { lane: "api", name: "the app reads a character's new feats, and one it showed is not new again", run: feats.shownFeatIsNotNewAgain },
  { lane: "api", name: "the feats requests refuse an unsigned read, another character, an unknown feat and a sixth tier", run: feats.featsRefuseWhatIsNotTheInstalls },
  { lane: "api", name: "the six professions with recorded lines are served with their numbers", run: professions.apiShowsTheUpload },
  { lane: "pages", name: "every public page answers and shows its own content", run: pages.everyPageShowsItself },
  { lane: "pages", name: "one death heard by two uploaders is counted once", run: pages.deathIsCountedOnce },
  { lane: "pages", name: "a match's scoreboard is shown without the players' names", run: pages.scoreboardIsShownWithoutNames },
  { lane: "pages", name: "a recipe, a spell and a boss encounter show what the uploads recorded", run: pages.recipeSpellAndBossLootAreShown },
  { lane: "pages", name: "the art answers set no cookie", independent: true, run: pages.artAnswersSetNoCookie },
  { lane: "pages", name: "a missing character is a 404 page", independent: true, run: pages.missingPageIs404("/c/Nobody-Here") },
  { lane: "pages", name: "a profession without a page is a 404 page", independent: true, run: pages.missingPageIs404("/professions/lockpicking") },
  { lane: "pages", name: "a missing creature is a 404 page", independent: true, run: pages.missingPageIs404("/creatures/999999999") },
  { lane: "pages", name: "the public profession and camps pages stay public: canonical, indexed, edge cacheable, no cookie, no own-page words", run: pages.publicPagesStayPublic },
  { lane: "pages", name: "the account pages are closed to visitors", independent: true, run: pages.accountIsClosedToVisitors },
  { lane: "pages", name: "the app links to an account", independent: true, run: pages.appLinksToAccount },
  { lane: "pages", name: "the twelve profession pages show what the upload recorded", run: professions.pagesShowTheUpload },
  { lane: "browser", name: "the browser has no window", independent: true, run: browser.hasNoWindow },
  { lane: "browser", name: "the map art is installed", independent: true, run: browser.mapArtIsServed },
  { lane: "browser", name: "the key pages run clean at 1280", run: browser.pagesRunClean },
  { lane: "browser", name: "the narrow pages fit at 390", run: browser.narrowPagesFit },
  { lane: "browser", name: "search suggests while typing", run: browser.searchSuggestsWhileTyping },
  { lane: "owner", name: "the owner sees a toast for a new feat once, and a visitor sees none", run: owner.ownerSeesNewFeatOnce },
  { lane: "owner", name: "the account's own profession pages show its characters and stay private", run: own.ownProfessionPagesShowTheAccount },
  { lane: "owner", name: "the account's own Tailoring page holds nothing of a character from another install, which everyone's page shows", run: own.ownPagesLeaveOutOtherInstalls },
  { lane: "owner", name: "the account's own camps page shows its campfire visit and sits among its thirteen own pages", run: own.ownCampsPageShowsTheAccount },
  { lane: "removal", name: `${ECHO} on two installs of one account: a sync of one keeps the other's parts, and a part only one lists goes when it drops it`, run: installs.twoInstallsKeepEachOthersParts },
  { lane: "removal", name: "removal answers and empties the status", run: removal.removalAnswersAndEmptiesStatus },
  { lane: "removal", name: "the stranger install's upload is removed too", run: removal.strangerIsRemoved },
  { lane: "removal", name: "the character is no longer served", run: removal.characterIsNoLongerServed },
  { lane: "removal", name: "an account with no character sees its profession page in the plain state", run: own.accountWithNoCharacterSeesThePlainState },
  { lane: "removal", name: "an account with no character sees its camps page in the plain state", run: own.accountWithNoCharacterSeesTheCampsPlainState },
];

function usage(message) {
  console.error(`${message}\nusage: node e2e/run.mjs [--url http://127.0.0.1:<port>] [--only ${LANES.join("|")}] [--keep <dir>] [--browser <path>]\n(without --url the lane reads E2E_URL)`);
  process.exit(2);
}

function parseArguments(argv) {
  const options = {};
  for (let at = 0; at < argv.length; at += 2) {
    const flag = argv[at];
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
  keepDir: options.keep ?? path.join(os.tmpdir(), "scarlet-web-e2e-shots"),
  browserPath: options.browser ?? null,
  browser: null,
};

const EOL = "\n";
const indented = (message) => String(message).split(EOL).map((line) => `  ${line}`).join(EOL);
const STAGES = [["upload", "api", "pages", "browser"], ["owner"], ["removal"]];
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
      if (lane !== "upload" && lane !== "removal" && !check.independent) {
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
    const lanes = stage.filter((lane) => lane === "upload" || lane === "removal" || only.includes(lane));
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
