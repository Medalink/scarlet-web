/**
 * The session of the fixture character Iron-Vanguard (e2e/fixture/Iron-Vanguard): six hours of a warrior that plays
 * levels 50, 51 and 52 for two hours each, with the lines every module of the Warrior class page reads (docs/ROWS.md,
 * "The class tables"): fights opened with Charge, Intercept, Bloodrage, Throw, another spell and nothing, the three
 * stances, Execute kills, Battle Shout and the other shouts, three weapons and a shield in the hands, the damage meter,
 * the party spells, three deaths, and the talents Mortal Strike, Sweeping Strikes and Enrage. Each line is
 * `[milliseconds since the session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page
 * must show are worked out from these lines by e2e/warrior-expected.mjs.
 */

import { GAP, HOUR, Track, nameLines, sessionStart, talentLines } from "./lines-kit.mjs";

const CHARGE = 11578;
const INTERCEPT = 20252;
const EXECUTE = 20662;
const SUNDER = 11597;
const BATTLE_SHOUT = 11551;
const DEMORALIZING = 11556;
const INTIMIDATING = 5246;
const CHALLENGING = 1161;
const PIERCING = 12323;
const TAUNT = 355;
const MOCKING = 20560;
const HEROIC = 11567;
const MORTAL = 21553;
const BLOODTHIRST = 23881;
const REND = 11574;
const REVENGE = 11601;
const OVERPOWER = 11585;
const THUNDER = 11581;
const HAMSTRING = 9080;
const CLEAVE = 11608;
const SLAM = 11605;
const WHIRLWIND = 1680;
const SHIELD_BLOCK = 2565;
const PUMMEL = 6552;
const SHIELD_BASH = 72;
const BLOODRAGE = 2687;
const THROW = 2764;
const SWEEPING = 12292;
const DEATH_WISH = 12328;
const ENRAGE_AURA = 12880;

const BATTLE = 2457;
const DEFENSIVE = 71;
const BERSERKER = 2458;

const MORTAL_TALENT = 12294;
const ENRAGE_TALENT = 12317;

const AXE = 12784;
const SWORD_ONE = 12940;
const SWORD_TWO = 13361;
const SHIELD = 12602;

const TREE = 1001;
const ARMS = 161;
const FURY = 164;
const PROTECTION = 163;

const SESSION_END = 6 * HOUR;
const seconds = (from, to) => Number(((to - from) / 1000).toFixed(3));

const cast = (k, spell, cast_seconds = "") => k.at(0, "C", spell, cast_seconds);

function stance(k, id) {
  if (k.stance?.id === id) return;
  cast(k, id);
  if (k.stance) k.at(0.1, "A", 0, k.stance.id, seconds(k.stance.since, k.t + 100));
  k.at(0.1, "A", 1, id, 1, 0, 1, "");
  k.stance = { id, since: k.t };
}

function shout(k) {
  if (k.shout !== undefined) return;
  cast(k, BATTLE_SHOUT);
  k.at(0.1, "A", 1, BATTLE_SHOUT, 1, 120, 1, "");
  k.shout = k.t;
}

function endShout(k) {
  if (k.shout === undefined) return;
  k.at(3, "A", 0, BATTLE_SHOUT, seconds(k.shout, k.t + 3000));
  k.shout = undefined;
}

function buff(k, [spell, casts, duration]) {
  if (casts) cast(k, spell);
  k.at(0.2, "A", 1, spell, 1, duration, 1, "");
  const since = k.t;
  return () => k.at(0, "A", 0, spell, seconds(since, k.t));
}

function fight(k, script) {
  k.wait(GAP);
  if (script.stance) stance(k, script.stance);
  if (script.shout) shout(k);
  k.wait(3);
  const opened = k.t;
  k.at(0, "F", 1);
  if (script.open === null) k.wait(5);
  if (script.open !== null) {
    k.wait(0.5);
    cast(k, script.open);
  }
  let ends = null;
  script.steps.forEach((step, at) => {
    k.wait(1.5);
    cast(k, step);
    if (at === (script.buff?.[3] ?? 0) && script.buff) ends = buff(k, script.buff);
    if (at === 0 && script.mid) {
      k.wait(0.3);
      stance(k, script.mid);
    }
  });
  const npc = k.npc();
  if (script.kill === "party") k.at(1.0, "K", npc, 0);
  if (script.kill === "plain" || script.kill === "execute") k.at(1.0, "K", npc, 1);
  ends?.();
  k.at(1.0, "F", 0, seconds(opened, k.t + 1000));
  const length = seconds(opened, k.t);
  k.at(1.5, "DM", "d", script.dealt, Number((script.dealt / length).toFixed(1)), length);
  k.at(0.2, "DM", "t", script.taken, Number((script.taken / length).toFixed(1)), length);
  for (const [spell, amount] of script.top ?? []) k.at(0.2, "DS", "d", spell, amount);
  if (script.interrupts) k.at(0.2, "DM", "i", script.interrupts, 0, length);
  if (script.shoutEnds) endShout(k);
}

