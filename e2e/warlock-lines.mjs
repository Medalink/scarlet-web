/**
 * The session of the fixture character Shard-Keeper (e2e/fixture/Shard-Keeper): six hours of a warlock that plays
 * levels 50, 51 and 52 for two hours each, with the lines every module of the Warlock class page reads (docs/ROWS.md,
 * "The class tables"): soul shards made and spent by spell, Life Taps in and out of fights, four demons, curses,
 * Shadow Trances, Shadowburn kills, a damage meter, stones, summons, a death three seconds after a tap and one
 * self-resurrection, and the talents Nightfall, Shadowburn and Siphon Life. Each line is `[milliseconds since the
 * session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page must show are in
 * e2e/warlock-expected.mjs, written from the plan below before the page was read.
 */

const ELWYNN = 1429;

const LIFE_TAP_4 = 11687;
const LIFE_TAP_5 = 11688;
const DRAIN_SOUL = 1120;
const SHADOW_BOLT = 11661;
const CORRUPTION = 11672;
const IMMOLATE = 11668;
const AGONY_4 = 11711;
const AGONY_5 = 11712;
const WEAKNESS = 11708;
const ELEMENTS = 1490;
const TONGUES = 1714;
const SIPHON_LIFE = 18265;
const SHADOWBURN = 18871;
const SHADOWBURN_TALENT = 17877;
const SOUL_FIRE = 17924;
const HEALTHSTONE_GREATER = 11729;
const HEALTHSTONE_PLAIN = 5699;
const SOULSTONE_GREATER = 20756;
const RITUAL = 698;
const SUMMON_IMP = 688;
const SUMMON_VOIDWALKER = 697;
const SUMMON_SUCCUBUS = 712;
const SUMMON_FELHUNTER = 691;
const HEALTH_FUNNEL = 11695;
const FEAR = 6215;
const DEATH_COIL = 17926;
const HOWL = 5484;
const BANISH = 18647;
const DRINK = 430;
const SHADOW_TRANCE = 17941;
const NIGHTFALL = 18094;
const SOULSTONE_RESURRECTION = 20707;

const SOUL_SHARD = 6265;
const HEALTHSTONE_ITEM = 5510;
const SOULSTONE_ITEM = 16895;

const TREE = 1009;
const AFFLICTION = 201;
const DEMONOLOGY = 202;
const DESTRUCTION = 203;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;
const GAP = 4;
const FIGHT_SPELL_SECONDS = 2.5;

const ms = (seconds) => Math.round(seconds * 1000);

