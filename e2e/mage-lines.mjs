/**
 * The session of the fixture character Frost-Weaver (e2e/fixture/Frost-Weaver): six hours of a mage that plays levels
 * 50, 51 and 52 for two hours each, with the lines every module of the Mage class page reads (docs/ROWS.md, "The class
 * tables"): conjured water and food made, drunk, eaten, handed over and destroyed, mana gems, portals and teleports of
 * three cities, Polymorph, escapes, Evocation, Blizzard, fights with their kills, a damage meter, absorbs, dispels and
 * interrupts, one death after an Ice Block, and the talents Ice Barrier, Cold Snap and Ice Block. Presence of Mind,
 * Arcane Power, Pyroblast and Combustion are cast too, so a build that holds them has rows. Each line is
 * `[milliseconds since the session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page
 * must show are in e2e/mage-expected.mjs, written from the plan below before the page was read.
 */

const ELWYNN = 1429;

const CONJURE_WATER = 5504;
const CONJURE_FOOD = 587;
const CONJURE_GEM = 10053;
const DRINK = 430;
const FOOD = 433;
const PORTAL_STORMWIND = 10059;
const PORTAL_IRONFORGE = 11416;
const PORTAL_DARNASSUS = 11419;
const TELEPORT_STORMWIND = 3561;
const TELEPORT_IRONFORGE = 3562;
const TELEPORT_DARNASSUS = 3565;
const POLYMORPH = 118;
const POLYMORPH_PIG = 28272;
const BLINK = 1953;
const FROST_NOVA = 122;
const COUNTERSPELL = 2139;
const ICE_BLOCK = 11958;
const MANA_SHIELD = 1463;
const EVOCATION = 12051;
const BLIZZARD = 10;
const FROSTBOLT_1 = 116;
const FROSTBOLT_2 = 8406;
const FIREBALL = 133;
const FIRE_BLAST = 2136;
const ARCANE_MISSILES = 5143;
const CONE_OF_COLD = 120;
const SCORCH = 2948;
const ARCANE_EXPLOSION = 1449;
const FLAMESTRIKE = 2120;
const PYROBLAST = 11366;
const INTELLECT = 1459;
const BRILLIANCE = 23028;
const REMOVE_CURSE = 475;
const ICE_BARRIER = 11426;
const COLD_SNAP = 12472;
const PRESENCE_OF_MIND = 12043;
const ARCANE_POWER = 12042;
const COMBUSTION = 11129;

const WATER = 5350;
const MUFFIN = 5349;
const GEM = 8007;

const TREE = 1020;
const ARCANE = 301;
const FIRE = 302;
const FROST = 303;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;
const GAP = 4;

const ms = (seconds) => Math.round(seconds * 1000);

class Track {
  lines = [];
  creature = 991100;

  constructor(start) {
    this.t = start;
  }

  at(seconds, ...line) {
    this.t += ms(seconds);
    this.lines.push([this.t, ...line]);
  }

  wait(seconds) {
    this.t += ms(seconds);
  }

  npc() {
    this.creature += 1;
    return this.creature;
  }
}

const cast = (k, spell, seconds = "") => k.at(0, "C", spell, seconds);