function closeStance(k) {
  if (k.stance) k.at(1, "A", 0, k.stance.id, seconds(k.stance.since, k.t + 1000));
  k.stance = undefined;
}

const dies = (k, after = 30) => {
  k.wait(after);
  k.at(0, "D");
  k.at(2, "U");
  closeStance(k);
  endShout(k);
};

const fightOf = (open, steps, spec) => ({ open, steps, kill: "plain", dealt: 5000, taken: 2200, ...spec });

const CLUSTERS = [
  {
    start: 60_000,
    fights: [
      fightOf(CHARGE, [HEROIC, REND, EXECUTE], { kill: "execute", stance: BATTLE, shout: true, dealt: 5200, taken: 1800, top: [[HEROIC, 2400], [REND, 1100]] }),
      fightOf(CHARGE, [HEROIC, THUNDER, HAMSTRING, HEROIC], { buff: [SWEEPING, true, 10], dealt: 6100, taken: 2100, top: [[HEROIC, 3000], [THUNDER, 900]] }),
      fightOf(THROW, [HEROIC, OVERPOWER, MORTAL], { dealt: 3900, taken: 1500, top: [[MORTAL, 2000]] }),
      fightOf(null, [HEROIC, THUNDER, CLEAVE], { dealt: 4100, taken: 1700 }),
      fightOf(CHARGE, [HEROIC, REND, MORTAL, EXECUTE], { kill: "execute", buff: [ENRAGE_AURA, false, 12], dealt: 6700, taken: 2000, top: [[MORTAL, 2800], [HEROIC, 2200]], shoutEnds: true }),
      fightOf(TAUNT, [PUMMEL, SUNDER, SUNDER, REVENGE, SHIELD_BLOCK], { stance: DEFENSIVE, dealt: 2600, taken: 4100, interrupts: 1 }),
    ],
    after: (k) => dies(k),
  },
  {
    start: 7_260_000,
    fights: [
      fightOf(BLOODRAGE, [SUNDER, SUNDER, REVENGE, TAUNT, SHIELD_BLOCK], { stance: DEFENSIVE, shout: true, buff: [ENRAGE_AURA, false, 12], dealt: 2800, taken: 5200, top: [[SUNDER, 600]] }),
      fightOf(null, [PUMMEL, SUNDER, REVENGE, MOCKING], { buff: [ENRAGE_AURA, false, 12], dealt: 2400, taken: 6300, interrupts: 1 }),
      fightOf(CHARGE, [HEROIC, SLAM, EXECUTE], { stance: BATTLE, kill: "execute", buff: [SWEEPING, true, 10], dealt: 5600, taken: 2300, top: [[SLAM, 1900], [HEROIC, 1500]] }),
      fightOf(CHARGE, [SHIELD_BASH, HEROIC, REND, REVENGE, THUNDER], { mid: DEFENSIVE, dealt: 4800, taken: 2700, shoutEnds: true, interrupts: 1 }),
      fightOf(THROW, [SUNDER, SUNDER, REVENGE, REVENGE, SHIELD_BLOCK], { stance: DEFENSIVE, dealt: 2900, taken: 5500 }),
      fightOf(INTERCEPT, [DEMORALIZING, SUNDER, REVENGE], { kill: "party", dealt: 3000, taken: 4600 }),
    ],
    after: (k) => dies(k),
  },
  {
    start: 14_460_000,
    fights: [
      fightOf(INTERCEPT, [WHIRLWIND, BLOODTHIRST, WHIRLWIND, BLOODTHIRST], { stance: BERSERKER, shout: true, buff: [ENRAGE_AURA, false, 12], dealt: 8300, taken: 3400, top: [[BLOODTHIRST, 3900], [WHIRLWIND, 2100]] }),
      fightOf(INTERCEPT, [WHIRLWIND, BLOODTHIRST, EXECUTE], { kill: "execute", dealt: 9100, taken: 3000, top: [[BLOODTHIRST, 4300], [WHIRLWIND, 2200]] }),
      fightOf(BLOODRAGE, [WHIRLWIND, BLOODTHIRST, HEROIC], { dealt: 7600, taken: 3500, shoutEnds: true }),
      fightOf(CHARGE, [HEROIC, DEMORALIZING, INTIMIDATING, CHALLENGING, PIERCING], { stance: BATTLE, dealt: 5000, taken: 2600 }),
      fightOf(CHARGE, [HEROIC, PUMMEL, CLEAVE, REND], { mid: BERSERKER, dealt: 5400, taken: 2400 }),
      fightOf(null, [HEROIC, OVERPOWER, DEATH_WISH], { kill: "party", buff: [DEATH_WISH, false, 30, 2], dealt: 4700, taken: 2500 }),
    ],
    after: (k) => dies(k, 0.5),
  },
];