class Track {
  lines = [];
  creature = 990100;

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
const shardOut = (k) => k.at(0.3, "I", SOUL_SHARD, -1, "");

const BLOCKS = {
  tap: (id) => (k) => cast(k, id),
  drain: () => (k) => {
    cast(k, DRAIN_SOUL);
    k.at(5.0, "CH", DRAIN_SOUL, 5.0);
    k.at(0.4, "I", SOUL_SHARD, 1, "");
  },
  dot: (spell) => (k) => cast(k, spell, spell === CORRUPTION || spell === IMMOLATE ? "" : 1.5),
  bolt: () => (k) => cast(k, SHADOW_BOLT, FIGHT_SPELL_SECONDS),
  trance: (used) => (k) => {
    k.at(0, "A", 1, SHADOW_TRANCE, 1, 10, 1, "");
    if (used) {
      k.at(1.45, "A", 0, SHADOW_TRANCE, 1.45);
      k.at(0.05, "C", SHADOW_BOLT, "");
      return;
    }
    k.at(2.0, "A", 0, SHADOW_TRANCE, 2.0);
  },
  burn: () => (k) => {
    cast(k, SHADOWBURN);
    shardOut(k);
  },
  soulFire: () => (k) => {
    cast(k, SOUL_FIRE, 4.0);
    shardOut(k);
  },
  fear: (cut) => (k) => {
    if (cut) {
      k.at(0, "CI", FEAR, 1.0);
      return;
    }
    cast(k, FEAR, 1.5);
  },
  spell: (id) => (k) => cast(k, id),
  stone: (spell, item) => (k) => {
    cast(k, spell, 3.0);
    shardOut(k);
    k.at(0.1, "I", item, 1, "");
  },
  ritual: (finished) => (k) => {
    if (finished) {
      cast(k, RITUAL, 5.0);
      shardOut(k);
      return;
    }
    k.at(0, "CI", RITUAL, 1.0);
  },
  funnel: () => (k) => {
    cast(k, HEALTH_FUNNEL);
    k.at(30.0, "CH", HEALTH_FUNNEL, 30.0);
  },
  destroy: (count) => (k) => {
    k.at(0, "DI", SOUL_SHARD, count);
    k.at(1.0, "I", SOUL_SHARD, -count, "");
  },
  eat: () => (k) => k.at(0, "I", HEALTHSTONE_ITEM, -1, ""),
  hand: () => (k) => k.at(0, "I", HEALTHSTONE_ITEM, -1, "t"),
  loot: () => (k) => k.at(0, "I", SOUL_SHARD, 1, "l"),
  drink: () => (k) => {
    k.at(0, "A", 1, DRINK, 1, 18, 1, "");
    k.at(300, "A", 0, DRINK, 300.0);
  },
  tapThenDeath: (id) => (k) => {
    cast(k, id);
    k.at(3.0, "D");
    k.at(0.5, "SR", 0, SOULSTONE_RESURRECTION);
    k.at(1.5, "U");
    k.wait(12);
  },
  fearThenDeath: () => (k) => {
    cast(k, FEAR, 1.5);
    k.at(6.0, "D");
    k.at(0.5, "SR", 0, SOULSTONE_RESURRECTION);
    k.at(2.0, "R");
    k.wait(12);
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

function killBlock(k, kind) {
  const npc = k.npc();
  if (kind === "bolt") {
    cast(k, SHADOW_BOLT, FIGHT_SPELL_SECONDS);
    k.at(1.0, "K", npc, 1);
    return;
  }
  if (kind === "burnKill") {
    cast(k, SHADOWBURN);
    shardOut(k);
    k.at(0.7, "K", npc, 1);
    k.at(0.2, "I", SOUL_SHARD, 1, "");
    return;
  }
  if (kind === "away") {
    k.wait(5);
    k.at(0, "K", npc, 1);
    return;
  }
  k.at(0.5, "K", npc, 0);
}

function fights(k, kills, pool) {
  const queue = [...pool];
  kills.forEach((kind, at) => {
    const share = Math.ceil(queue.length / (kills.length - at));
    k.wait(1);
    const opened = k.t;
    k.at(0, "F", 1);
    run(k, queue.splice(0, share));
    killBlock(k, kind);
    k.at(1.0, "F", 0, ((k.t - opened) / 1000).toFixed(1) * 1);
    k.wait(GAP);
  });
}

function reports(k, damage, healing) {
  const [total, spells] = damage;
  k.at(2, "DM", "d", total, (total / 100).toFixed(1) * 1, 100.0);
  for (const [spell, amount] of spells) k.at(0.2, "DS", "d", spell, amount);
  k.at(2, "DM", "h", healing[0], (healing[0] / 100).toFixed(1) * 1, 100.0);
  k.at(0.2, "DS", "h", SIPHON_LIFE, healing[1]);
}

function cluster(start, plan) {
  const k = new Track(start);
  const opening = [];
  if (plan.tapRun) {
    opening.push(...repeat(plan.tapRun, () => BLOCKS.tap(LIFE_TAP_5)));
  }
  if (plan.death) opening.push(plan.death);
  run(k, opening);

  const away = interleave([
    repeat(plan.drain, () => BLOCKS.drain()),
    plan.tapsOut.map((id) => BLOCKS.tap(id)),
    plan.healthstones.map((id) => BLOCKS.stone(id, HEALTHSTONE_ITEM)),
    repeat(plan.soulstones, () => BLOCKS.stone(SOULSTONE_GREATER, SOULSTONE_ITEM)),
    repeat(plan.ritual, () => BLOCKS.ritual(true)),
    repeat(plan.ritualCut, () => BLOCKS.ritual(false)),
    repeat(plan.funnel, () => BLOCKS.funnel()),
    plan.destroyed.map((count) => BLOCKS.destroy(count)),
    repeat(plan.eaten, () => BLOCKS.eat()),
    repeat(plan.handed, () => BLOCKS.hand()),
    repeat(plan.looted, () => BLOCKS.loot()),
    repeat(plan.drinks, () => BLOCKS.drink()),
  ]);
  run(k, away);

  const kills = [...repeat(plan.killBolt, () => "bolt"), ...repeat(plan.killBurn, () => "burnKill"), ...repeat(plan.killAway, () => "away"), ...repeat(plan.killParty, () => "party")];
  const pool = interleave([
    plan.tapsIn.map((id) => BLOCKS.tap(id)),
    plan.agony.flatMap(([id, count]) => repeat(count, () => BLOCKS.dot(id))),
    repeat(plan.weakness, () => BLOCKS.dot(WEAKNESS)),
    repeat(plan.elements, () => BLOCKS.dot(ELEMENTS)),
    repeat(plan.tongues, () => BLOCKS.dot(TONGUES)),
    repeat(plan.corruption, () => BLOCKS.dot(CORRUPTION)),
    repeat(plan.immolate, () => BLOCKS.dot(IMMOLATE)),
    repeat(plan.siphon, () => BLOCKS.dot(SIPHON_LIFE)),
    repeat(plan.bolts, () => BLOCKS.bolt()),
    repeat(plan.trancesUsed, () => BLOCKS.trance(true)),
    repeat(plan.trancesLost, () => BLOCKS.trance(false)),
    repeat(plan.burnsMissed, () => BLOCKS.burn()),
    repeat(plan.soulFire, () => BLOCKS.soulFire()),
    repeat(plan.fear, () => BLOCKS.fear(false)),
    repeat(plan.fearCut, () => BLOCKS.fear(true)),
    repeat(plan.coil, () => BLOCKS.spell(DEATH_COIL)),
    repeat(plan.howl, () => BLOCKS.spell(HOWL)),
    repeat(plan.banish, () => BLOCKS.spell(BANISH)),
  ]);
  fights(k, kills, pool);
  reports(k, plan.damage, plan.healing);
  return k.lines;
}

const PLANS = [
  {
    start: 60_000,
    tapRun: 4, tapsOut: [LIFE_TAP_4], tapsIn: [LIFE_TAP_4, LIFE_TAP_5],
    drain: 14, looted: 1, destroyed: [4], eaten: 2, handed: 1, drinks: 2, funnel: 4,
    healthstones: [HEALTHSTONE_GREATER, HEALTHSTONE_GREATER, HEALTHSTONE_PLAIN], soulstones: 1, ritual: 1, ritualCut: 0,
    bolts: 4, trancesUsed: 2, trancesLost: 1, burnsMissed: 1, soulFire: 1,
    agony: [[AGONY_5, 6], [AGONY_4, 2]], weakness: 2, elements: 1, tongues: 1, corruption: 5, immolate: 3, siphon: 4,
    fear: 3, fearCut: 1, coil: 1, howl: 1, banish: 0,
    killBolt: 3, killBurn: 0, killAway: 2, killParty: 1,
    damage: [24_000, [[CORRUPTION, 6000], [SHADOW_BOLT, 9000], [AGONY_5, 5000], [SIPHON_LIFE, 2000]]], healing: [3000, 900],
  },
  {
    start: 7_260_000,
    death: BLOCKS.tapThenDeath(LIFE_TAP_5),
    tapsOut: [LIFE_TAP_5, LIFE_TAP_5], tapsIn: [LIFE_TAP_5, LIFE_TAP_5],
    drain: 12, looted: 1, destroyed: [4], eaten: 1, handed: 0, drinks: 1, funnel: 4,
    healthstones: [HEALTHSTONE_GREATER, HEALTHSTONE_GREATER], soulstones: 1, ritual: 1, ritualCut: 0,
    bolts: 4, trancesUsed: 2, trancesLost: 0, burnsMissed: 1, soulFire: 1,
    agony: [[AGONY_5, 5]], weakness: 2, elements: 1, tongues: 1, corruption: 5, immolate: 3, siphon: 5,
    fear: 2, fearCut: 1, coil: 2, howl: 0, banish: 0,
    killBolt: 2, killBurn: 1, killAway: 3, killParty: 2,
    damage: [31_000, [[CORRUPTION, 8000], [SHADOW_BOLT, 10000], [AGONY_5, 6000], [SIPHON_LIFE, 3000], [IMMOLATE, 2000]]], healing: [4000, 1200],
  },
  {
    start: 14_460_000,
    death: BLOCKS.fearThenDeath(),
    tapsOut: [LIFE_TAP_5, LIFE_TAP_5], tapsIn: [LIFE_TAP_5, LIFE_TAP_5, LIFE_TAP_5, LIFE_TAP_5],
    drain: 10, looted: 0, destroyed: [3], eaten: 1, handed: 1, drinks: 1, funnel: 4,
    healthstones: [HEALTHSTONE_GREATER, HEALTHSTONE_GREATER], soulstones: 1, ritual: 2, ritualCut: 1,
    bolts: 6, trancesUsed: 2, trancesLost: 1, burnsMissed: 1, soulFire: 1,
    agony: [[AGONY_5, 6]], weakness: 2, elements: 2, tongues: 1, corruption: 5, immolate: 3, siphon: 5,
    fear: 2, fearCut: 0, coil: 2, howl: 1, banish: 1,
    killBolt: 3, killBurn: 1, killAway: 2, killParty: 1,
    damage: [45_000, [[SHADOW_BOLT, 15000], [CORRUPTION, 10000], [AGONY_5, 8000], [SIPHON_LIFE, 4000], [SHADOWBURN, 3000]]], healing: [5000, 1500],
  },
];

const SPELL_NAMES = [
  [LIFE_TAP_4, "Life Tap"], [LIFE_TAP_5, "Life Tap"], [DRAIN_SOUL, "Drain Soul"], [SHADOW_BOLT, "Shadow Bolt"], [CORRUPTION, "Corruption"],
  [IMMOLATE, "Immolate"], [AGONY_4, "Curse of Agony"], [AGONY_5, "Curse of Agony"], [WEAKNESS, "Curse of Weakness"], [ELEMENTS, "Curse of the Elements"],
  [TONGUES, "Curse of Tongues"], [SIPHON_LIFE, "Siphon Life"], [SHADOWBURN, "Shadowburn"], [SHADOWBURN_TALENT, "Shadowburn"], [SOUL_FIRE, "Soul Fire"],
  [HEALTHSTONE_GREATER, "Create Healthstone (Greater)"], [HEALTHSTONE_PLAIN, "Create Healthstone"], [SOULSTONE_GREATER, "Create Soulstone (Greater)"],
  [RITUAL, "Ritual of Summoning"], [SUMMON_IMP, "Summon Imp"], [SUMMON_VOIDWALKER, "Summon Voidwalker"], [SUMMON_SUCCUBUS, "Summon Succubus"],
  [SUMMON_FELHUNTER, "Summon Felhunter"], [HEALTH_FUNNEL, "Health Funnel"], [FEAR, "Fear"], [DEATH_COIL, "Death Coil"], [HOWL, "Howl of Terror"],
  [BANISH, "Banish"], [DRINK, "Drink"], [SHADOW_TRANCE, "Shadow Trance"], [NIGHTFALL, "Nightfall"], [SIPHON_LIFE, "Siphon Life"],
  [SOULSTONE_RESURRECTION, "Soulstone Resurrection"],
];

const ITEM_NAMES = [[SOUL_SHARD, "Soul Shard"], [HEALTHSTONE_ITEM, "Greater Healthstone"], [5509, "Healthstone"], [SOULSTONE_ITEM, "Greater Soulstone"]];

const DESCRIPTIONS = [[LIFE_TAP_5, "Converts 310 health into 310 mana."], [LIFE_TAP_4, "Converts 220 health into 220 mana."]];

const talentLines = () => [
  [500, "TY", AFFLICTION, "Affliction"],
  [510, "TY", DEMONOLOGY, "Demonology"],
  [520, "TY", DESTRUCTION, "Destruction"],
  [800, "TV", TREE, 9, 3, "9f2c41ab"],
  [810, "TD", TREE, 1001, 100, 200, "single", 2, AFFLICTION, `2001:${NIGHTFALL}:2`, ""],
  [820, "TD", TREE, 1002, 100, 300, "single", 1, AFFLICTION, `2002:${SIPHON_LIFE}:1`, "1001:0"],
  [830, "TD", TREE, 1003, 400, 200, "single", 1, DESTRUCTION, `2003:${SHADOWBURN_TALENT}:1`, ""],
  [1500, "TA", 1, 1001, 2001, 2, "", 2, NIGHTFALL, AFFLICTION, "s"],
  [1510, "TA", 1, 1002, 2002, 1, "", 1, SIPHON_LIFE, AFFLICTION, "s"],
  [1520, "TA", 1, 1003, 2003, 1, "", 1, SHADOWBURN_TALENT, DESTRUCTION, "s"],
  [2000, "TG", "s", 1, 1, "", "Affliction", TREE, `${AFFLICTION}:31:0,${DEMONOLOGY}:0:0,${DESTRUCTION}:20:0`, 0, 0, 1, ""],
];

const summon = (cast, castAt, family, name) => [
  [castAt, "C", cast, 2.0],
  ...(cast === SUMMON_IMP ? [] : [[castAt + 300, "I", SOUL_SHARD, -1, ""]]),
  [castAt + 2000, "PT", name, family],
];

export const WARLOCK_LINES = [
  [0, "S", "1.15.8", "WARLOCK", "Human", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...SPELL_NAMES.map(([id, name], at) => [100 + at, "SN", id, name]),
  ...ITEM_NAMES.map(([id, name], at) => [200 + at, "IN", id, name]),
  ...DESCRIPTIONS.map(([id, text], at) => [300 + at, "SD", id, text]),
  ...talentLines(),
  ...PLANS.flatMap((plan) => cluster(plan.start, plan)),
  ...summon(SUMMON_IMP, 18_000, "Imp", "Piptik"),
  ...summon(SUMMON_VOIDWALKER, 2_998_000, "Voidwalker", "Zharmok"),
  [12_600_000, "PT", "", ""],
  ...summon(SUMMON_SUCCUBUS, 12_998_000, "Succubus", "Nethira"),
  ...summon(SUMMON_FELHUNTER, 15_398_000, "Felhunter", "Shaadrun"),
  ...summon(SUMMON_FELHUNTER, 17_998_000, "Felhunter", "Shaadrun"),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