const BLOCKS = {
  spell: (id, seconds = 1.5) => (k) => cast(k, id, seconds),
  conjure: (spell, item) => (k) => {
    cast(k, spell, 3.0);
    k.at(0.3, "I", item, 2, "");
  },
  gem: () => (k) => {
    cast(k, CONJURE_GEM, 3.0);
    k.at(0.3, "I", GEM, 1, "");
  },
  gemUsed: () => (k) => k.at(0, "I", GEM, -1, ""),
  drink: () => (k) => {
    cast(k, DRINK);
    k.at(0, "A", 1, DRINK, 1, 18, 1, "");
    k.at(0.4, "I", WATER, -1, "");
    k.at(120, "A", 0, DRINK, 120.0);
  },
  eat: () => (k) => {
    cast(k, FOOD);
    k.at(0.4, "I", MUFFIN, -1, "");
  },
  hand: (item) => (k) => k.at(0, "I", item, -2, "t"),
  destroy: (item, count) => (k) => {
    k.at(0, "DI", item, count);
    k.at(1.0, "I", item, -count, "");
  },
  cut: (id) => (k) => k.at(0, "CI", id, 1.0),
  evocation: () => (k) => {
    cast(k, EVOCATION, "");
    k.at(8.0, "CH", EVOCATION, 8.0);
  },
  blizzard: () => (k) => {
    cast(k, BLIZZARD, "");
    k.at(30.0, "CH", BLIZZARD, 30.0);
  },
  iceBlock: () => (k) => {
    cast(k, ICE_BLOCK, "");
    k.at(0, "A", 1, ICE_BLOCK, 1, 10, 1, "");
    k.at(10.0, "A", 0, ICE_BLOCK, 10.0);
  },
  iceBlockThenDeath: () => (k) => {
    cast(k, ICE_BLOCK, "");
    k.at(0, "A", 1, ICE_BLOCK, 1, 10, 1, "");
    k.at(10.0, "A", 0, ICE_BLOCK, 10.0);
    k.at(3.0, "D");
    k.at(1.5, "R");
    k.wait(12);
  },
  presence: () => (k) => {
    cast(k, PRESENCE_OF_MIND, "");
    k.at(0, "A", 1, PRESENCE_OF_MIND, 1, 15, 1, "");
    k.at(0.5, "C", PYROBLAST, "");
    k.at(0.1, "A", 0, PRESENCE_OF_MIND, 0.6);
  },
  power: () => (k) => {
    cast(k, ARCANE_POWER, "");
    k.at(0, "A", 1, ARCANE_POWER, 1, 15, 1, "");
    k.at(0.5, "C", ARCANE_MISSILES, "");
    k.at(0.5, "C", ARCANE_MISSILES, "");
    k.at(2.0, "A", 0, ARCANE_POWER, 3.0);
  },
};

const repeat = (count, make) => Array.from({ length: count }, (_, at) => make(at));

function interleave(groups) {
  const lists = groups.filter((group) => group.length > 0).map((group) => [...group]);
  const out = [];
  while (lists.some((list) => list.length > 0)) {
    for (const list of lists) if (list.length > 0) out.push(list.shift());
  }
  return out;
}

function run(k, blocks) {
  for (const block of blocks) {
    block(k);
    k.wait(GAP);
  }
}

function fights(k, kills, pool) {
  const queue = [...pool];
  kills.forEach((count, at) => {
    const share = Math.ceil(queue.length / (kills.length - at));
    k.wait(1);
    const opened = k.t;
    k.at(0, "F", 1);
    run(k, queue.splice(0, share));
    for (let kill = 0; kill < count; kill += 1) k.at(0.5, "K", k.npc(), 1);
    k.at(1.0, "F", 0, ((k.t - opened) / 1000).toFixed(1) * 1);
    k.wait(GAP);
  });
}

function reports(k, plan) {
  const [total, spells] = plan.damage;
  k.at(2, "DM", "d", total, (total / 100).toFixed(1) * 1, 100.0);
  for (const [spell, amount] of spells) k.at(0.2, "DS", "d", spell, amount);
  k.at(2, "DM", "a", plan.absorbed, (plan.absorbed / 100).toFixed(1) * 1, 100.0);
  k.at(0.2, "DS", "a", ICE_BARRIER, plan.absorbed);
  k.at(2, "DM", "i", plan.interrupts, 1.0, 100.0);
  k.at(2, "DM", "x", plan.dispels, 1.0, 100.0);
}

