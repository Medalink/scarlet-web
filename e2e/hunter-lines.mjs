/**
 * The session of the fixture character Pack-Warden (e2e/fixture/Pack-Warden): six hours of a hunter that plays levels
 * 50, 51 and 52 for two hours each, with the lines every module of the Hunter class page reads (docs/ROWS.md, "The
 * class tables"): arrows bought, crafted, looted, mailed, traded, fired by Auto Shot and sold, pets of four families
 * and another, their happiness, the aspects, the shots, the traps, Feign Death, Mend Pet, tracking, the meters, three
 * deaths, and the talents Bestial Wrath, Intimidation and Aimed Shot. Each line is `[milliseconds since the session
 * began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page must show are worked out from
 * these lines by e2e/hunter-expected.mjs.
 */

import { GAP, HOUR, Track, nameLines, sessionStart, talentLines } from "./lines-kit.mjs";

const AUTO_SHOT = 75;
const ARCANE = 3044;
const MULTI = 2643;
const SERPENT = 1978;
const CONCUSSIVE = 5116;
const AIMED = 19434;
const MARK = 1130;
const FREEZING = 1499;
const IMMOLATION = 13795;
const EXPLOSIVE = 13812;
const FEIGN = 5384;
const MEND = 136;
const TRANQUILIZING = 19801;
const TAME = 1515;
const FEED = 6991;
const REVIVE = 982;
const BESTIAL = 19574;
const INTIMIDATION = 19577;
const HAWK = 13165;
const MONKEY = 13163;
const PACK = 13159;
const TRUESHOT = 19506;
const TRACK_BEASTS = 1494;
const TRACK_HUMANOIDS = 19883;

const SHARP = 2515;
const RAZOR = 3030;
const JAGGED = 11285;

const TREE = 1003;
const BEAST_MASTERY = 361;
const SURVIVAL = 362;
const MARKSMANSHIP = 363;

const SESSION_END = 6 * HOUR;
const seconds = (from, to) => Number(((to - from) / 1000).toFixed(3));

const cast = (k, spell, cast_seconds = "") => k.at(0, "C", spell, cast_seconds);
const happiness = (name, mood, why) => ["PD", name, 50, 0, 100, mood, "Faithful", 0, 0, why];

function fight(k, script) {
  k.wait(GAP);
  k.wait(3);
  const opened = k.t;
  k.at(0, "F", 1);
  k.wait(1);
  k.at(0, "AR", 1, AUTO_SHOT);
  const shooting = k.t;
  k.wait(1);
  k.at(0, "I", script.ammo, -script.shots, "");
  k.wait(2);
  for (const step of script.steps) {
    k.wait(1.5);
    cast(k, step);
    if (step === MEND) {
      k.wait(4);
      k.at(0, "CH", MEND, 4.0);
    }
  }
  const npc = k.npc();
  if (script.kill === "party") k.at(1.0, "K", npc, 0);
  if (script.kill !== "party") k.at(1.0, "K", npc, 1);
  k.at(0.2, "AR", 0, AUTO_SHOT, seconds(shooting, k.t + 200));
  k.at(0.8, "F", 0, seconds(opened, k.t + 800));
  const length = seconds(opened, k.t);
  k.at(1.5, "DM", "d", script.dealt, Number((script.dealt / length).toFixed(1)), length);
  k.at(0.2, "DM", "t", script.taken, Number((script.taken / length).toFixed(1)), length);
  script.after?.(k);
}

const fightOf = (ammo, shots, steps, spec) => ({ ammo, shots, steps, dealt: 3600, taken: 1400, ...spec });

