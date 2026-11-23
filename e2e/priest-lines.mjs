/**
 * The session of the fixture character Candle-Warden (e2e/fixture/Candle-Warden): six hours of a priest that plays
 * levels 50, 51 and 52 for two hours each, in the open world, a dungeon and a raid, with the lines every module of the
 * Priest class page reads (docs/ROWS.md, "The class tables"): 24 fights of 30 seconds with the healing, damage and
 * absorb meters written after each, the heals and the shadow spells cast, a wand fired for 20 seconds of each fight,
 * Shadowform, shields, resurrections, control, dispels, Fortitudes with their candles, Inner Focus, Spirit of
 * Redemption, Lightwell, Power Infusion and Vampiric Embrace, one death, and the build Holy 14 / 32 / 0. Each line is
 * `[milliseconds since the session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page
 * must show are in e2e/priest-expected.mjs, written from the plan below before the page was read.
 */
import { STEP, Track, cast, ms } from "./track.mjs";

const ELWYNN = 1429;
const DEADMINES = 36;
const MOLTEN_CORE = 409;

const FLASH = 2061;
const GREATER = 2060;
const HEAL = 2054;
const RENEW = 139;
const SHIELD = 17;
const PRAYER_HEAL = 596;
const SMITE = 585;
const PAIN = 589;
const MIND_BLAST = 8092;
const HOLY_FIRE = 14914;
const MIND_FLAY = 15407;
const PLAGUE = 2944;
const FADE = 586;
const SCREAM = 8122;
const SHACKLE = 1425;
const SOOTHE = 453;
const MIND_CONTROL = 605;
const DISPEL = 527;
const CURE_DISEASE = 528;
const ABOLISH = 552;
const FORTITUDE = 1243;
const PRAYER_FORTITUDE = 21562;
const INNER_FIRE = 588;
const SHADOW_PROTECTION = 976;
const LEVITATE = 1706;
const RESURRECTION = 2006;
const SHOOT = 5019;
const SHADOWFORM = 15473;
const INNER_FOCUS = 14751;
const REDEMPTION = 20711;
const LIGHTWELL = 724;
const POWER_INFUSION = 10060;
const VAMPIRIC = 15286;
const HOLY_CANDLE = 17028;

const TREE = 5201;
const DISCIPLINE = 201;
const HOLY = 202;
const SHADOW = 203;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;
const GAP = 4;
const FIGHT_SECONDS = 30;
const WAND_SECONDS = 20;
const BUFF_SECONDS = 15;

const FIGHT_POOL = {
  FH: FLASH, GH: GREATER, RN: RENEW, PS: SHIELD, PH: PRAYER_HEAL, HE: HEAL,
  SM: SMITE, PA: PAIN, MB: MIND_BLAST, HF: HOLY_FIRE, MF: MIND_FLAY, PL: PLAGUE,
  PI: POWER_INFUSION, VE: VAMPIRIC,
};

const peer = (who, total) => ["DP", "h", who, "PRIEST", total];

function fight(k, plan, pool, first) {
  k.wait(GAP);
  const npc = k.npc();
  k.at(0, "F", 1);
  const opened = k.t;
  k.at(0.5, "AR", 1, SHOOT);
  k.at(WAND_SECONDS, "AR", 0, SHOOT, WAND_SECONDS);
  k.t = opened;
  for (const spell of pool) cast(k, spell);
  k.at(0, "K", npc, 1);
  k.t = opened + ms(FIGHT_SECONDS);
  k.lines.push([k.t, "F", 0, FIGHT_SECONDS]);

  k.at(0.2, "DM", "h", plan.heal, plan.heal / FIGHT_SECONDS, FIGHT_SECONDS);
  for (const who of plan.peers) k.at(0.05, ...peer(...who));
  for (const [spell, amount] of plan.spells) k.at(0.05, "DS", "h", spell, amount);
  k.at(0.05, "DM", "d", plan.damage, plan.damage / FIGHT_SECONDS, FIGHT_SECONDS);
  k.at(0.05, "DM", "a", plan.absorb, plan.absorb / FIGHT_SECONDS, FIGHT_SECONDS);
  if (first) k.at(0.05, "DM", "x", plan.dispels, plan.dispels / FIGHT_SECONDS, FIGHT_SECONDS);
}

function spread(items, fights) {
  const lists = Array.from({ length: fights }, () => []);
  items.forEach((item, at) => lists[at % fights].push(item));
  return lists;
}

function tokens(counts) {
  return Object.entries(counts).flatMap(([token, count]) => Array.from({ length: count }, () => FIGHT_POOL[token]));
}

function times(k, spell, count) {
  for (let at = 0; at < count; at += 1) cast(k, spell);
}

function buffed(k, spell, under, attack) {
  cast(k, spell);
  k.at(0.5, "A", 1, spell, 1, BUFF_SECONDS, 1, "");
  const gained = k.t;
  for (let at = 0; at < under; at += 1) k.at(STEP, "C", attack, "");
  k.t = gained + ms(BUFF_SECONDS);
  k.lines.push([k.t, "A", 0, spell, BUFF_SECONDS]);
}

