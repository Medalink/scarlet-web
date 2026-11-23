/**
 * The session of the fixture character Pocket-Shade (e2e/fixture/Pocket-Shade): six hours of a rogue that plays
 * levels 50, 51 and 52 for two hours each, with the lines every module of the Rogue class page reads (docs/ROWS.md,
 * "The class tables"): 24 fights that open and finish in every way the page names, a long stealth with pockets
 * picked, locks and lockboxes, poisons applied and crafted, escapes, Kicks and interrupts, Blade Flurry, Adrenaline
 * Rush and Riposte, one death after a Vanish, and the build Combat 5 / 31 / 7. Each line is `[milliseconds since the
 * session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page must show are in
 * e2e/rogue-expected.mjs, written from the plan below before the page was read.
 */

const ELWYNN = 1429;

const STEALTH = 1784;
const OPENERS = { C: 1833, A: 8676, G: 703, S: 2070 };
const OPENER_GAP = { C: 2, A: 3, G: 4 };
const EVISCERATE = [2098, 6760, 11299];
const SLICE = 5171;
const KIDNEY = 408;
const RUPTURE = 1943;
const EXPOSE = 8647;
const FINISHERS = { E: null, D: SLICE, K: KIDNEY, R: RUPTURE, X: EXPOSE };
const SINISTER = [1752, 1757, 11293];
const BACKSTAB = 53;
const HEMORRHAGE = 16511;
const GHOSTLY = 14278;
const GOUGE = 1776;
const FEINT = 1966;
const SPRINT = 2983;
const EVASION = 5277;
const BLIND = 2094;
const VANISH = 1856;
const KICK = 1766;
const PICK_POCKET = 921;
const PICK_LOCK = 1804;
const DISTRACT = 1725;
const DETECT = 2836;
const DISARM = 1842;
const FLURRY = 13877;
const RUSH = 13750;
const RIPOSTE = 14251;
const FLURRY_TALENT = 13877;
const RUSH_TALENT = 13750;
const RIPOSTE_TALENT = 14251;

const INSTANT = 6947;
const INSTANT_II = 6949;
const DEADLY = 2892;
const CRIPPLING = 3775;
const MIND_NUMBING = 5237;
const LOCKBOX = 4634;

const TREE = 5101;
const ASSASSINATION = 182;
const COMBAT = 181;
const SUBTLETY = 183;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;
const GAP = 4;
const STEP = 2;
const LONG_STEALTH = 1800;
const BUFF_SECONDS = 15;

const ms = (seconds) => Math.round(seconds * 1000);