const CLUSTERS = [
  {
    start: 60_000,
    fights: [
      fightOf(SHARP, 24, [MARK, SERPENT, ARCANE, AIMED], { dealt: 3900 }),
      fightOf(SHARP, 18, [MULTI, ARCANE, FEIGN], { taken: 1900 }),
      fightOf(SHARP, 30, [MARK, FREEZING, CONCUSSIVE, ARCANE], { dealt: 4300 }),
      fightOf(SHARP, 12, [SERPENT, MULTI, TRANQUILIZING], { taken: 1200 }),
      fightOf(SHARP, 26, [MARK, ARCANE, ARCANE, AIMED, IMMOLATION], { dealt: 4600, kill: "party" }),
      fightOf(SHARP, 20, [BESTIAL, MULTI, MEND, ARCANE], { dealt: 5200, taken: 2100 }),
    ],
    after: (k) => {
      k.wait(30);
      k.at(0, "D");
      k.at(2, "U");
    },
  },
  {
    start: 7_260_000,
    fights: [
      fightOf(RAZOR, 28, [MARK, SERPENT, ARCANE, EXPLOSIVE], { dealt: 5400, taken: 2300 }),
      fightOf(RAZOR, 16, [INTIMIDATION, MULTI, FEIGN], { dealt: 4200 }),
      fightOf(RAZOR, 34, [MARK, FREEZING, ARCANE, AIMED], { dealt: 6100 }),
      fightOf(RAZOR, 22, [BESTIAL, SERPENT, ARCANE, MEND], { dealt: 5800, taken: 2600 }),
      fightOf(RAZOR, 14, [CONCUSSIVE, MULTI, FREEZING], { taken: 1700, kill: "party" }),
      fightOf(RAZOR, 25, [MARK, ARCANE, AIMED, SERPENT], { dealt: 6400, taken: 2000 }),
    ],
    after: (k) => {
      k.wait(30);
      k.at(0, "D");
      k.at(2, "U");
    },
  },
  {
    start: 14_460_000,
    fights: [
      fightOf(JAGGED, 30, [MARK, SERPENT, ARCANE, AIMED], { dealt: 7200 }),
      fightOf(JAGGED, 18, [INTIMIDATION, MULTI, EXPLOSIVE], { dealt: 6300, taken: 1800 }),
      fightOf(JAGGED, 36, [MARK, ARCANE, AIMED, FREEZING], { dealt: 7900 }),
      fightOf(JAGGED, 20, [BESTIAL, SERPENT, MULTI, IMMOLATION], { dealt: 7400, taken: 2300 }),
      fightOf(JAGGED, 24, [MARK, ARCANE, MULTI, TRANQUILIZING], { dealt: 6900 }),
      fightOf(JAGGED, 28, [SERPENT, ARCANE, AIMED], { dealt: 7600, taken: 2200 }),
    ],
    after: (k) => {
      k.wait(20);
      cast(k, FEIGN);
      k.at(3, "D");
      k.at(2, "U");
    },
  },
];

function cluster(plan) {
  const k = new Track(plan.start);
  plan.fights.forEach((script) => fight(k, script));
  plan.after(k);
  return k.lines;
}

const aura = (id, from, to) => [
  [from, "A", 1, id, 1, 0, 1, ""],
  [to, "A", 0, id, seconds(from, to)],
];

const AURAS = [
  ...aura(HAWK, 5_000, 1.7 * HOUR),
  ...aura(MONKEY, 1.7 * HOUR, 2 * HOUR),
  ...aura(HAWK, 2 * HOUR, 3.4 * HOUR),
  ...aura(PACK, 3.4 * HOUR, 3.7 * HOUR),
  ...aura(HAWK, 3.7 * HOUR, 5.6 * HOUR),
  ...aura(TRUESHOT, 1 * HOUR, 5.5 * HOUR),
];

const PETS = [
  [10_000, "Ashfang", "Cat", 3],
  [1.8 * HOUR, "Duskrum", "Wolf", 3],
  [2.3 * HOUR, "Briarhorn", "Boar", 3],
  [2.6 * HOUR, "Nightperch", "Owl", 3],
  [5.2 * HOUR, "Rex", "Raptor", 2],
  [5.7 * HOUR, "", "", 0],
];

const MOODS = [
  [1.0 * HOUR, "Ashfang", 2],
  [1.2 * HOUR, "Ashfang", 3],
  [3.0 * HOUR, "Nightperch", 2],
  [3.3 * HOUR, "Nightperch", 3],
];

const pets = () => [
  ...PETS.flatMap(([at, name, family, mood]) => [[at, "PT", name, family], ...(name === "" ? [] : [[at + 500, ...happiness(name, mood, "c")]])]),
  ...MOODS.map(([at, name, mood]) => [at, ...happiness(name, mood, "c")]),
];

