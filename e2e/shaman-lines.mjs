/**
 * The session of the fixture character Totem-Caller (e2e/fixture/Totem-Caller): six hours of a shaman that plays
 * levels 50, 51 and 52 for two hours each, with the lines every module of the Shaman class page reads (docs/ROWS.md,
 * "The class tables"): 24 fights of 30 seconds in which the four totem slots are used in four ways (all four elements,
 * three, two and none), totems that stand their full time, are replaced, are lost early or are still standing when the
 * fight ends, shocks and Stormstrike, Clearcasting and Flurry with the casts made under them, weapon imbues, heals and
 * cures, the Ghost Wolf form and a mount, Astral Recall, four deaths of which three were walked off with Reincarnation
 * and the Ankhs bought and used, and the build Enhancement 20 / 31 / 0. Each line is `[milliseconds since the session
 * began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page must show are in
 * e2e/shaman-expected.mjs, written from the plan below before the page was read.
 */
import { STEP, Track, cast, ms } from "./track.mjs";

const ELWYNN = 1429;
const DEADMINES = 36;
const MOLTEN_CORE = 409;

const SEARING = 2075;
const MAGMA = 8187;
const STONECLAW = 5730;
const STRENGTH = 8075;
const HEALING_STREAM = 5394;
const MANA_SPRING = 5675;
const WINDFURY_TOTEM = 8512;
const GRACE = 8835;
const EARTH_SHOCK = 8042;
const FLAME_SHOCK = 8050;
const FROST_SHOCK = 8056;
const STORMSTRIKE = 17364;
const LIGHTNING_BOLT = 403;
const ROCKBITER = 8017;
const FLAMETONGUE = 8024;
const FROSTBRAND = 8033;
const WINDFURY_WEAPON = 8232;
const HEALING_WAVE = 331;
const CHAIN_HEAL = 1064;
const LESSER_HEALING_WAVE = 8004;
const ANCESTRAL = 2008;
const PURGE = 370;
const CURE_POISON = 526;
const CURE_DISEASE = 528;
const REINCARNATION = 20608;
const ASTRAL_RECALL = 556;
const GHOST_WOLF = 2645;
const BROWN_HORSE = 458;
const CLEARCASTING = 12536;
const FLURRY = 16256;
const MASTERY = 16166;
const SWIFTNESS = 16188;
const MANA_TIDE = 16190;
const ELEMENTAL_FOCUS = 16164;
const ANKH = 17030;

const TREE = 5301;
const ELEMENTAL = 261;
const ENHANCEMENT = 263;
const RESTORATION = 262;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;
const GAP = 4;
const BUFF_SECONDS = 15;

const POOL = { ES: EARTH_SHOCK, FL: FLAME_SHOCK, FR: FROST_SHOCK, SS: STORMSTRIKE };

const ALL = [1, 2, 3, 4];
const NO_WATER = [1, 2, 4];
const FIRE_AIR = [1, 4];
const NONE = [];

function totems(at, slots, air2) {
  if (slots.includes(1)) {
    at(1, "TW", 1, MAGMA, 20);
    if (slots.includes(2)) at(22, "TW", 1, SEARING, 55);
  }
  if (slots.includes(2)) {
    at(2, "TW", 2, STONECLAW, 15);
    at(8, "TW", 2, 0, 0);
    at(10, "TW", 2, STRENGTH, 120);
  }
  if (slots.includes(3)) {
    at(3, "TW", 3, HEALING_STREAM, 60);
    at(12, "TW", 3, MANA_SPRING, 60);
  }
  if (slots.includes(4)) {
    at(4, "TW", 4, WINDFURY_TOTEM, 120);
    if (air2) at(14, "TW", 4, GRACE, 120);
  }
  for (const slot of slots) at(31, "TW", slot, 0, 0);
}

function fight(k, spec, pool, plan, index) {
  k.wait(GAP);
  const t0 = k.t;
  const npc = k.npc();
  const at = (seconds, ...line) => k.lines.push([t0 + ms(seconds), ...line]);
  at(0, "F", 1);
  totems(at, spec, plan.air2);
  if (plan.clearcasting.includes(index)) {
    at(6, "A", 1, CLEARCASTING, 1, BUFF_SECONDS, 1, "");
    if (index === plan.clearcasting[0]) at(8, "C", LIGHTNING_BOLT, "");
    at(21, "A", 0, CLEARCASTING, BUFF_SECONDS);
  }
  if (plan.flurry.includes(index)) {
    at(6, "A", 1, FLURRY, 1, BUFF_SECONDS, 1, "");
    at(8, "C", LIGHTNING_BOLT, "");
    at(10, "C", LIGHTNING_BOLT, "");
    at(21, "A", 0, FLURRY, BUFF_SECONDS);
  }
  pool.forEach((spell, at4) => at(23 + 2 * at4, "C", spell, ""));
  at(29.8, "K", npc, 1);
  at(30, "F", 0, 30);
  if (index === 0) {
    at(30.5, "DM", "i", plan.interrupts, plan.interrupts / 30, 30);
    at(30.6, "DM", "x", plan.dispels, plan.dispels / 30, 30);
  }
  k.t = t0 + ms(32);
}