function cluster(plan) {
  const k = new Track(plan.start);
  plan.fights.forEach((script) => fight(k, script));
  plan.after(k);
  return k.lines;
}

const SPELL_NAMES = [
  [CHARGE, "Charge"], [INTERCEPT, "Intercept"], [EXECUTE, "Execute"], [SUNDER, "Sunder Armor"], [BATTLE_SHOUT, "Battle Shout"],
  [DEMORALIZING, "Demoralizing Shout"], [INTIMIDATING, "Intimidating Shout"], [CHALLENGING, "Challenging Shout"], [PIERCING, "Piercing Howl"],
  [TAUNT, "Taunt"], [MOCKING, "Mocking Blow"], [HEROIC, "Heroic Strike"], [MORTAL, "Mortal Strike"], [BLOODTHIRST, "Bloodthirst"],
  [REND, "Rend"], [REVENGE, "Revenge"], [OVERPOWER, "Overpower"], [THUNDER, "Thunder Clap"], [HAMSTRING, "Hamstring"], [CLEAVE, "Cleave"],
  [SLAM, "Slam"], [WHIRLWIND, "Whirlwind"], [SHIELD_BLOCK, "Shield Block"], [PUMMEL, "Pummel"], [SHIELD_BASH, "Shield Bash"],
  [BLOODRAGE, "Bloodrage"], [THROW, "Throw"], [SWEEPING, "Sweeping Strikes"], [DEATH_WISH, "Death Wish"], [ENRAGE_AURA, "Enrage"],
  [BATTLE, "Battle Stance"], [DEFENSIVE, "Defensive Stance"], [BERSERKER, "Berserker Stance"],
  [MORTAL_TALENT, "Mortal Strike"], [ENRAGE_TALENT, "Enrage"],
];

const ITEM_NAMES = [[AXE, "Arcanite Reaper"], [SWORD_ONE, "Dal'Rend's Sacred Charge"], [SWORD_TWO, "Skullforge Reaver"], [SHIELD, "Draconian Deflector"]];

const ITEM_INFOS = [[AXE, 2, 1, "INVTYPE_2HWEAPON"], [SWORD_ONE, 2, 7, "INVTYPE_WEAPON"], [SWORD_TWO, 2, 8, "INVTYPE_2HWEAPON"], [SHIELD, 4, 6, "INVTYPE_SHIELD"]];

const GEAR = [
  [62_000, "G", 16, AXE, "", "", ""],
  [1.5 * HOUR, "G", 16, SWORD_ONE, "", "", ""],
  [1.5 * HOUR + 100, "G", 17, SHIELD, "", "", ""],
  [3.5 * HOUR, "G", 16, SWORD_TWO, "", "", ""],
  [3.5 * HOUR + 100, "G", 17, 0, "", "", ""],
];

const talents = () => talentLines({
  tree: TREE,
  classId: 1,
  hash: "1c3d52e7",
  spec: "Arms",
  tabs: [[ARMS, "Arms"], [FURY, "Fury"], [PROTECTION, "Protection"]],
  nodes: [
    { node: 1001, spell: MORTAL_TALENT, ranks: 1, held: 1, tab: ARMS },
    { node: 1002, spell: SWEEPING, ranks: 1, held: 1, tab: ARMS },
    { node: 1003, spell: ENRAGE_TALENT, ranks: 5, held: 5, tab: FURY },
  ],
  spent: [31, 20, 0],
});

export const WARRIOR_LINES = [
  ...sessionStart("WARRIOR", "Human", 50, nameLines({ spells: SPELL_NAMES, items: ITEM_NAMES, infos: ITEM_INFOS })),
  ...talents(),
  ...GEAR,
  ...CLUSTERS.flatMap(cluster),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
