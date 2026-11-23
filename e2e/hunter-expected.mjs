/**
 * What the class page of the Hunter fixture (e2e/hunter-lines.mjs) must show, worked out from the lines the fixture
 * writes with e2e/lines-facts.mjs and not from the decoder's rows or the page. Each entry is a label and the value
 * written next to it; a callout and a talent module are their lines in order.
 */

import { HUNTER_LINES } from "./hunter-lines.mjs";
import { decimal, hours, number, percent, readLines } from "./lines-facts.mjs";

const facts = readLines(HUNTER_LINES);
const casts = (...names) => facts.castCount(...names);
const HOUR = 3_600_000;
const fightCount = facts.fights.length;
const fightMs = facts.fightMs();
const TRAPS = ["Freezing Trap", "Immolation Trap", "Explosive Trap", "Frost Trap", "Snake Trap"];
const SHOTS = ["Arcane Shot", "Multi-Shot", "Serpent Sting", "Concussive Shot", "Aimed Shot", "Hunter's Mark"];
const ASPECTS = ["Aspect of the Hawk", "Aspect of the Monkey", "Aspect of the Cheetah", "Aspect of the Pack", "Aspect of the Wild", "Aspect of the Beast"];
const FAMILIES = ["Cat", "Wolf", "Boar", "Owl"];

const ranked = (names) => names.map((name) => [name, casts(name)]).filter(([, count]) => count > 0).sort((a, b) => b[1] - a[1]);
const rows = (list) => list.map(([name, count]) => [name, number(count)]);

const petHours = facts.petMsIn() / HOUR;
const byName = new Map();
for (const segment of facts.petSegments()) {
  const held = byName.get(segment.name) ?? { family: segment.family, ms: 0 };
  held.ms += segment.end - segment.t;
  byName.set(segment.name, held);
}
const pets = [...byName].sort((a, b) => b[1].ms - a[1].ms);
const fired = facts.firedShots();
const feign = casts("Feign Death");

export const HEAD = [
  ["Hours with a pet out", hours(petHours)],
  ["Tame Beast casts", number(casts("Tame Beast"))],
  ["Shots fired", number(fired)],
  ["Hours of Auto Shot", hours(facts.autoShotMs() / HOUR)],
  ["Traps set", number(casts(...TRAPS))],
  ["Feign Deaths", number(feign)],
];

export const BAND = [
  ["Pet out most", pets[0][0]],
  ["Hours with a pet out", hours(petHours)],
  ["Shots fired", number(fired)],
  ["Tame Beast casts", number(casts("Tame Beast"))],
  ["Feign Deaths", number(feign)],
];

export const CALLOUTS = FAMILIES.filter((family) => facts.petMsIn(family) > 0).map((family) => {
  const own = pets.filter(([, held]) => held.family === family);
  return [own[0][0], `${family}, out ${hours(facts.petMsIn(family) / HOUR)} hours`, `${percent((facts.petMsIn(family) / facts.petMsIn()) * 100)} of pet time`];
});

export const QUIVER = [
  ["Shots fired", number(fired)],
  ["Shots a fight", decimal(fired / fightCount)],
  ["Ammo bought", number(facts.ammoIn("v"))],
];

export const AMMO_WORDS = ["Bought from vendors", "Crafted", "Looted", "Mailed or traded in", "Sharp Arrow", "Razor Arrow", "Jagged Arrow", "Sold, traded or destroyed", "In the quiver now", "Stacks of 200 in the bags now, counted from the changes the addon saw."];

export const PETS = [
  ...pets.map(([name, held]) => [name, `${hours(held.ms / HOUR)} h`]),
  ["Fed", number(casts("Feed Pet"))],
  ["Revived", number(casts("Revive Pet"))],
  ["Happy while out", percent((facts.happyMs() / facts.petMsIn()) * 100)],
];

const petLeaders = [50, 51, 52].map((level) => {
  const whole = facts.levelWindow(level).end - facts.levelWindow(level).start;
  return [level, FAMILIES.find((family) => facts.petMsIn(family, level) / whole >= 0.5)];
});
const owlRun = petLeaders.filter(([, lead]) => lead === "Owl");

export const PET_WORDS = ["Cat", "Wolf", "Boar", "Owl", "Another pet", "No pet out", `The Owl was out for most of every level from ${owlRun[0][0]} to ${owlRun.at(-1)[0]}.`];

const aspectLeaders = [50, 51, 52].map((level) => {
  const whole = facts.levelWindow(level).end - facts.levelWindow(level).start;
  return [level, ASPECTS.find((name) => facts.auraMsInLevel(name, level) / whole >= 0.5)];
});
const hawkRun = aspectLeaders.filter(([, lead]) => lead === "Aspect of the Hawk");

export const ASPECT_WORDS = ["Hawk", "Monkey", "Pack", "No aspect", `Aspect of the Hawk was up for most of every level from ${hawkRun[0][0]} to ${hawkRun.at(-1)[0]}.`];

export const SHOT_RANK = rows(ranked(SHOTS));

export const SHOT_NOTE = `Auto Shot ran for ${percent((facts.autoShotFightMs() / fightMs) * 100)} of fight time.`;

export const TRAP_RANK = rows(ranked(TRAPS));

export const FEIGN = [
  ["Casts", number(feign)],
  ["In combat", percent((facts.fightCasts("Feign Death") / feign) * 100)],
  ["Deaths within 5 seconds of one", number(facts.deathsAfter(["Feign Death"], 5))],
];

export const FEIGN_WORDS = ["Lived", "Died within 5 seconds"];

export const TRACKING = [...facts.tracking()].sort((a, b) => b[1] - a[1]).map(([label, ms]) => [label, `${hours(ms / HOUR)} h`]);

const used = (name) => percent((facts.fightCasts(name) / casts(name)) * 100);
const shotTotal = casts(...SHOTS);

export const TALENTS = [
  ["Casts", number(casts("Bestial Wrath")), "Used in a fight", used("Bestial Wrath"), "Casts a fight", decimal(casts("Bestial Wrath") / fightCount)],
  ["Casts", number(casts("Intimidation")), "Used in a fight", used("Intimidation"), "Casts a fight", decimal(casts("Intimidation") / fightCount)],
  ["Casts", number(casts("Aimed Shot")), "Of your shots", percent((casts("Aimed Shot") / shotTotal) * 100), "Killed within 3 seconds", percent((facts.killBlowsAfter("Aimed Shot") / casts("Aimed Shot")) * 100)],
];

export const PARTY = [
  ["Trueshot Aura", hours(facts.auraMs("Trueshot Aura") / HOUR)],
  ["Aspect of the Pack", hours(facts.auraMs("Aspect of the Pack") / HOUR)],
  ["Packs cast", number(casts("Aspect of the Pack"))],
  ["Casts", number(casts("Freezing Trap"))],
  ["Casts a played hour", decimal(casts("Freezing Trap") / (facts.end / HOUR))],
  ["Set in a fight", percent((facts.fightCasts("Freezing Trap") / casts("Freezing Trap")) * 100)],
  ["Tranquilizing Shots", number(casts("Tranquilizing Shot"))],
  ["Mend Pets", number(casts("Mend Pet"))],
];
