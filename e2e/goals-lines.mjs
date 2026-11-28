import { sessionStart } from "./lines-kit.mjs";

const LINEN_CLOTH = 2589;
const WOOL_CLOTH = 2592;
const HERB_NODE = 1617;
const QUEST = 101;

const JEWELCRAFTING = 755;
const INSCRIPTION = 773;
const SWORDS = 43;
const LANGUAGE_COMMON = 98;
const PLATE_MAIL = 293;

const SKILL_NAMES = [
  [JEWELCRAFTING, "Jewelcrafting"],
  [INSCRIPTION, "Inscription"],
  [SWORDS, "Swords"],
  [LANGUAGE_COMMON, "Language: Common"],
  [PLATE_MAIL, "Plate Mail"],
];

const EXALTED = 8;
const REVERED = 7;
const HONORED = 6;
const FRIENDLY = 5;
const NEUTRAL = 4;
const UNFRIENDLY = 3;
const HOSTILE = 2;

/** `[factionID, name, reaction, standing, floor, ceiling]`: nine factions, two Exalted and two below Neutral. */
export const FACTIONS = [
  [72, "Stormwind", EXALTED, 42000, 42000, 42000],
  [47, "Ironforge", EXALTED, 42000, 42000, 42000],
  [69, "Darnassus", HONORED, 14000, 9000, 21000],
  [54, "Gnomeregan Exiles", HONORED, 12000, 9000, 21000],
  [930, "Exodar", FRIENDLY, 7000, 3000, 9000],
  [529, "Argent Dawn", NEUTRAL, 1000, 0, 3000],
  [21, "Booty Bay", UNFRIENDLY, -4500, -6000, -3000],
  [87, "Bloodsail Buccaneers", HOSTILE, -5500, -6000, -3000],
  [577, "Everlook", REVERED, 30000, 21000, 42000],
];

const EVERLOOK_BEFORE = [577, "Everlook", HONORED, 15_000, 9000, 21_000];

const read = (at, [id, , reaction, standing, floor, ceiling], why) => [at, "RF", id, reaction, standing, floor, ceiling, 0, 0, why];

const names = () => [
  ...SKILL_NAMES.map(([id, name], n) => [200 + n, "KN", id, name, 0]),
  ...FACTIONS.map(([id, name], n) => [300 + n, "FN", id, name]),
  [400, "IN", LINEN_CLOTH, "Linen Cloth"],
  [401, "IN", WOOL_CLOTH, "Wool Cloth"],
];

const spread = (count, from, to, make) => Array.from({ length: count }, (_, n) => make(Math.round(from + ((to - from) * n) / count), n));

function session({ level, minutes, kills, xp, quests, gathers, items, balance, extra = [] }) {
  const end = minutes * 60_000;
  const bar = level === 24 ? 31_700 : 34_000;
  return [
    ...sessionStart("WARRIOR", "Human", level),
    ...spread(kills, 20_000, end - 20_000, (at, n) => [at, "K", 990000 + n, 1]),
    ...spread(xp.length, 30_000, end - 30_000, (at, n) => [at, "X", xp[n], xp.slice(0, n + 1).reduce((a, b) => a + b, 0), bar, "k", ""]),
    ...spread(quests, 40_000, end - 40_000, (at) => [at, "Q", 2, QUEST, 0, 0]),
    ...spread(gathers, 50_000, end - 50_000, (at) => [at, "LO", "g", HERB_NODE, 1, `${LINEN_CLOTH}x1`, 0]),
    ...items.map(([id, count], n) => [60_000 + n * 1000, "O", id, count]),
    [70_000, "M", 40_000, "", balance - 60_000],
    [end - 70_000, "M", 60_000, "", balance],
    ...extra,
    [end, "ES"],
  ];
}

/** A first session at level 24 that reaches 25, with the skills, the reputation baseline and the most items, gathers and experience in a session. */
const FIRST = session({
  level: 24,
  minutes: 30,
  kills: 60,
  xp: [500, 500, 500, 500, 500, 500],
  quests: 3,
  gathers: 4,
  items: [[LINEN_CLOTH, 4], [LINEN_CLOTH, 4], [LINEN_CLOTH, 4], [WOOL_CLOTH, 2]],
  balance: 150_000,
  extra: [
    ...names(),
    [500, "SL", JEWELCRAFTING, 120, 300, 0, "s"],
    [510, "SL", INSCRIPTION, 300, 300, 0, "s"],
    [520, "SL", SWORDS, 100, 225, 0, "s"],
    [530, "SL", LANGUAGE_COMMON, 300, 300, 0, "s"],
    [540, "SL", PLATE_MAIL, 1, 1, 0, "s"],
    [900_000, "SL", JEWELCRAFTING, 150, 300, 0, ""],
    ...FACTIONS.map((faction, n) => read(600 + n, faction[0] === EVERLOOK_BEFORE[0] ? EVERLOOK_BEFORE : faction, "s")),
    [1_000_000, "L", 25],
  ],
});

/** A second session at level 25 with the best rates and most quests, and Everlook rising from Honored to Revered. */
const SECOND = session({
  level: 25,
  minutes: 20,
  kills: 48,
  xp: [600, 600, 600, 600],
  quests: 5,
  gathers: 2,
  items: [[WOOL_CLOTH, 9], [LINEN_CLOTH, 1]],
  balance: 240_000,
  extra: [...names(), read(600_000, FACTIONS[8], "")],
});

/** A ten minute session, under the 900 seconds a record needs, whose every figure would beat the other two. */
const SHORT = session({
  level: 25,
  minutes: 10,
  kills: 500,
  xp: [5000, 5000, 5000, 5000],
  quests: 20,
  gathers: 10,
  items: [[LINEN_CLOTH, 900]],
  balance: 400_000,
  extra: names(),
});

export const GOALS_SESSIONS = [
  { session: 1794800000, lines: FIRST },
  { session: 1794900000, lines: SECOND },
  { session: 1795000000, lines: SHORT },
];