function shadowform(k, seconds) {
  cast(k, SHADOWFORM);
  k.at(0.5, "A", 1, SHADOWFORM, 1, seconds, 1, "");
  const gained = k.t;
  k.t = gained + ms(seconds);
  k.lines.push([k.t, "A", 0, SHADOWFORM, seconds]);
}

function prepare(k, plan) {
  shadowform(k, plan.shadowform);
  k.wait(GAP);
  times(k, FORTITUDE, plan.fortitude);
  for (let at = 0; at < plan.prayer; at += 1) {
    cast(k, PRAYER_FORTITUDE);
    k.at(0.5, "I", HOLY_CANDLE, -1, "");
  }
  times(k, INNER_FIRE, plan.innerFire);
  times(k, SHADOW_PROTECTION, plan.shadowProtection);
  times(k, LEVITATE, plan.levitate);
  k.wait(GAP);
  times(k, RESURRECTION, plan.resurrection);
  k.wait(GAP);
  times(k, FADE, plan.fade);
  times(k, SCREAM, plan.scream);
  times(k, SHACKLE, plan.shackle);
  times(k, SOOTHE, plan.soothe);
  times(k, MIND_CONTROL, plan.mindControl);
  k.wait(GAP);
  times(k, DISPEL, plan.dispel);
  times(k, CURE_DISEASE, plan.cure);
  times(k, ABOLISH, plan.abolish);
  times(k, LIGHTWELL, plan.lightwell);
  times(k, POWER_INFUSION, plan.powerInfusionOut);
  k.wait(GAP);
  if (plan.innerFocus) buffed(k, INNER_FOCUS, 2, FLASH);
  if (plan.redemption) {
    k.wait(GAP);
    k.at(0, "A", 1, REDEMPTION, 1, BUFF_SECONDS, 1, "");
    const gained = k.t;
    k.at(STEP, "C", GREATER, "");
    k.at(STEP, "C", GREATER, "");
    k.t = gained + ms(BUFF_SECONDS);
    k.lines.push([k.t, "A", 0, REDEMPTION, BUFF_SECONDS]);
  }
  k.wait(GAP);
}

function cluster(start, plan) {
  const k = new Track(start);
  if (plan.zone) k.lines.push([start, ...plan.zone]);
  k.wait(1);
  prepare(k, plan);
  const pools = spread(tokens(plan.pool), plan.fights);
  pools.forEach((pool, at) => fight(k, plan, pool, at === 0));
  if (plan.death) {
    k.wait(GAP);
    k.at(4.0, "D");
    k.at(0.5, "R");
    k.at(1.5, "U");
  }
  return k.lines;
}

const SPELLS = [
  FLASH, GREATER, HEAL, RENEW, SHIELD, PRAYER_HEAL, SMITE, PAIN, MIND_BLAST, HOLY_FIRE, MIND_FLAY, PLAGUE, FADE, SCREAM, SHACKLE, SOOTHE,
  MIND_CONTROL, DISPEL, CURE_DISEASE, ABOLISH, FORTITUDE, PRAYER_FORTITUDE, INNER_FIRE, SHADOW_PROTECTION, LEVITATE, RESURRECTION, SHOOT,
  SHADOWFORM, INNER_FOCUS, REDEMPTION, LIGHTWELL, POWER_INFUSION, VAMPIRIC,
];

const NAMES = {
  [FLASH]: "Flash Heal", [GREATER]: "Greater Heal", [HEAL]: "Heal", [RENEW]: "Renew", [SHIELD]: "Power Word: Shield",
  [PRAYER_HEAL]: "Prayer of Healing", [SMITE]: "Smite", [PAIN]: "Shadow Word: Pain", [MIND_BLAST]: "Mind Blast",
  [HOLY_FIRE]: "Holy Fire", [MIND_FLAY]: "Mind Flay", [PLAGUE]: "Devouring Plague", [FADE]: "Fade", [SCREAM]: "Psychic Scream",
  [SHACKLE]: "Shackle Undead", [SOOTHE]: "Mind Soothe", [MIND_CONTROL]: "Mind Control", [DISPEL]: "Dispel Magic",
  [CURE_DISEASE]: "Cure Disease", [ABOLISH]: "Abolish Disease", [FORTITUDE]: "Power Word: Fortitude",
  [PRAYER_FORTITUDE]: "Prayer of Fortitude", [INNER_FIRE]: "Inner Fire", [SHADOW_PROTECTION]: "Shadow Protection",
  [LEVITATE]: "Levitate", [RESURRECTION]: "Resurrection", [SHOOT]: "Shoot", [SHADOWFORM]: "Shadowform",
  [INNER_FOCUS]: "Inner Focus", [REDEMPTION]: "Spirit of Redemption", [LIGHTWELL]: "Lightwell",
  [POWER_INFUSION]: "Power Infusion", [VAMPIRIC]: "Vampiric Embrace",
};