function cluster(start, plan) {
  const k = new Track(start);
  if (plan.death) run(k, [BLOCKS.iceBlockThenDeath()]);

  const away = interleave([
    repeat(plan.water, () => BLOCKS.conjure(CONJURE_WATER, WATER)),
    repeat(plan.food, () => BLOCKS.conjure(CONJURE_FOOD, MUFFIN)),
    repeat(plan.drinks, () => BLOCKS.drink()),
    repeat(plan.eats, () => BLOCKS.eat()),
    repeat(plan.handWater, () => BLOCKS.hand(WATER)),
    repeat(plan.handFood, () => BLOCKS.hand(MUFFIN)),
    plan.destroyWater > 0 ? [BLOCKS.destroy(WATER, plan.destroyWater)] : [],
    plan.destroyFood > 0 ? [BLOCKS.destroy(MUFFIN, plan.destroyFood)] : [],
    repeat(plan.gems, () => BLOCKS.gem()),
    repeat(plan.gemsUsed, () => BLOCKS.gemUsed()),
    plan.portals.flatMap(([id, count]) => repeat(count, () => BLOCKS.spell(id, 10.0))),
    plan.teleports.flatMap(([id, count]) => repeat(count, () => BLOCKS.spell(id, 10.0))),
    repeat(plan.portalCut, () => BLOCKS.cut(PORTAL_STORMWIND)),
    repeat(plan.evocation, () => BLOCKS.evocation()),
    repeat(plan.intellect, () => BLOCKS.spell(INTELLECT, 1.5)),
    repeat(plan.brilliance, () => BLOCKS.spell(BRILLIANCE, 1.5)),
    repeat(plan.removeAway, () => BLOCKS.spell(REMOVE_CURSE, 1.5)),
    repeat(plan.sheepAway, () => BLOCKS.spell(POLYMORPH, 1.5)),
    repeat(plan.barrierAway, () => BLOCKS.spell(ICE_BARRIER, "")),
    repeat(plan.snapAway, () => BLOCKS.spell(COLD_SNAP, "")),
  ]);
  run(k, away);

  const pool = interleave([
    repeat(plan.frostbolt, () => BLOCKS.spell(plan.frostboltRank, 2.5)),
    repeat(plan.fireball, () => BLOCKS.spell(FIREBALL, 3.0)),
    repeat(plan.fireBlast, () => BLOCKS.spell(FIRE_BLAST, "")),
    repeat(plan.missiles, () => BLOCKS.spell(ARCANE_MISSILES, 1.0)),
    repeat(plan.cone, () => BLOCKS.spell(CONE_OF_COLD, "")),
    repeat(plan.scorch, () => BLOCKS.spell(SCORCH, 1.5)),
    repeat(plan.explosion, () => BLOCKS.spell(ARCANE_EXPLOSION, 1.5)),
    repeat(plan.blizzard, () => BLOCKS.blizzard()),
    repeat(plan.flamestrike, () => BLOCKS.spell(FLAMESTRIKE, 3.0)),
    repeat(plan.pyroblast, () => BLOCKS.spell(PYROBLAST, 6.0)),
    repeat(plan.presence, () => BLOCKS.presence()),
    repeat(plan.power, () => BLOCKS.power()),
    repeat(plan.combustion, () => BLOCKS.spell(COMBUSTION, "")),
    repeat(plan.sheep, () => BLOCKS.spell(POLYMORPH, 1.5)),
    repeat(plan.pig, () => BLOCKS.spell(POLYMORPH_PIG, 1.5)),
    repeat(plan.sheepCut, () => BLOCKS.cut(POLYMORPH)),
    repeat(plan.blink, () => BLOCKS.spell(BLINK, "")),
    repeat(plan.nova, () => BLOCKS.spell(FROST_NOVA, "")),
    repeat(plan.counter, () => BLOCKS.spell(COUNTERSPELL, "")),
    repeat(plan.shield, () => BLOCKS.spell(MANA_SHIELD, "")),
    repeat(plan.iceBlock, () => BLOCKS.iceBlock()),
    repeat(plan.barrier, () => BLOCKS.spell(ICE_BARRIER, "")),
    repeat(plan.snap, () => BLOCKS.spell(COLD_SNAP, "")),
    repeat(plan.removeFight, () => BLOCKS.spell(REMOVE_CURSE, 1.5)),
  ]);
  fights(k, plan.kills, pool);
  reports(k, plan);
  return k.lines;
}

