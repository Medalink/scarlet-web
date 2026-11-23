/**
 * What the class page of the Rogue fixture (e2e/rogue-lines.mjs) must show, worked out from the plan that file writes
 * (24 fights over three levels, 22 Pick Pocket casts, 12 Pick Lock casts, 46 poisons applied, one death after a Vanish)
 * before the page was read. Each pair is a label and the value written next to it; each note is a line of text.
 */

export const HEAD = [
  ["Hours in stealth", "1.5"],
  ["Openers from stealth", "15"],
  ["Pockets picked", "22"],
  ["Locks picked", "12"],
  ["Poisons applied", "46"],
  ["Vanishes", "6"],
];

export const BAND = [
  ["Hours in stealth", "1.5"],
  ["Openers from stealth", "15"],
  ["Pockets picked", "22"],
  ["Locks picked", "12"],
  ["Vanishes", "6"],
];

export const CALLOUTS = [
  ["Instant Poison", "The poison applied most", "65% of applications"],
  ["Locks picked", "12 boxes, doors and chests"],
  ["Pick Pocket", "22 pockets picked"],
];

export const STEALTH = [
  ["Hours in stealth", "1.5 h"],
  ["Of played time", "25%"],
  ["Seconds stealthed before the opener", "2.7"],
];

export const HANDS = [
  ["Pockets picked", "22"],
  ["A played hour", "3.7"],
  ["Most in a row", "9"],
  ["Locks picked", "12"],
  ["Lockboxes opened", "7"],
  ["A played hour", "2.0"],
];

export const FIGHTING = [
  ["Sinister Strike", "40"],
  ["Backstab", "12"],
  ["Gouge", "6"],
  ["Hemorrhage", "6"],
  ["Ghostly Strike", "5"],
  ["Feint", "3"],
  ["Instant Poison", "30"],
  ["Deadly Poison", "12"],
  ["Crippling Poison", "4"],
  ["Sprint", "8"],
  ["Evasion", "5"],
  ["Blind", "3"],
  ["Kicks cast", "10"],
  ["Interrupts landed", "7"],
  ["Landed", "70%"],
];

export const TALENTS = [
  ["Time up", "45 s"],
  ["Casts under it", "7"],
  ["Casts under it", "6"],
  ["Cast in a fight", "80%"],
  ["Most in a row", "2"],
];

export const PARTY = [
  ["Fights opened with one", "3"],
  ["Of all fights", "13%"],
  ["Distract", "6"],
  ["Detect Traps", "3"],
  ["Disarm Trap", "1"],
];

export const NOTES = [
  "Cheap Shot opened 7 fights and led into Eviscerate in 3 of them. 7 fights ended without a finisher.",
  "Eviscerate was the only finisher before level 51, and Slice and Dice came in at level 51.",
  "Sinister Strike was 56% of these casts.",
  "17 poisons were crafted. Mind-numbing Poison was crafted and never applied.",
  "1 death came within 10 seconds of a Vanish.",
];

export const HEADINGS = [
  "From the opener to the finisher",
  "Stealth",
  "The finisher used, by level",
  "Pick Pocket",
  "Attacks used",
  "Locks",
  "Poisons applied",
  "Escapes",
  "Kick",
  "Blade Flurry",
  "Adrenaline Rush",
  "Riposte",
  "Sap",
  "Scouting",
];