const PLANS = [
  {
    start: 60_000,
    zone: null,
    fights: 8,
    pool: { FH: 16, GH: 10, RN: 12, PS: 10, PH: 4, HE: 2, SM: 12, PA: 8, MB: 6, HF: 4, VE: 1 },
    heal: 2000,
    damage: 500,
    absorb: 300,
    dispels: 6,
    peers: [],
    spells: [[FLASH, 800], [GREATER, 500], [RENEW, 300], [PRAYER_HEAL, 250], [HEAL, 150]],
    shadowform: 1800,
    fortitude: 8,
    prayer: 2,
    innerFire: 4,
    shadowProtection: 1,
    levitate: 1,
    resurrection: 4,
    fade: 6,
    scream: 4,
    shackle: 2,
    soothe: 1,
    mindControl: 1,
    dispel: 4,
    cure: 2,
    abolish: 1,
    lightwell: 0,
    powerInfusionOut: 0,
    innerFocus: false,
    redemption: false,
    death: false,
  },
  {
    start: 7_260_000,
    zone: ["Z", "", DEADMINES, "party", 1, "The Deadmines", ""],
    fights: 8,
    pool: { FH: 14, GH: 14, RN: 10, PS: 12, PH: 6, SM: 10, PA: 8, MB: 6, HF: 3, MF: 4, PI: 1, VE: 1 },
    heal: 3000,
    damage: 700,
    absorb: 400,
    dispels: 5,
    peers: [["Aide-Healer", 3000], ["Aide-Mender", 3000]],
    spells: [[FLASH, 1100], [GREATER, 900], [RENEW, 450], [PRAYER_HEAL, 350], [HEAL, 200]],
    shadowform: 1200,
    fortitude: 6,
    prayer: 3,
    innerFire: 4,
    shadowProtection: 1,
    levitate: 1,
    resurrection: 6,
    fade: 6,
    scream: 3,
    shackle: 2,
    soothe: 1,
    mindControl: 1,
    dispel: 3,
    cure: 1,
    abolish: 1,
    lightwell: 1,
    powerInfusionOut: 0,
    innerFocus: false,
    redemption: true,
    death: true,
  },
  {
    start: 14_460_000,
    zone: ["Z", "", MOLTEN_CORE, "raid", 1, "Molten Core", ""],
    fights: 8,
    pool: { FH: 12, GH: 16, RN: 8, PS: 14, PH: 8, SM: 8, PA: 8, MB: 6, HF: 3, MF: 6, PL: 4, PI: 2, VE: 2 },
    heal: 4000,
    damage: 900,
    absorb: 500,
    dispels: 4,
    peers: [["Aide-Healer", 4000], ["Aide-Mender", 4000]],
    spells: [[FLASH, 1500], [GREATER, 1400], [RENEW, 500], [PRAYER_HEAL, 400], [HEAL, 200]],
    shadowform: 600,
    fortitude: 6,
    prayer: 3,
    innerFire: 4,
    shadowProtection: 1,
    levitate: 0,
    resurrection: 5,
    fade: 6,
    scream: 3,
    shackle: 0,
    soothe: 1,
    mindControl: 0,
    dispel: 3,
    cure: 1,
    abolish: 0,
    lightwell: 1,
    powerInfusionOut: 1,
    innerFocus: true,
    redemption: false,
    death: false,
  },
];

const talentLines = () => [
  [500, "TY", DISCIPLINE, "Discipline"],
  [510, "TY", HOLY, "Holy"],
  [520, "TY", SHADOW, "Shadow"],
  [800, "TV", TREE, 4, 3, "7a3d90b1"],
  [810, "TD", TREE, 3101, 100, 200, "single", 1, DISCIPLINE, `4101:${INNER_FOCUS}:1`, ""],
  [820, "TD", TREE, 3102, 300, 200, "single", 1, HOLY, `4102:${REDEMPTION}:1`, ""],
  [830, "TD", TREE, 3103, 500, 200, "single", 1, HOLY, `4103:${LIGHTWELL}:1`, ""],
  [1500, "TA", 1, 3101, 4101, 1, "", 1, INNER_FOCUS, DISCIPLINE, "s"],
  [1510, "TA", 1, 3102, 4102, 1, "", 1, REDEMPTION, HOLY, "s"],
  [1520, "TA", 1, 3103, 4103, 1, "", 1, LIGHTWELL, HOLY, "s"],
  [2000, "TG", "s", 1, 1, "", "Holy", TREE, `${DISCIPLINE}:14:0,${HOLY}:32:0,${SHADOW}:0:0`, 0, 0, 1, ""],
];

export const PRIEST_LINES = [
  [0, "S", "1.15.8", "PRIEST", "Human", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...SPELLS.map((id, at) => [100 + at, "SN", id, NAMES[id]]),
  [200, "IN", HOLY_CANDLE, "Holy Candle"],
  ...talentLines(),
  ...PLANS.flatMap((plan) => cluster(plan.start, plan)),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