const PLANS = [
  {
    start: 60_000,
    death: false,
    water: 6, food: 2, drinks: 4, eats: 1, handWater: 1, handFood: 1, destroyWater: 0, destroyFood: 0, gems: 1, gemsUsed: 1,
    portals: [[PORTAL_STORMWIND, 3], [PORTAL_IRONFORGE, 1]], teleports: [[TELEPORT_STORMWIND, 2], [TELEPORT_IRONFORGE, 1]], portalCut: 0,
    evocation: 1, intellect: 2, brilliance: 0, removeAway: 1, removeFight: 0, sheepAway: 1, barrierAway: 1, snapAway: 0,
    frostbolt: 20, frostboltRank: FROSTBOLT_1, fireball: 6, fireBlast: 4, missiles: 1, cone: 3, scorch: 4, explosion: 2, blizzard: 4, flamestrike: 1,
    pyroblast: 1, presence: 1, power: 1, combustion: 1,
    sheep: 3, pig: 1, sheepCut: 0, blink: 2, nova: 3, counter: 1, shield: 2, iceBlock: 1, barrier: 2, snap: 1,
    kills: [1, 1, 3, 5],
    damage: [12_000, [[FROSTBOLT_1, 4000], [BLIZZARD, 2000], [FIREBALL, 3000], [ARCANE_MISSILES, 1000]]], absorbed: 3000, interrupts: 1, dispels: 1,
  },
  {
    start: 7_260_000,
    death: true,
    water: 8, food: 2, drinks: 5, eats: 1, handWater: 0, handFood: 0, destroyWater: 3, destroyFood: 0, gems: 1, gemsUsed: 0,
    portals: [[PORTAL_STORMWIND, 2], [PORTAL_IRONFORGE, 1], [PORTAL_DARNASSUS, 1]], teleports: [[TELEPORT_STORMWIND, 1], [TELEPORT_DARNASSUS, 1]], portalCut: 1,
    evocation: 1, intellect: 2, brilliance: 1, removeAway: 1, removeFight: 1, sheepAway: 1, barrierAway: 1, snapAway: 0,
    frostbolt: 24, frostboltRank: FROSTBOLT_2, fireball: 8, fireBlast: 4, missiles: 1, cone: 3, scorch: 4, explosion: 2, blizzard: 4, flamestrike: 1,
    pyroblast: 1, presence: 1, power: 1, combustion: 1,
    sheep: 5, pig: 1, sheepCut: 1, blink: 3, nova: 4, counter: 1, shield: 2, iceBlock: 0, barrier: 2, snap: 1,
    kills: [2, 2, 6],
    damage: [15_000, [[FROSTBOLT_2, 6000], [BLIZZARD, 3000], [FIREBALL, 2000], [SCORCH, 1000], [ARCANE_MISSILES, 3000]]], absorbed: 4000, interrupts: 1, dispels: 2,
  },
  {
    start: 14_460_000,
    death: false,
    water: 10, food: 2, drinks: 6, eats: 2, handWater: 1, handFood: 0, destroyWater: 0, destroyFood: 1, gems: 1, gemsUsed: 1,
    portals: [[PORTAL_STORMWIND, 1], [PORTAL_IRONFORGE, 1], [PORTAL_DARNASSUS, 1]], teleports: [[TELEPORT_STORMWIND, 1], [TELEPORT_IRONFORGE, 1]], portalCut: 0,
    evocation: 1, intellect: 2, brilliance: 1, removeAway: 1, removeFight: 1, sheepAway: 0, barrierAway: 1, snapAway: 1,
    frostbolt: 28, frostboltRank: FROSTBOLT_2, fireball: 10, fireBlast: 4, missiles: 1, cone: 3, scorch: 4, explosion: 2, blizzard: 4, flamestrike: 1,
    pyroblast: 1, presence: 1, power: 1, combustion: 1,
    sheep: 8, pig: 2, sheepCut: 0, blink: 4, nova: 5, counter: 2, shield: 2, iceBlock: 1, barrier: 2, snap: 0,
    kills: [1, 7, 4, 9],
    damage: [20_000, [[FROSTBOLT_2, 8000], [CONE_OF_COLD, 4000], [FIREBALL, 4000], [FIRE_BLAST, 2000], [ARCANE_MISSILES, 2000]]], absorbed: 5000, interrupts: 1, dispels: 2,
  },
];