function spread(items, fights) {
  const lists = Array.from({ length: fights }, () => []);
  items.forEach((item, at) => lists[at % fights].push(item));
  return lists;
}

function tokens(counts) {
  return Object.entries(counts).flatMap(([token, count]) => Array.from({ length: count }, () => POOL[token]));
}

function times(k, spell, count) {
  for (let at = 0; at < count; at += 1) cast(k, spell);
}

function buffed(k, spell, under, filler) {
  cast(k, spell);
  k.at(0.5, "A", 1, spell, 1, BUFF_SECONDS, 1, "");
  const gained = k.t;
  for (let at = 0; at < under; at += 1) k.at(STEP, "C", filler, "");
  k.t = gained + ms(BUFF_SECONDS);
  k.lines.push([k.t, "A", 0, spell, BUFF_SECONDS]);
}

function wolf(k, seconds) {
  cast(k, GHOST_WOLF);
  k.at(0.5, "A", 1, GHOST_WOLF, 1, seconds, 1, "");
  const gained = k.t;
  k.t = gained + ms(seconds);
  k.lines.push([k.t, "A", 0, GHOST_WOLF, seconds]);
}

function ride(k, seconds) {
  k.at(2, "MO", 1, BROWN_HORSE, 1, 0, "");
  k.at(seconds, "MO", 0, BROWN_HORSE, 1, seconds);
}

function prepare(k, plan) {
  if (plan.ankhs > 0) k.at(1, "I", ANKH, plan.ankhs, "v");
  wolf(k, plan.wolf);
  if (plan.ride > 0) ride(k, plan.ride);
  k.wait(GAP);
  times(k, WINDFURY_WEAPON, plan.imbues[0]);
  times(k, FLAMETONGUE, plan.imbues[1]);
  times(k, FROSTBRAND, plan.imbues[2]);
  times(k, ROCKBITER, plan.imbues[3]);
  k.wait(GAP);
  times(k, HEALING_WAVE, plan.heals[0]);
  times(k, CHAIN_HEAL, plan.heals[1]);
  times(k, LESSER_HEALING_WAVE, plan.heals[2]);
  times(k, ANCESTRAL, plan.heals[3]);
  k.wait(GAP);
  times(k, PURGE, plan.cures[0]);
  times(k, CURE_POISON, plan.cures[1]);
  times(k, CURE_DISEASE, plan.cures[2]);
  times(k, ASTRAL_RECALL, plan.recalls);
  times(k, MANA_TIDE, plan.manaTide);
  k.wait(GAP);
  if (plan.mastery) buffed(k, MASTERY, 2, LIGHTNING_BOLT);
  if (plan.swiftness) buffed(k, SWIFTNESS, 1, LIGHTNING_BOLT);
  k.wait(GAP);
}

function cluster(start, plan) {
  const k = new Track(start);
  if (plan.zone) k.lines.push([start, ...plan.zone]);
  k.wait(1);
  prepare(k, plan);
  const pools = spread(tokens(plan.pool), plan.masks.length);
  plan.masks.forEach((mask, at) => fight(k, mask, pools[at], plan, at));
  for (const reincarnated of plan.deaths) {
    k.wait(GAP);
    k.at(4.0, "D");
    if (reincarnated) {
      k.at(1.5, "C", REINCARNATION, "");
      k.at(0.5, "I", ANKH, -1, "");
      k.at(1.5, "R");
    } else {
      k.at(1.5, "U");
    }
  }
  return k.lines;
}

const SPELL_NAMES = [
  [SEARING, "Searing Totem"], [MAGMA, "Magma Totem"], [STONECLAW, "Stoneclaw Totem"], [STRENGTH, "Strength of Earth Totem"],
  [HEALING_STREAM, "Healing Stream Totem"], [MANA_SPRING, "Mana Spring Totem"], [WINDFURY_TOTEM, "Windfury Totem"], [GRACE, "Grace of Air Totem"],
  [EARTH_SHOCK, "Earth Shock"], [FLAME_SHOCK, "Flame Shock"], [FROST_SHOCK, "Frost Shock"], [STORMSTRIKE, "Stormstrike"],
  [LIGHTNING_BOLT, "Lightning Bolt"], [ROCKBITER, "Rockbiter Weapon"], [FLAMETONGUE, "Flametongue Weapon"], [FROSTBRAND, "Frostbrand Weapon"],
  [WINDFURY_WEAPON, "Windfury Weapon"], [HEALING_WAVE, "Healing Wave"], [CHAIN_HEAL, "Chain Heal"], [LESSER_HEALING_WAVE, "Lesser Healing Wave"],
  [ANCESTRAL, "Ancestral Spirit"], [PURGE, "Purge"], [CURE_POISON, "Cure Poison"], [CURE_DISEASE, "Cure Disease"],
  [REINCARNATION, "Reincarnation"], [ASTRAL_RECALL, "Astral Recall"], [GHOST_WOLF, "Ghost Wolf"], [BROWN_HORSE, "Brown Horse"],
  [CLEARCASTING, "Clearcasting"], [FLURRY, "Flurry"], [MASTERY, "Lightning Mastery"], [SWIFTNESS, "Nature's Swiftness"],
  [MANA_TIDE, "Mana Tide Totem"], [ELEMENTAL_FOCUS, "Elemental Focus"],
];

