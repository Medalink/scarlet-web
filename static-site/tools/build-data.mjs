// Regenerates assets/format-data.js from Scarlet's own docs.
//   node tools/build-data.mjs <path to a scarlet checkout>
// Reads docs/FORMAT.md (every event code) and docs/api-map.json (the capture map).
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const repo = process.argv[2];
if (!repo) throw new Error("usage: node tools/build-data.mjs <path to the Scarlet repo>");
const here = dirname(fileURLToPath(import.meta.url));

const GROUPS = [
  ["Sessions and time", "S ES TP AFK RS RX XD GA GR LL LK LG SS PR RU"],
  ["Movement and travel", "Z SZ P W T TN TF MO WP SM HB CP EX VG AI MP ZP MT TE"],
  ["Combat", "F C CH CI K NS A CC DM DS DP TW AR SO"],
  ["Deaths", "D R U DL DR RZ SH SKN SR DB"],
  ["Experience, stats and talents", "X L XR RO ST SC SX CD SK SL TL TG TA TQ TV TD LS SB AB TB TC"],
  ["Quests and NPCs", "Q QI QP QS QA QD QR QW QY QK NI NW GS GO GC PC IR SE SU NP"],
  ["Loot and items", "O LO LT LD LM RL RW BR IQ I II IT G EN WE IU DI BG LP EQ BC"],
  ["Gold and trade", "M VB VS RE AU AV ML TR BK TK CU"],
  ["Imported history", "YG YS YB YM"],
  ["Crafting and professions", "CR CB CS CW TS RD RT PG PS PU PO CO LR EC RA"],
  ["Groups and people", "GP GM GL GI RC LF IV PM AL FR FC GU GH CT WD EM DU DW DQ CE CM RM"],
  ["Dungeons and raids", "E B IL IB DF TM"],
  ["PvP", "PK PV HK HN HL PB BQ BM BS PP"],
  ["Collections and appearance", "NC CL CA CX SP PT PD SG BT TX TO TI BA"],
  ["Reputation, achievements and Legacy", "RP RF RG RV RR AC AD AT AP AE CK LV LB LA LN"],
  ["Client and interface", "WS UE AO AX AF TT UW TU CV"],
  ["Names for ids", "SN IN N QN KN WN AN FN CN GN BN TY"],
];

// Codes with several rows in FORMAT.md (start and end, or several kinds) get one sentence that covers them.
const MEANING = {
  T: "A flight path started or ended, with where and how long it took",
  F: "Combat started or ended, with how long it lasted",
  E: "A boss encounter started or ended, with whether it was a kill",
  Q: "A quest was accepted, turned in, or left your log without a turn-in",
  A: "You gained an aura or one ended, with how long it lasted",
  CP: "A camp visit started or ended",
  GM: "A player joined or left your group, with the time you were grouped together",
  TS: "A profession window opened or closed",
  CR: "You crafted an item",
  NW: "An NPC or object window opened or closed, with how long it was open",
  GC: "A gossip confirmation with a cost appeared or was cancelled",
  PC: "A player choice popup was offered, answered or closed",
  TR: "A trade window opened or closed, with what each side gave",
  BM: "A PvP match: you entered it, it started, it ended and you left",
  MO: "You mounted or dismounted, with how long you rode",
  WP: "You set or cleared a map waypoint",
  SM: "A summon was offered, accepted or declined",
  TM: "A countdown the client started, ended or cancelled",
  IB: "The countdown that removes you from an instance started or stopped",
  SE: "A scenario started, reached a stage, changed a criterion, or was left or completed",
  BT: "A pet battle began or ended, or one of your pets gained experience or a level in it",
  GL: "A group member's state, the group leader or the kind of group changed",
  RC: "A ready check started, a member answered or it ended",
  LF: "The group finder: listings, searches, roles and what the client lets you use",
  CV: "A cinematic or movie started or ended, and whether you skipped it",
  AO: "The loaded addon list and client language, compared with what this character last wrote",
  TT: "Your support ticket changed or you sent a report",
  TU: "A tutorial hint was triggered, or you left the tutorial area",
  RM: "You set a raid marker on a unit, or placed or removed a world marker",
  AR: "Auto repeat started or ended, with how long it ran",
  IL: "You entered or left an instance you are saved to",
  FC: "The sizes of your friends and ignore lists, as counts only",
  CM: "Communities and the community finder: joins, leaves, invitations and roles",
  TK: "The token's market price changed, or a buy or sell of it finished",
  FR: "A friend or ignored player was added or removed, or a friend came online or went offline",
  DQ: "Duel details: a duel to the death requested, a duel finished, or you left the boundary",
  GI: "A party invite reached you, or you took a group action such as inviting or leaving",
  GU: "Your guild membership, rank or a guild invite changed",
  CE: "Calendar activity as counts: invites waiting, an alarm, an event opened",
  EQ: "An equipment set was found, created, changed, deleted or swapped to",
  TQ: "A respec was offered, or the client reset talents or changed the spec",
  CL: "A collection's total changed: mounts, pets, toys, heirlooms, appearances and outfits",
  UW: "A world state widget changed: zone progress, battleground scores, capture bars",
  RA: "An archaeology dig: a survey, a find, a finished site or a solved artifact",
  BQ: "A battleground queue slot changed: queued, ready, entered or ended",
  SB: "The spellbook: everything it lists, then spells added or removed",
  RV: "Major faction renown: a baseline, a level change or a faction unlocked",
  AU: "An auction: posted, bid, bought, cancelled, sold, expired or outbid",
  ML: "Mail: sent, money taken, attachments taken or returned",
  BK: "Bank money deposited or withdrawn, or bank tabs bought",
  TO: "A transmog outfit was shown, created or saved as a custom set",
  CA: "One item added to a collection, with what the client says about it",
  CX: "A pet or companion left the collection",
  SU: "What you are super-tracking: a quest, your waypoint or other content",
  BA: "A barber shop change was applied, with its price and choices",
  LB: "Your Legacy points balance: earned, available and spent per tree",
  PR: "Whether each client API Scarlet uses exists in this build, once per build and day",
};

