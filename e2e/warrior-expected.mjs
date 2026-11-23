/**
 * What the class page of the Warrior fixture (e2e/warrior-lines.mjs) must show, worked out from the lines the fixture
 * writes with e2e/lines-facts.mjs and not from the decoder's rows or the page. Each entry is a label and the value
 * written next to it; a callout is its lines in order.
 */

import { WARRIOR_LINES } from "./warrior-lines.mjs";
import { decimal, hours, number, percent, readLines } from "./lines-facts.mjs";

const facts = readLines(WARRIOR_LINES);
const casts = (...names) => facts.castCount(...names);
const fightMs = facts.fightMs();
const fightShare = (ms) => percent((ms / fightMs) * 100);
const playedHours = facts.end / 3_600_000;
const STANCES = ["Battle Stance", "Defensive Stance", "Berserker Stance"];
const ATTACKS = ["Heroic Strike", "Mortal Strike", "Bloodthirst", "Rend", "Revenge", "Overpower", "Thunder Clap", "Hamstring", "Cleave", "Slam", "Whirlwind", "Shield Slam", "Concussion Blow"];
const SHOUTS = ["Battle Shout", "Demoralizing Shout", "Intimidating Shout", "Challenging Shout", "Piercing Howl"];
const HOLDING = ["Taunt", "Sunder Armor", "Shield Block", "Revenge", "Mocking Blow"];
const INTERRUPTS = ["Pummel", "Shield Bash"];

const ranked = (names) => names.map((name) => [name, casts(name)]).filter(([, count]) => count > 0).sort((a, b) => b[1] - a[1]);
const rows = (list) => list.map(([name, count]) => [name, number(count)]);

const charges = casts("Charge", "Intercept");
const executes = casts("Execute");
const executeKills = facts.killBlowsAfter("Execute");
const executeShare = (executeKills / facts.killBlows()) * 100;
const stanceMs = (name) => facts.auraFightMs(name);
const held = STANCES.map((name) => [name, facts.auraMs(name)]).sort((a, b) => b[1] - a[1])[0][0].replace(" Stance", "");
const changes = casts(...STANCES);
const changesInFights = facts.casts.filter((cast) => STANCES.includes(cast.name) && facts.inFight(cast.t)).length;
const shout = casts("Battle Shout");
const shoutUp = (facts.auraFightMs("Battle Shout") / fightMs) * 100;
const landed = facts.meterTotal("i");
const interruptCasts = casts(...INTERRUPTS);

export const HEAD = [
  ["Charges", number(charges)],
  ["Executes", number(executes)],
  ["Sunder Armors", number(casts("Sunder Armor"))],
  ["Battle Shouts", number(shout)],
  ["Taunts", number(casts("Taunt"))],
  ["Deaths", number(facts.deaths.length)],
];

export const BAND = [
  ["Charges", number(charges)],
  ["Executes", number(executes)],
  ["Stance held most", held],
  ["Battle Shouts", number(shout)],
  ["Deaths", number(facts.deaths.length)],
];

const attacks = ranked(ATTACKS);
const attackTotal = attacks.reduce((sum, [, count]) => sum + count, 0);
const mainHand = facts.gear(16);
const kindOf = (item) => ({ 12784: "Two-Handed Axes", 12940: "One-Handed Swords", 13361: "Two-Handed Swords" })[item];
const byType = new Map();
for (const [item, held] of mainHand) {
  const type = kindOf(item);
  const total = byType.get(type) ?? { ms: 0, fightMs: 0 };
  total.ms += held.ms;
  total.fightMs += held.fightMs;
  byType.set(type, total);
}
const topType = [...byType].sort((a, b) => b[1].ms - a[1].ms)[0];
const shield = facts.gear(17).get(12602);

export const CALLOUTS = [
  [attacks[0][0], "The attack used most", `${percent((attacks[0][1] / attackTotal) * 100)} of attacks`],
  [topType[0], `In the main hand for ${hours(topType[1].ms / 3_600_000)} hours`, `${fightShare(topType[1].fightMs)} of fight time`],
  ["Shield", `On the arm for ${hours(shield.ms / 3_600_000)} hours`, `${fightShare(shield.fightMs)} of fight time`],
];

export const ATTACKS_USED = rows(attacks.slice(0, 9));

export const EXECUTE = [
  ["Casts", number(executes)],
  ["Kills within 3 seconds of one", number(executeKills)],
  ["Share of kills finished by one", percent(executeShare)],
];

export const STANCE = [
  ["Stance changes", number(changes)],
  ["Changes a fight", decimal(changes / facts.fights.length)],
  ["Changed in a fight", percent((changesInFights / changes) * 100)],
];

const stanceShares = STANCES.map((name) => (stanceMs(name) / fightMs) * 100);
const noStance = 100 - stanceShares.reduce((sum, share) => sum + share, 0);

export const STANCE_SHARES = [...STANCES.map((name, at) => `${name} ${percent(stanceShares[at])}`), ...(stanceShares.reduce((sum, share) => sum + share, 0) < 99.5 ? [`No stance ${percent(noStance)}`] : [])];

const GROUPS = { Charge: "Charge", Intercept: "Intercept", Bloodrage: "Bloodrage", Throw: "A ranged pull" };
const opened = new Map();
for (const name of facts.firstCasts()) {
  const group = name === null ? "Nothing in the first 3 seconds" : (GROUPS[name] ?? "Another spell");
  opened.set(group, (opened.get(group) ?? 0) + 1);
}

export const OPENS = [...opened].map(([group, count]) => [group, percent((count / facts.fights.length) * 100)]);

export const SHOUT_RANK = [...rows(ranked(SHOUTS))];

export const SHOUT_UP = `Battle Shout was up for ${percent(shoutUp)} of fight time.`;

export const PARTY = [
  ...rows(ranked(HOLDING)),
  ...rows(ranked(INTERRUPTS)),
  ["Interrupts landed", number(landed)],
  ["Landed of casts", percent(Math.min(100, (landed / interruptCasts) * 100))],
  ["Casts", number(shout)],
  ["Casts a played hour", decimal(shout / playedHours)],
  ["Up in fights", percent(shoutUp)],
];

const dealt = facts.meterTotal("d");
const shown = facts.meterSpells("d").slice(0, 6);

export const DAMAGE_BY_SPELL = [
  ...shown.map(([name, amount]) => [name, percent((amount / dealt) * 100)]),
  ["Every other spell", percent(((dealt - shown.reduce((sum, [, amount]) => sum + amount, 0)) / dealt) * 100)],
];

const upShare = (name) => (facts.auraFightMs(name) / fightMs) * 100;
const mortalDamage = facts.meterSpells("d").find(([name]) => name === "Mortal Strike")[1];
const mortalCasts = casts("Mortal Strike");

export const TALENTS = [
  ["Casts", number(mortalCasts), "Of your damage", percent((mortalDamage / dealt) * 100), "Killed within 3 seconds", percent((facts.killBlowsAfter("Mortal Strike") / mortalCasts) * 100)],
  ["Casts", number(casts("Sweeping Strikes")), "Of fight time it was up", percent(upShare("Sweeping Strikes")), "Attacks under it", number(facts.castsUnder("Sweeping Strikes"))],
  ["Times enraged", number(facts.auraStarts("Enrage")), "Of fight time it was up", percent(upShare("Enrage")), "Attacks under it", number(facts.castsUnder("Enrage"))],
];