const MASKS = [ALL, ALL, ALL, NO_WATER, NO_WATER, FIRE_AIR, FIRE_AIR, NONE];

const PLANS = [
  {
    start: 60_000,
    zone: null,
    masks: MASKS,
    air2: false,
    pool: { ES: 6, FL: 4, FR: 2, SS: 4 },
    clearcasting: [1, 4],
    flurry: [2, 5],
    interrupts: 2,
    dispels: 3,
    ankhs: 5,
    wolf: 1800,
    ride: 1800,
    imbues: [4, 2, 1, 1],
    heals: [4, 2, 1, 2],
    cures: [2, 1, 0],
    recalls: 1,
    manaTide: 0,
    mastery: false,
    swiftness: false,
    deaths: [true],
  },
  {
    start: 7_260_000,
    zone: ["Z", "", DEADMINES, "party", 1, "The Deadmines", ""],
    masks: MASKS,
    air2: false,
    pool: { ES: 8, FL: 4, FR: 2, SS: 5 },
    clearcasting: [1, 4],
    flurry: [2, 5],
    interrupts: 3,
    dispels: 3,
    ankhs: 0,
    wolf: 1200,
    ride: 0,
    imbues: [4, 2, 1, 0],
    heals: [4, 2, 1, 2],
    cures: [2, 1, 1],
    recalls: 2,
    manaTide: 1,
    mastery: true,
    swiftness: false,
    deaths: [true],
  },
  {
    start: 14_460_000,
    zone: ["Z", "", MOLTEN_CORE, "raid", 1, "Molten Core", ""],
    masks: MASKS,
    air2: true,
    pool: { ES: 10, FL: 4, FR: 2, SS: 6 },
    clearcasting: [1, 4],
    flurry: [2, 5],
    interrupts: 2,
    dispels: 2,
    ankhs: 3,
    wolf: 600,
    ride: 3600,
    imbues: [4, 2, 1, 0],
    heals: [4, 2, 1, 1],
    cures: [2, 1, 0],
    recalls: 1,
    manaTide: 1,
    mastery: false,
    swiftness: true,
    deaths: [true, false],
  },
];

const talentLines = () => [
  [500, "TY", ELEMENTAL, "Elemental"],
  [510, "TY", ENHANCEMENT, "Enhancement"],
  [520, "TY", RESTORATION, "Restoration"],
  [800, "TV", TREE, 4, 3, "b84c12e7"],
  [810, "TD", TREE, 3201, 100, 200, "single", 1, ENHANCEMENT, `4201:${STORMSTRIKE}:1`, ""],
  [820, "TD", TREE, 3202, 300, 200, "single", 1, ELEMENTAL, `4202:${ELEMENTAL_FOCUS}:1`, ""],
  [830, "TD", TREE, 3203, 500, 200, "single", 1, ENHANCEMENT, `4203:${FLURRY}:1`, ""],
  [1500, "TA", 1, 3201, 4201, 1, "", 1, STORMSTRIKE, ENHANCEMENT, "s"],
  [1510, "TA", 1, 3202, 4202, 1, "", 1, ELEMENTAL_FOCUS, ELEMENTAL, "s"],
  [1520, "TA", 1, 3203, 4203, 1, "", 1, FLURRY, ENHANCEMENT, "s"],
  [2000, "TG", "s", 1, 1, "", "Enhancement", TREE, `${ELEMENTAL}:20:0,${ENHANCEMENT}:31:0,${RESTORATION}:0:0`, 0, 0, 1, ""],
];

export const SHAMAN_LINES = [
  [0, "S", "1.15.8", "SHAMAN", "Orc", 50, "Horde", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...SPELL_NAMES.map(([id, name], at) => [100 + at, "SN", id, name]),
  [200, "IN", ANKH, "Ankh"],
  ...talentLines(),
  ...PLANS.flatMap((plan) => cluster(plan.start, plan)),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
