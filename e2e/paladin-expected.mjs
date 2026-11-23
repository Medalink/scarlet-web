/**
 * What the class page of the Paladin fixture (e2e/paladin-lines.mjs) must show, worked out from the lines the fixture
 * writes with e2e/lines-facts.mjs and not from the decoder's rows or the page. Each entry is a label and the value
 * written next to it; a callout and a talent module are their lines in order.
 */

import { PALADIN_LINES } from "./paladin-lines.mjs";
import { decimal, hours, number, percent, readLines } from "./lines-facts.mjs";

const facts = readLines(PALADIN_LINES);
const casts = (...names) => facts.castCount(...names);
const playedHours = facts.end / 3_600_000;
const BLESSINGS = ["Blessing of Might", "Blessing of Wisdom", "Blessing of Kings", "Blessing of Salvation", "Blessing of Light", "Blessing of Sanctuary", "Blessing of Protection", "Blessing of Freedom", "Blessing of Sacrifice"];
const GREATER = ["Greater Blessing of Might", "Greater Blessing of Wisdom", "Greater Blessing of Kings", "Greater Blessing of Salvation", "Greater Blessing of Light", "Greater Blessing of Sanctuary"];
const SHIELDS = ["Divine Shield", "Divine Protection"];

const ranked = (names) => names.map((name) => [name, casts(name)]).filter(([, count]) => count > 0).sort((a, b) => b[1] - a[1]);
const rows = (list) => list.map(([name, count]) => [name, number(count)]);

const blessings = ranked([...BLESSINGS, ...GREATER]);
const blessingTotal = blessings.reduce((sum, [, count]) => sum + count, 0);
const judgements = casts("Judgement");
const bubbles = casts(...SHIELDS);
const hearths = facts.castsUnderAuras(SHIELDS, "Hearthstone");
const fates = facts.sealFates();
const fateCount = (fate, name) => fates.filter((one) => (fate === undefined || one.fate === fate) && (name === undefined || one.name === name)).length;
const judgedShare = (fateCount(1) / fates.length) * 100;
const healTotal = facts.meterTotal("h");
const damageTotal = facts.meterTotal("d");
const fightCount = facts.fights.length;
const perHour = (count) => count / playedHours;

export const HEAD = [
  ["Judgements", number(judgements)],
  ["Blessings cast", number(blessingTotal)],
  ["Lay on Hands", number(casts("Lay on Hands"))],
  ["Divine Shields", number(casts("Divine Shield"))],
  ["Hammers of Justice", number(casts("Hammer of Justice"))],
  ["Players raised", number(casts("Redemption"))],
];

export const BAND = [
  ["Judgements", number(judgements)],
  ["Blessings cast", number(blessingTotal)],
  ["Blessing cast most", blessings[0][0]],
  ["Divine Shields", number(casts("Divine Shield"))],
  ["Players raised", number(casts("Redemption"))],
];

export const CALLOUTS = [
  ["Warhorse", `Ridden for ${hours(facts.rideMs() / 3_600_000)} hours`, `Summoned ${number(casts("Summon Warhorse"))} times`],
  [blessings[0][0], "The blessing cast most", `${percent((blessings[0][1] / blessingTotal) * 100)} of blessings`],
  ["Divine Shield", `${number(bubbles)} bubbles`, `${number(hearths)} of them left with a Hearthstone`],
];

const judgedMs = fates.filter((one) => one.fate === 1).reduce((sum, one) => sum + one.ms, 0);

export const JUDGEMENT = [
  ["Judgements", number(judgements)],
  ["Seconds from seal to judgement", decimal(judgedMs / fateCount(1) / 1000)],
  ["Judgements a fight", decimal(judgements / fightCount)],
];

export const SEAL_WORDS = [
  ...new Set(fates.map((one) => one.name)),
  "Judged",
  "Ran out unjudged",
  "Replaced by another seal",
  "Still up when the fight ended",
  "Ended another way",
  `${number(fateCount(3))} seals ran out unjudged, more than the ${number(fateCount(2))} replaced by another seal.`,
];