// FORMAT.md sentences that carry field codes or run long, shortened for the site.
Object.assign(MEANING, {
  S: "A session started, at login or reload",
  ES: "A session ended, at logout or reload",
  TP: "Time played, whenever the client reports it",
  RS: "Your resting state changed: inn, city or camp",
  XD: "Experience gain was turned off or on for the character",
  GA: "The client's active game mode",
  GR: "The Forever experience preset and hardcore flag at login",
  LK: "A logout countdown started, was cancelled, or the client quit",
  LG: "Combat logging was turned off or on",
  SS: "You took a screenshot. Scarlet takes none and never reads one",
  Z: "The area changed: zone, subzone and instance type",
  SZ: "The first time you entered a subzone, with the map and where you stood",
  P: "A path point while moving: map, x, y and the way you face",
  W: "A stretch of movement ended: running, walking, mounted, swimming or flying, with yards, seconds and jumps",
  TN: "A flight point seen on a flight master's map",
  VG: "A rare, a treasure or an event pin came into range, or was first seen dead",
  MP: "The minimap tracking types you have switched on",
  MT: "A breath, fatigue or feign death timer started or ended",
  C: "You cast a spell, with its cast time",
  CH: "A channel you started ended, such as fishing",
  K: "A mob died to your group, and whether you landed the killing blow",
  NS: "A creature you saw, with its level, type and where you stood",
  CC: "You lost control: a stun, fear, root or silence",
  DM: "Your damage, healing and damage taken totals after a fight",
  DS: "One of your top 5 spells for damage or healing in a fight",
  SO: "The client lit a spell's action button for a proc, or put the glow out",
  DL: "Where you died, as map coordinates",
  DR: "One of the last 3 hits before you died",
  X: "Experience gained, with its source: a kill, a quest or a discovery",
  ST: "A character snapshot at login and each level: money, attributes, armor and health",
  SX: "More character numbers: defense, penetration, weapon damage and item level",
  CD: "Your character's sex, class, race and effective level",
  SL: "A skill line changed: a weapon, defense, a profession or a language",
  TA: "A talent node's rank changed",
  AB: "An action bar slot changed, with what it held before",
  TB: "You paid a trainer to teach a spell or recipe",
  TC: "One service on a trainer's list, with its price and requirements",
  QI: "A quest's kind: level, frequency, group size and time allowed",
  QS: "A quest became complete, stopped being complete or failed",
  QW: "A quest was added to or removed from the tracker",
  QY: "A quest session with your party was created, joined or left",
  NP: "A map cell where you met a creature or an object: seen, talked to or looted",
  TE: "A teleport by a spell, an item, a portal or a summon: where you left and where you arrived",
  NI: "You opened an NPC window: vendor, flight master, trainer, bank or quest giver",
  GS: "What a gossip window offered: quests to give, quests to take and options",
  IR: "You opened a book, letter or plaque. Only its title is kept",
  O: "You received an item from loot, a push or a craft",
  LO: "A loot window's source: a creature, an object or an item you opened",
  LD: "Another player's roll on an item in your group",
  LM: "The loot rule changed: method, threshold and master looter",
  RL: "You rolled on loot: pass, need or greed",
  RW: "A loot roll was offered to you, or you won or lost one",
  IQ: "An item's quality, from poor to legendary",
  I: "Bag contents changed, and where: vendor, bank, mail, trade, loot or auction house",
  II: "What the client says an item is: class, slot, item level, sell price and stack size",
  IT: "The tooltip text of one of Forever's own items",
  G: "An equipment slot changed",
  WE: "A temporary weapon enchant such as an oil or poison was applied or ended",
  BG: "Your free and total bag slots",
  M: "Money changed, with the part that moved between your own characters",
  VB: "You bought from a vendor, or bought an item back",
  VS: "Everything a vendor lists, with prices, stack sizes and stock",
  RD: "A recipe's difficulty and what one craft can give",
  RT: "You tracked or untracked a recipe for its reagents",
  PM: "Another player you saw, with class, race, level and guild",
  GH: "The guild's head count",
  CT: "Chat activity as counts per channel, never the text",
  WD: "You said a tracked word. Only the word and the count are kept",
  EM: "You used an emote",
  DF: "The dungeon and raid difficulties you have picked",
  PK: "A player died to your group",
  PB: "Your honor level and war mode at login",
  NC: "A new collectible: a mount, toy, pet, companion or title",
  SP: "The companion pet you have out was summoned or dismissed",
  PT: "Your pet changed",
  PD: "Your pet's level, loyalty, happiness and training points",
  RP: "Reputation changed, with the amount",
  RG: "A paragon track changed its pending reward or its level",
  AD: "The client's details for an achievement you just earned",
  AP: "Your Legacy Challenge totals at login and after each one earned",
  LV: "Your Legacy reward track level, at login and when it changes",
  LN: "A node of a Legacy tree changed its rank: a purchase, a refund or a reset",
  UE: "The client showed a UI error. Its name is kept, not its text",
  AX: "The client restricted what addons can read, which is why some fields are written as ?",
  AF: "The client blocked or forbade an addon's call",
  RO: "Rested XP at logout: how much you had, the level's XP maximum and whether you were resting",
  TV: "A class talent tree's layout, once per tree per session, with a hash over its nodes' fields",
  TD: "One visible node of a class talent tree: its position, type, entries and edges",
  RU: "Every client signal that might name the character's ruleset (Normal, PvP, RP, RP-PvP, Hardcore), read together",
  YG: "Imported history: a gold balance another addon logged (TSM), written by the companion, never by the addon",
  YS: "Imported history: a sale another addon booked, with the amount received after the house's cut",
  YB: "Imported history: a purchase another addon booked, like YS",
  YM: "Imported history: other income or expense another addon booked, copper signed",
});

