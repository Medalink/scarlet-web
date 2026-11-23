/**
 * The session of the fixture character Oath-Bearer (e2e/fixture/Oath-Bearer): six hours of a paladin that plays levels
 * 50, 51 and 52 for two hours each, with the lines every module of the Paladin class page reads (docs/ROWS.md, "The
 * class tables"): seals that are judged, replaced, run out, end another way or are still up when the fight ends, the
 * auras, the blessings with the Symbols of Kings they cost, the undead spells, the bubble with a Hearthstone cast
 * inside it and a death after it, Lay on Hands, heals, control, a mount, the meters, three deaths, and the talents
 * Seal of Command, Repentance and Consecration. Each line is `[milliseconds since the session began, code, ...fields]`,
 * as e2e/build-fixture.mjs reads them. The figures the page must show are worked out from these lines by
 * e2e/paladin-expected.mjs.
 */

import { GAP, HOUR, Track, nameLines, sessionStart, talentLines } from "./lines-kit.mjs";

const SOR = 20154;
const SOR_TWO = 20287;
const SOC = 20375;
const SOCR = 20162;
const SOL = 20165;
const SOW = 20166;
const SOJ = 20164;
const JUDGE = 20271;
const DEVOTION = 465;
const RETRIBUTION = 7294;
const CONCENTRATION = 19746;
const SHADOW_AURA = 19876;
const MIGHT = 19740;
const WISDOM = 19742;
const KINGS = 20217;
const PROTECTION = 1022;
const FREEDOM = 1044;
const GREATER_MIGHT = 25782;
const GREATER_WISDOM = 25894;
const EXORCISM = 879;
const HOLY_WRATH = 2812;
const TURN_UNDEAD = 2878;
const CONSECRATION = 26573;
const HAMMER_OF_WRATH = 24239;
const SHIELD = 642;
const PROTECT = 498;
const LAY_ON_HANDS = 633;
const HOLY_LIGHT = 635;
const FLASH = 19750;
const HOLY_SHOCK = 20473;
const HAMMER_OF_JUSTICE = 853;
const REPENTANCE = 20066;
const FURY = 25780;
const INTERVENTION = 19752;
const REDEMPTION = 7328;
const CLEANSE = 4987;
const PURIFY = 1152;
const WARHORSE = 13819;
const HEARTHSTONE = 8690;
const SYMBOL = 21177;

const TREE = 1002;
const HOLY = 381;
const PROT = 382;
const RET = 383;

const SESSION_END = 6 * HOUR;
const seconds = (from, to) => Number(((to - from) / 1000).toFixed(3));
const SEAL_SECONDS = 30;

const cast = (k, spell) => k.at(0, "C", spell, "");

function gain(k, id, duration) {
  k.at(0.1, "A", 1, id, 1, duration, 1, "");
  k.auras ??= new Map();
  k.auras.set(id, k.t);
}

function lose(k, id) {
  k.at(0, "A", 0, id, seconds(k.auras.get(id), k.t));
  k.auras.delete(id);
}

function sealChain(k, chain) {
  let pending = null;
  chain.forEach(([id, fate], at) => {
    cast(k, id);
    gain(k, id, SEAL_SECONDS);
    if (at > 0) {
      k.wait(0);
      lose(k, chain[at - 1][0]);
    }
    if (fate === "replaced") {
      k.wait(5);
      return;
    }
    if (fate === "judged") {
      k.wait(3);
      cast(k, JUDGE);
      k.wait(0.2);
      lose(k, id);
    }
    if (fate === "ranout") {
      k.wait(SEAL_SECONDS - 0.1);
      lose(k, id);
    }
    if (fate === "other") {
      k.wait(8);
      lose(k, id);
    }
    if (fate === "fightend") pending = id;
  });
  return pending;
}

function fight(k, script) {
  k.wait(GAP);
  for (const spell of script.pre ?? []) {
    cast(k, spell);
    if (spell === GREATER_MIGHT || spell === GREATER_WISDOM) k.at(0.2, "I", SYMBOL, -1, "");
    k.wait(1);
  }
  k.wait(3);
  const opened = k.t;
  k.at(0, "F", 1);
  k.wait(1);
  const pending = sealChain(k, script.seals ?? []);
  for (const step of script.steps ?? []) {
    k.wait(1.5);
    cast(k, step);
  }
  const npc = k.npc();
  if (script.kill === "party") k.at(1.0, "K", npc, 0);
  if (script.kill !== "party") k.at(1.0, "K", npc, 1);
  k.at(1.0, "F", 0, seconds(opened, k.t + 1000));
  const length = seconds(opened, k.t);
  k.at(1.5, "DM", "d", script.dealt, Number((script.dealt / length).toFixed(1)), length);
  k.at(0.2, "DM", "t", script.taken, Number((script.taken / length).toFixed(1)), length);
  for (const [spell, amount] of script.top ?? []) k.at(0.2, "DS", "d", spell, amount);
  if (script.heal) {
    k.at(0.2, "DM", "h", script.heal[0], Number((script.heal[0] / length).toFixed(1)), length);
    for (const [spell, amount] of script.heal[1]) k.at(0.2, "DS", "h", spell, amount);
  }
  if (script.dispels) k.at(0.2, "DM", "x", script.dispels, 0, length);
  if (pending !== null) {
    k.wait(2);
    lose(k, pending);
  }
  script.after?.(k);
}