const TRACKING = [
  [3_000, "MP", String(TRACK_BEASTS), "s"],
  [1 * HOUR, "MP", String(TRACK_HUMANOIDS), "c"],
  [3 * HOUR, "MP", "Find Minerals", "c"],
  [3.5 * HOUR, "MP", String(TRACK_BEASTS), "c"],
  [5 * HOUR, "MP", "", "c"],
];

const bag = (at, item, delta, where) => [at, "I", item, delta, where];

const AMMO = [
  bag(30_000, SHARP, 1000, "v"),
  bag(40_000, SHARP, 60, "l"),
  bag(1.9 * HOUR, SHARP, -100, "v"),
  bag(7_200_000, RAZOR, 600, "v"),
  bag(7_220_000, RAZOR, 400, "m"),
  bag(7_240_000, RAZOR, 100, "t"),
  bag(14_400_000, JAGGED, 200, "c"),
  bag(14_410_000, JAGGED, 150, "l"),
];

const SPELL_NAMES = [
  [AUTO_SHOT, "Auto Shot"], [ARCANE, "Arcane Shot"], [MULTI, "Multi-Shot"], [SERPENT, "Serpent Sting"], [CONCUSSIVE, "Concussive Shot"],
  [AIMED, "Aimed Shot"], [MARK, "Hunter's Mark"], [FREEZING, "Freezing Trap"], [IMMOLATION, "Immolation Trap"], [EXPLOSIVE, "Explosive Trap"],
  [FEIGN, "Feign Death"], [MEND, "Mend Pet"], [TRANQUILIZING, "Tranquilizing Shot"], [TAME, "Tame Beast"], [FEED, "Feed Pet"],
  [REVIVE, "Revive Pet"], [BESTIAL, "Bestial Wrath"], [INTIMIDATION, "Intimidation"], [HAWK, "Aspect of the Hawk"], [MONKEY, "Aspect of the Monkey"],
  [PACK, "Aspect of the Pack"], [TRUESHOT, "Trueshot Aura"], [TRACK_BEASTS, "Track Beasts"], [TRACK_HUMANOIDS, "Track Humanoids"],
];

const ITEM_NAMES = [[SHARP, "Sharp Arrow"], [RAZOR, "Razor Arrow"], [JAGGED, "Jagged Arrow"]];

const ITEM_INFOS = [[SHARP, 6, 2, "INVTYPE_AMMO"], [RAZOR, 6, 2, "INVTYPE_AMMO"], [JAGGED, 6, 2, "INVTYPE_AMMO"]];

const OUT_OF_FIGHT = [
  [90_000, "C", TAME, 10],
  [95_000, "C", FEED, ""],
  [7_300_000, "C", FEED, ""],
  [7_310_000, "C", REVIVE, 10],
  [14_500_000, "C", TAME, 10],
  [14_501_000, "C", FEED, ""],
  [14_503_000, "C", FEED, ""],
];

const talents = () => talentLines({
  tree: TREE,
  classId: 3,
  hash: "3e5b72a1",
  spec: "Beast Mastery",
  tabs: [[BEAST_MASTERY, "Beast Mastery"], [MARKSMANSHIP, "Marksmanship"], [SURVIVAL, "Survival"]],
  nodes: [
    { node: 3001, spell: BESTIAL, ranks: 1, held: 1, tab: BEAST_MASTERY },
    { node: 3002, spell: INTIMIDATION, ranks: 1, held: 1, tab: BEAST_MASTERY },
    { node: 3003, spell: AIMED, ranks: 1, held: 1, tab: MARKSMANSHIP },
  ],
  spent: [31, 20, 0],
});

const lines = [
  ...sessionStart("HUNTER", "Dwarf", 50, nameLines({ spells: SPELL_NAMES, items: ITEM_NAMES, infos: ITEM_INFOS })),
  ...talents(),
  ...AURAS,
  ...pets(),
  ...TRACKING,
  ...AMMO,
  ...OUT_OF_FIGHT,
  ...CLUSTERS.flatMap(cluster),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];

export const HUNTER_LINES = lines.map(([at, ...rest]) => [Math.round(at), ...rest]);