const text = readFileSync(join(repo, "docs", "FORMAT.md"), "utf8");
const table = text.slice(text.indexOf("## Codes"), text.indexOf("`I` lines gain where"));
const first = new Map();
for (const line of table.split(/\r?\n/)) {
  const m = line.match(/^\| `([A-Za-z]+)` \|(.*?)\|(.*)\|\s*$/);
  if (!m || first.has(m[1])) continue;
  const fields = m[2].split(",").map((f) => f.trim().replace(/`/g, "")).filter((f) => f && !/^\d/.test(f));
  const sentence = m[3].trim().replace(/`/g, "").split(/(?<=[a-z\)])[.:] /)[0].replace(/[.:]$/, "").trim();
  first.set(m[1], { fields, sentence });
}

const seen = new Set();
const groups = GROUPS.map(([name, list]) => ({
  name,
  codes: list.split(" ").map((code) => {
    const row = first.get(code);
    if (!row) throw new Error(`group "${name}" lists ${code}, which FORMAT.md does not define`);
    if (seen.has(code)) throw new Error(`${code} is in two groups`);
    seen.add(code);
    return [code, MEANING[code] ?? row.sentence, row.fields.slice(0, 8)];
  }),
}));
const missing = [...first.keys()].filter((c) => !seen.has(c));
if (missing.length) throw new Error(`no group for: ${missing.join(" ")}`);

const map = JSON.parse(readFileSync(join(repo, "docs", "api-map.json"), "utf8"));
const domains = Object.entries(map.summary)
  .filter(([name]) => name !== "Interface and other")
  .map(([name, s]) => ({ name, live: s.live, recorded: s.liveTracked, leftOut: s.skipped, documented: s.points }))
  .sort((a, b) => b.live - a.live);
for (const d of domains) {
  if (d.recorded !== d.live - d.leftOut) throw new Error(`${d.name}: ${d.recorded} recorded of ${d.live - d.leftOut} worth recording`);
}
const sum = (k) => domains.reduce((n, d) => n + d[k], 0);
const capture = {
  build: map.build,
  documented: sum("documented"),
  live: sum("live"),
  leftOut: sum("leftOut"),
  recorded: sum("recorded"),
  domains,
};

const out = `// Generated by tools/build-data.mjs from docs/FORMAT.md and docs/api-map.json. Do not edit.
window.SCARLET_FORMAT = ${JSON.stringify({ count: seen.size, groups })};
window.SCARLET_CAPTURE = ${JSON.stringify(capture)};
`;
writeFileSync(join(here, "..", "assets", "format-data.js"), out);
console.log(`${seen.size} codes in ${groups.length} groups; ${capture.recorded} of ${capture.live - capture.leftOut} data points recorded (build ${capture.build})`);
if (process.argv.includes("--print")) for (const g of groups) for (const [c, m] of g.codes) console.log(c.padEnd(4), m);