class Track {
  lines = [];
  creature = 990200;

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

const cast = (k, spell, seconds = "") => k.at(STEP, "C", spell, seconds);

const POOL = {
  SS: (c) => (k) => cast(k, SINISTER[c]),
  BS: () => (k) => cast(k, BACKSTAB),
  HE: () => (k) => cast(k, HEMORRHAGE),
  GH: () => (k) => cast(k, GHOSTLY),
  GO: () => (k) => cast(k, GOUGE),
  FE: () => (k) => cast(k, FEINT),
  SP: () => (k) => cast(k, SPRINT),
  EV: () => (k) => cast(k, EVASION),
  BL: () => (k) => cast(k, BLIND),
  VA: () => (k) => cast(k, VANISH),
  KI: () => (k) => cast(k, KICK),
  RI: () => (k) => cast(k, RIPOSTE),
  RR: () => (k) => {
    cast(k, RIPOSTE);
    cast(k, RIPOSTE);
  },
  XE: (c) => (k) => cast(k, EVISCERATE[c]),
  XD: () => (k) => cast(k, SLICE),
  flurry: (c, under) => (k) => buffed(k, FLURRY, under, SINISTER[c]),
  rush: (c, under) => (k) => buffed(k, RUSH, under, SINISTER[c]),
};

function buffed(k, spell, under, attack) {
  cast(k, spell);
  k.at(0.5, "A", 1, spell, 1, BUFF_SECONDS, 1, "");
  const gained = k.t;
  for (let at = 0; at < under; at += 1) k.at(STEP, "C", attack, "");
  k.t = gained + ms(BUFF_SECONDS);
  k.lines.push([k.t, "A", 0, spell, BUFF_SECONDS]);
}

function fight(k, spec, c, pool) {
  k.wait(GAP);
  const npc = k.npc();
  const [opener, finisher] = spec;
  if (opener === "S") {
    k.at(0, "C", OPENERS.S, "");
    k.at(0.4, "F", 1);
  } else if (opener !== null) {
    const gap = OPENER_GAP[opener];
    k.at(0, "A", 1, STEALTH, 1, "", 1, "");
    k.at(gap, "C", OPENERS[opener], "");
    k.at(0.05, "A", 0, STEALTH, gap + 0.05);
    k.at(0.05, "F", 1);
  } else {
    k.at(0, "F", 1);
  }
  const opened = k.t;
  if (finisher !== null) {
    const id = finisher === "E" ? EVISCERATE[c] : FINISHERS[finisher];
    cast(k, id);
  }
  for (const block of pool) block(k);
  k.at(1.0, "K", npc, 1);
  k.at(0.5, "F", 0, Number(((k.t + 500 - opened) / 1000).toFixed(1)));
}

function spread(items, fights) {
  const lists = Array.from({ length: fights }, () => []);
  items.forEach((item, at) => lists[at % fights].push(item));
  return lists;
}

function counts(plan) {
  const tokens = [];
  for (const [token, count] of Object.entries(plan.pool)) for (let at = 0; at < count; at += 1) tokens.push(token);
  return tokens;
}

function cluster(start, plan, c) {
  const k = new Track(start);
  k.at(0, "A", 1, STEALTH, 1, "", 1, "");
  const gained = k.t;
  k.wait(10);
  for (let at = 0; at < plan.pockets; at += 1) {
    k.at(20, "C", PICK_POCKET, "");
  }
  k.t = gained + ms(LONG_STEALTH);
  k.lines.push([k.t, "A", 0, STEALTH, LONG_STEALTH + 0.0]);

  k.wait(GAP);
  for (const [item, times] of plan.poisons) {
    for (let at = 0; at < times; at += 1) k.at(3, "I", item, -1, "");
  }
  for (const [item, quantity] of plan.crafted) k.at(3, "I", item, quantity, "c");
  k.wait(GAP);

  for (let at = 0; at < plan.locks; at += 1) {
    k.at(GAP, "C", PICK_LOCK, 2.0);
    if (at < plan.lockboxes) k.at(0.5, "I", LOCKBOX, -1, "");
  }
  k.wait(GAP);
  for (const spell of plan.scouting) {
    k.at(GAP, "C", spell, "");
  }
  for (let at = 0; at < plan.sap; at += 1) k.at(GAP, "C", OPENERS.S, "");
  for (let at = 0; at < plan.ripostesOut; at += 1) k.at(GAP, "C", RIPOSTE, "");
  k.wait(GAP);

  const pools = spread(counts(plan).map((token) => POOL[token](c)), plan.fights.length);
  plan.fights.forEach((spec, at) => {
    const extra = [];
    if (plan.flurry === at) extra.push(POOL.flurry(c, plan.flurryUnder));
    if (plan.rush === at) extra.push(POOL.rush(c, 2));
    if (plan.extra[0] === at) extra.push(POOL[plan.extra[1]](c));
    fight(k, spec, c, [...extra, ...pools[at]]);
  });
  k.at(2, "DM", "d", plan.damage[0], plan.damage[0] / 100, 100.0);
  for (const [spell, amount] of plan.damage[1]) k.at(0.2, "DS", "d", spell, amount);
  k.at(2, "DM", "i", plan.interrupts, plan.interrupts / 10, 100.0);
  if (plan.death) {
    k.wait(GAP);
    k.at(0, "C", VANISH, "");
    k.at(4.0, "D");
    k.at(0.5, "R");
    k.at(1.5, "U");
  }
  return k.lines;
}

const PLANS = [
  {
    start: 60_000,
    pockets: 6,
    poisons: [[INSTANT, 8], [INSTANT_II, 4], [DEADLY, 4], [CRIPPLING, 1]],
    crafted: [[INSTANT, 10]],
    locks: 4,
    lockboxes: 2,
    scouting: [DISTRACT, DISTRACT, DETECT],
    sap: 1,
    ripostesOut: 1,
    fights: [["C", "E"], ["C", "E"], ["A", "E"], ["G", null], [null, "E"], [null, null], ["S", null], ["C", null]],
    pool: { SS: 10, BS: 4, GH: 2, GO: 2, FE: 1, SP: 3, EV: 2, BL: 1, VA: 2, KI: 4, RR: 1 },
    extra: [0, "XE"],
    flurry: 1,
    flurryUnder: 2,
    rush: 2,
    damage: [24_000, [[SINISTER[0], 9000], [BACKSTAB, 6000], [EVISCERATE[0], 5000]]],
    interrupts: 3,
    death: false,
  },
  {
    start: 7_260_000,
    pockets: 9,
    poisons: [[INSTANT, 7], [INSTANT_II, 3], [DEADLY, 4], [CRIPPLING, 2]],
    crafted: [[DEADLY, 5]],
    locks: 5,
    lockboxes: 3,
    scouting: [DISTRACT, DISTRACT, DETECT, DISARM],
    sap: 1,
    ripostesOut: 0,
    fights: [["C", "K"], ["C", "D"], ["A", "E"], ["A", "K"], ["G", "D"], [null, "E"], [null, null], ["S", "E"]],
    pool: { SS: 8, BS: 4, HE: 3, GH: 2, GO: 2, FE: 1, SP: 3, EV: 2, BL: 1, VA: 2, KI: 3, RI: 1 },
    extra: [2, "XE"],
    flurry: 1,
    flurryUnder: 3,
    rush: 2,
    damage: [31_000, [[SINISTER[1], 11000], [BACKSTAB, 7000], [EVISCERATE[1], 6000]]],
    interrupts: 2,
    death: true,
  },
  {
    start: 14_460_000,
    pockets: 7,
    poisons: [[INSTANT, 5], [INSTANT_II, 3], [DEADLY, 4], [CRIPPLING, 1]],
    crafted: [[MIND_NUMBING, 2]],
    locks: 3,
    lockboxes: 2,
    scouting: [DISTRACT, DISTRACT, DETECT],
    sap: 0,
    ripostesOut: 0,
    fights: [["C", "E"], ["C", "K"], ["A", "X"], ["A", "E"], ["G", "R"], [null, "D"], [null, null], ["S", null]],
    pool: { SS: 9, BS: 4, HE: 3, GH: 1, GO: 2, FE: 1, SP: 2, EV: 1, BL: 1, VA: 1, KI: 3, RI: 1 },
    extra: [3, "XD"],
    flurry: 1,
    flurryUnder: 2,
    rush: 2,
    damage: [45_000, [[SINISTER[2], 15000], [BACKSTAB, 9000], [EVISCERATE[2], 7000]]],
    interrupts: 2,
    death: false,
  },
];

const SPELL_NAMES = [
  [STEALTH, "Stealth"], [OPENERS.C, "Cheap Shot"], [OPENERS.A, "Ambush"], [OPENERS.G, "Garrote"], [OPENERS.S, "Sap"],
  [EVISCERATE[0], "Eviscerate"], [EVISCERATE[1], "Eviscerate"], [EVISCERATE[2], "Eviscerate"], [SLICE, "Slice and Dice"],
  [KIDNEY, "Kidney Shot"], [RUPTURE, "Rupture"], [EXPOSE, "Expose Armor"], [SINISTER[0], "Sinister Strike"],
  [SINISTER[1], "Sinister Strike"], [SINISTER[2], "Sinister Strike"], [BACKSTAB, "Backstab"], [HEMORRHAGE, "Hemorrhage"],
  [GHOSTLY, "Ghostly Strike"], [GOUGE, "Gouge"], [FEINT, "Feint"], [SPRINT, "Sprint"], [EVASION, "Evasion"], [BLIND, "Blind"],
  [VANISH, "Vanish"], [KICK, "Kick"], [PICK_POCKET, "Pick Pocket"], [PICK_LOCK, "Pick Lock"], [DISTRACT, "Distract"],
  [DETECT, "Detect Traps"], [DISARM, "Disarm Trap"], [FLURRY, "Blade Flurry"], [RUSH, "Adrenaline Rush"], [RIPOSTE, "Riposte"],
];

const ITEM_NAMES = [
  [INSTANT, "Instant Poison"], [INSTANT_II, "Instant Poison II"], [DEADLY, "Deadly Poison"], [CRIPPLING, "Crippling Poison"],
  [MIND_NUMBING, "Mind-numbing Poison"], [LOCKBOX, "Iron Lockbox"],
];

const talentLines = () => [
  [500, "TY", ASSASSINATION, "Assassination"],
  [510, "TY", COMBAT, "Combat"],
  [520, "TY", SUBTLETY, "Subtlety"],
  [800, "TV", TREE, 4, 3, "5c1e77d2"],
  [810, "TD", TREE, 3001, 100, 200, "single", 1, COMBAT, `4001:${RIPOSTE_TALENT}:1`, ""],
  [820, "TD", TREE, 3002, 300, 200, "single", 1, COMBAT, `4002:${FLURRY_TALENT}:1`, ""],
  [830, "TD", TREE, 3003, 500, 200, "single", 1, COMBAT, `4003:${RUSH_TALENT}:1`, ""],
  [1500, "TA", 1, 3001, 4001, 1, "", 1, RIPOSTE_TALENT, COMBAT, "s"],
  [1510, "TA", 1, 3002, 4002, 1, "", 1, FLURRY_TALENT, COMBAT, "s"],
  [1520, "TA", 1, 3003, 4003, 1, "", 1, RUSH_TALENT, COMBAT, "s"],
  [2000, "TG", "s", 1, 1, "", "Combat", TREE, `${ASSASSINATION}:5:0,${COMBAT}:31:0,${SUBTLETY}:7:0`, 0, 0, 1, ""],
];

export const ROGUE_LINES = [
  [0, "S", "1.15.8", "ROGUE", "Human", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...SPELL_NAMES.map(([id, name], at) => [100 + at, "SN", id, name]),
  ...ITEM_NAMES.map(([id, name], at) => [200 + at, "IN", id, name]),
  ...talentLines(),
  ...PLANS.flatMap((plan, c) => cluster(plan.start, plan, c)),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