const AURAS = ["Devotion Aura", "Retribution Aura", "Concentration Aura", "Shadow Resistance Aura"];
const leaders = [50, 51, 52].map((level) => {
  const window = facts.levelWindow(level);
  const lead = AURAS.find((name) => facts.auraMsInLevel(name, level) / (window.end - window.start) >= 0.5);
  return [level, lead];
});

export const AURA_WORDS = [
  "Devotion Aura",
  "Retribution Aura",
  "Concentration Aura",
  "The resistance auras",
  "No aura",
  `Retribution Aura was up for most of every level from ${leaders.filter(([, lead]) => lead === "Retribution Aura")[0][0]} to ${leaders.filter(([, lead]) => lead === "Retribution Aura").at(-1)[0]}.`,
];

export const BLESSING_RANK = rows(blessings.slice(0, 9));

export const BLESSING_NOTE = `${number(facts.itemOut(21177))} Symbols of Kings left the bags. Who was blessed is not recorded.`;

export const UNDEAD = rows(ranked(["Exorcism", "Holy Wrath", "Turn Undead", "Consecration", "Hammer of Wrath", "Holy Shield"]));

export const BUBBLE = [
  ["Divine Shields", number(casts("Divine Shield"))],
  ["Divine Protections", number(casts("Divine Protection"))],
  ["Hearthstones used inside one", number(hearths)],
];

export const BUBBLE_WORDS = [`${number(facts.deathsAfterAura(SHIELDS, 10))} died within 10 seconds of one ending`];

export const LAY_ON_HANDS = [
  ["Casts", number(casts("Lay on Hands"))],
  ["Used in a fight", percent((facts.fightCasts("Lay on Hands") / casts("Lay on Hands")) * 100)],
  ["Deaths within 10 seconds of one", number(facts.deathsAfter(["Lay on Hands"], 10))],
];

const healed = facts.meterSpells("h").filter(([name]) => ["Holy Light", "Flash of Light", "Holy Shock"].includes(name));

export const HEALS = rows(ranked(["Holy Light", "Flash of Light", "Holy Shock"]));

export const HEALS_NOTE = `The healing meter gave ${healed.map(([name, amount]) => `${name} ${percent((amount / healTotal) * 100)}`).join(", ")} of the healing.`;

export const CONTROL = [...rows(ranked(["Hammer of Justice", "Repentance", "Turn Undead"])), ["Righteous Fury", hours(facts.auraMs("Righteous Fury") / 3_600_000)]];

const commandSeals = fateCount(undefined, "Seal of Command");
const commandDamage = facts.meterSpells("d").find(([name]) => name === "Seal of Command")[1];
const used = (name) => percent((facts.fightCasts(name) / casts(name)) * 100);

export const TALENTS = [
  ["Seals put up", number(commandSeals), "Judged", percent((fateCount(1, "Seal of Command") / commandSeals) * 100), "Of your damage", percent((commandDamage / damageTotal) * 100)],
  ["Casts", number(casts("Repentance")), "Used in a fight", used("Repentance"), "Casts a played hour", decimal(perHour(casts("Repentance")))],
  ["Casts", number(casts("Consecration")), "Used in a fight", used("Consecration"), "Casts a fight", decimal(casts("Consecration") / fightCount)],
];

export const PARTY = [
  ["Blessings a played hour", decimal(perHour(blessingTotal))],
  ["Greater blessings", number(casts(...GREATER))],
  ["Symbols of Kings used", number(facts.itemOut(21177))],
  ...rows(ranked(["Blessing of Protection", "Divine Intervention", "Redemption"])),
  ["Cleanse", number(casts("Cleanse"))],
  ["Purify", number(casts("Purify"))],
  ["Dispels landed", number(facts.meterTotal("x"))],
];