const SPELL_NAMES = [
  [CONJURE_WATER, "Conjure Water"], [CONJURE_FOOD, "Conjure Food"], [CONJURE_GEM, "Conjure Mana Citrine"], [DRINK, "Drink"], [FOOD, "Food"],
  [PORTAL_STORMWIND, "Portal: Stormwind"], [PORTAL_IRONFORGE, "Portal: Ironforge"], [PORTAL_DARNASSUS, "Portal: Darnassus"],
  [TELEPORT_STORMWIND, "Teleport: Stormwind"], [TELEPORT_IRONFORGE, "Teleport: Ironforge"], [TELEPORT_DARNASSUS, "Teleport: Darnassus"],
  [POLYMORPH, "Polymorph"], [POLYMORPH_PIG, "Polymorph: Pig"], [BLINK, "Blink"], [FROST_NOVA, "Frost Nova"], [COUNTERSPELL, "Counterspell"],
  [ICE_BLOCK, "Ice Block"], [MANA_SHIELD, "Mana Shield"], [EVOCATION, "Evocation"], [BLIZZARD, "Blizzard"], [FROSTBOLT_1, "Frostbolt"],
  [FROSTBOLT_2, "Frostbolt"], [FIREBALL, "Fireball"], [FIRE_BLAST, "Fire Blast"], [ARCANE_MISSILES, "Arcane Missiles"], [CONE_OF_COLD, "Cone of Cold"],
  [SCORCH, "Scorch"], [ARCANE_EXPLOSION, "Arcane Explosion"], [FLAMESTRIKE, "Flamestrike"], [PYROBLAST, "Pyroblast"], [INTELLECT, "Arcane Intellect"],
  [BRILLIANCE, "Arcane Brilliance"], [REMOVE_CURSE, "Remove Lesser Curse"], [ICE_BARRIER, "Ice Barrier"], [COLD_SNAP, "Cold Snap"],
  [PRESENCE_OF_MIND, "Presence of Mind"], [ARCANE_POWER, "Arcane Power"], [COMBUSTION, "Combustion"],
];

const ITEM_NAMES = [[WATER, "Conjured Water"], [MUFFIN, "Conjured Muffin"], [GEM, "Mana Citrine"]];

const TALENTS = [
  ["Ice Barrier", ICE_BARRIER, FROST, 2001, 3001],
  ["Cold Snap", COLD_SNAP, FROST, 2002, 3002],
  ["Ice Block", ICE_BLOCK, FROST, 2003, 3003],
];

const talentLines = () => [
  [500, "TY", ARCANE, "Arcane"],
  [510, "TY", FIRE, "Fire"],
  [520, "TY", FROST, "Frost"],
  [800, "TV", TREE, 8, TALENTS.length, "5b7e19c3"],
  ...TALENTS.map(([, spell, tab, node, entry], at) => [810 + at * 10, "TD", TREE, node, 100 + at * 100, 200, "single", 1, tab, `${entry}:${spell}:1`, ""]),
  ...TALENTS.map(([, spell, tab, node, entry], at) => [1500 + at * 10, "TA", 1, node, entry, 1, "", 1, spell, tab, "s"]),
  [2000, "TG", "s", 1, 1, "", "Frost", TREE, `${ARCANE}:12:0,${FIRE}:0:0,${FROST}:35:0`, 0, 0, 1, ""],
];

export const MAGE_LINES = [
  [0, "S", "1.15.8", "MAGE", "Human", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...SPELL_NAMES.map(([id, name], at) => [100 + at, "SN", id, name]),
  ...ITEM_NAMES.map(([id, name], at) => [200 + at, "IN", id, name]),
  ...talentLines(),
  ...PLANS.flatMap((plan) => cluster(plan.start, plan)),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