function bubble(k, spell, hearth) {
  k.wait(2);
  cast(k, spell);
  gain(k, spell, 12);
  if (hearth) {
    k.wait(1);
    k.at(0, "C", HEARTHSTONE, 10);
    k.wait(9);
  } else {
    k.wait(10);
  }
  lose(k, spell);
}

const fightOf = (seals, steps, spec) => ({ seals, steps, dealt: 4800, taken: 2100, ...spec });

const CLUSTERS = [
  {
    start: 60_000,
    fights: [
      fightOf([[SOR, "judged"]], [HOLY_LIGHT, EXORCISM, CONSECRATION], { pre: [MIGHT, FURY], dealt: 5200, taken: 1900, top: [[SOR, 1500], [EXORCISM, 900]], heal: [1700, [[HOLY_LIGHT, 1700]]] }),
      fightOf([[SOR, "replaced"], [SOCR, "judged"]], [FLASH, HOLY_WRATH, HAMMER_OF_JUSTICE], { pre: [MIGHT, WISDOM], top: [[HOLY_WRATH, 1100]], heal: [2600, [[FLASH, 1600], [HOLY_LIGHT, 1000]]] }),
      fightOf([[SOC, "ranout"]], [HOLY_LIGHT, JUDGE, TURN_UNDEAD, SHIELD], { pre: [KINGS], dealt: 6100, top: [[SOC, 2500], [TURN_UNDEAD, 300]], after: (k) => bubble(k, SHIELD, true) }),
      fightOf([[SOC, "fightend"]], [FLASH, CONSECRATION, REPENTANCE], { pre: [GREATER_MIGHT], dealt: 4100, taken: 1600, top: [[SOC, 1800]], heal: [900, [[FLASH, 900]]] }),
      fightOf([[SOL, "other"]], [EXORCISM, HOLY_LIGHT, HAMMER_OF_WRATH], { dealt: 3900, taken: 2400, top: [[EXORCISM, 700]], dispels: 2 }),
      fightOf([[SOW, "judged"]], [PROTECT, FLASH, HAMMER_OF_JUSTICE], { pre: [PROTECTION], dealt: 4400, taken: 3100, kill: "party", heal: [2100, [[FLASH, 2100]]] }),
    ],
    after: (k) => {
      k.wait(2);
      cast(k, REDEMPTION);
      k.wait(30);
      k.at(0, "D");
      k.at(2, "U");
    },
  },
  {
    start: 7_260_000,
    fights: [
      fightOf([[SOC, "judged"]], [HOLY_LIGHT, CONSECRATION, EXORCISM], { pre: [GREATER_WISDOM, FREEDOM], dealt: 6200, top: [[SOC, 2900], [EXORCISM, 800]], heal: [1500, [[HOLY_LIGHT, 1500]]] }),
      fightOf([[SOR_TWO, "ranout"]], [FLASH, REPENTANCE, LAY_ON_HANDS], { dealt: 3600, taken: 3300, heal: [2800, [[FLASH, 1800], [HOLY_SHOCK, 1000]]] }),
      fightOf([[SOC, "judged"]], [HOLY_SHOCK, TURN_UNDEAD, CLEANSE], { pre: [MIGHT, KINGS], dealt: 5900, top: [[SOC, 3100]], dispels: 3 }),
      fightOf([[SOR, "replaced"], [SOC, "fightend"]], [HOLY_LIGHT, CONSECRATION, PURIFY], { pre: [GREATER_MIGHT], dealt: 5000, taken: 2200, top: [[SOC, 2000], [CONSECRATION, 400]] }),
      fightOf([[SOJ, "other"]], [EXORCISM, FLASH, INTERVENTION], { dealt: 3300, taken: 2900, heal: [1200, [[FLASH, 1200]]] }),
      fightOf([[SOR, "judged"]], [HAMMER_OF_JUSTICE, HOLY_LIGHT], { dealt: 3500, taken: 4000, kill: "party" }),
    ],
    after: (k) => {
      k.wait(3);
      cast(k, LAY_ON_HANDS);
      k.at(5, "D");
      k.at(2, "U");
    },
  },
  {
    start: 14_460_000,
    fights: [
      fightOf([[SOC, "judged"]], [CONSECRATION, EXORCISM, HOLY_LIGHT], { pre: [MIGHT, GREATER_MIGHT], dealt: 6600, top: [[SOC, 3300], [EXORCISM, 1000]], heal: [1400, [[HOLY_LIGHT, 1400]]] }),
      fightOf([[SOC, "ranout"]], [FLASH, REPENTANCE, HAMMER_OF_WRATH], { dealt: 6000, taken: 2000, top: [[SOC, 2700]] }),
      fightOf([[SOC, "judged"]], [HOLY_SHOCK, EXORCISM, PROTECT], { pre: [KINGS], dealt: 7100, top: [[SOC, 3600], [HOLY_WRATH, 700]], heal: [1100, [[HOLY_SHOCK, 1100]]], after: (k) => bubble(k, PROTECT, false) }),
      fightOf([[SOC, "fightend"]], [HOLY_LIGHT, CONSECRATION, HOLY_WRATH], { pre: [WISDOM], dealt: 5400, taken: 2300, top: [[SOC, 2200]] }),
      fightOf([[SOL, "judged"]], [FLASH, TURN_UNDEAD, REPENTANCE], { dealt: 4000, taken: 2600, dispels: 1 }),
      fightOf([[SOC, "replaced"], [SOW, "ranout"]], [HOLY_LIGHT, HAMMER_OF_JUSTICE, EXORCISM], { dealt: 5600, taken: 2700 }),
    ],
    after: (k) => {
      bubble(k, SHIELD, true);
      k.at(4, "D");
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

function rides() {
  const ride = (from, length) => [
    [from - 3000, "C", WARHORSE, ""],
    [from, "MO", 1, WARHORSE, 1, 0, ""],
    [from + length, "MO", 0, WARHORSE, 1, length / 1000],
  ];
  return [...ride(1_000_000, 0.5 * HOUR), ...ride(10_000_000, HOUR), ...ride(20_000_000, 100_000)];
}

const aura = (id, from, to) => [
  [from, "A", 1, id, 1, 0, 1, ""],
  [to, "A", 0, id, seconds(from, to)],
];

const AURAS = [
  ...aura(DEVOTION, 5_000, 1.9 * HOUR),
  ...aura(RETRIBUTION, 1.9 * HOUR, 3 * HOUR),
  ...aura(CONCENTRATION, 3 * HOUR, 3.5 * HOUR),
  ...aura(RETRIBUTION, 3.5 * HOUR, 5.5 * HOUR),
  ...aura(SHADOW_AURA, 5.5 * HOUR, 5.9 * HOUR),
  [100_000, "A", 1, FURY, 1, 0, 1, ""],
  [1.2 * HOUR, "A", 0, FURY, seconds(100_000, 1.2 * HOUR)],
];

const SPELL_NAMES = [
  [SOR, "Seal of Righteousness"], [SOR_TWO, "Seal of Righteousness"], [SOC, "Seal of Command"], [SOCR, "Seal of the Crusader"], [SOL, "Seal of Light"],
  [SOW, "Seal of Wisdom"], [SOJ, "Seal of Justice"], [JUDGE, "Judgement"], [DEVOTION, "Devotion Aura"], [RETRIBUTION, "Retribution Aura"],
  [CONCENTRATION, "Concentration Aura"], [SHADOW_AURA, "Shadow Resistance Aura"], [MIGHT, "Blessing of Might"], [WISDOM, "Blessing of Wisdom"],
  [KINGS, "Blessing of Kings"], [PROTECTION, "Blessing of Protection"], [FREEDOM, "Blessing of Freedom"], [GREATER_MIGHT, "Greater Blessing of Might"],
  [GREATER_WISDOM, "Greater Blessing of Wisdom"], [EXORCISM, "Exorcism"], [HOLY_WRATH, "Holy Wrath"], [TURN_UNDEAD, "Turn Undead"],
  [CONSECRATION, "Consecration"], [HAMMER_OF_WRATH, "Hammer of Wrath"], [SHIELD, "Divine Shield"], [PROTECT, "Divine Protection"],
  [LAY_ON_HANDS, "Lay on Hands"], [HOLY_LIGHT, "Holy Light"], [FLASH, "Flash of Light"], [HOLY_SHOCK, "Holy Shock"],
  [HAMMER_OF_JUSTICE, "Hammer of Justice"], [REPENTANCE, "Repentance"], [FURY, "Righteous Fury"], [INTERVENTION, "Divine Intervention"],
  [REDEMPTION, "Redemption"], [CLEANSE, "Cleanse"], [PURIFY, "Purify"], [WARHORSE, "Summon Warhorse"], [HEARTHSTONE, "Hearthstone"],
];

const talents = () => talentLines({
  tree: TREE,
  classId: 2,
  hash: "2d4a61f0",
  spec: "Retribution",
  tabs: [[HOLY, "Holy"], [PROT, "Protection"], [RET, "Retribution"]],
  nodes: [
    { node: 2001, spell: SOC, ranks: 1, held: 1, tab: RET },
    { node: 2002, spell: REPENTANCE, ranks: 1, held: 1, tab: RET },
    { node: 2003, spell: CONSECRATION, ranks: 1, held: 1, tab: HOLY },
  ],
  spent: [11, 4, 31],
});

export const PALADIN_LINES = [
  ...sessionStart("PALADIN", "Human", 50, nameLines({ spells: SPELL_NAMES, items: [[SYMBOL, "Symbol of Kings"]] })),
  ...talents(),
  ...AURAS,
  ...rides(),
  ...CLUSTERS.flatMap(cluster),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
