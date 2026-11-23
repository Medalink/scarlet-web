/**
 * What the class page of the Shaman fixture (e2e/shaman-lines.mjs) must show, worked out from the plan that file writes
 * (24 fights of 30 seconds in which 9 had all four totem slots in use, 6 three, 6 two and 3 none; 112 totems dropped
 * with 15 lost early; 42 shocks; 15 Stormstrikes; 4 deaths, 3 of them walked off with Reincarnation; one hour as a Ghost
 * Wolf and an hour and a half mounted) before the page was read. Each pair is a label and the value written next to it;
 * each note is a line of text.
 */

export const HEAD = [
  ["Totems dropped", "112"],
  ["Hours a totem stood", "0.5"],
  ["Shocks cast", "42"],
  ["Hours as a Ghost Wolf", "1.0"],
  ["Reincarnations", "3"],
  ["Astral Recalls", "4"],
];

export const BAND = [
  ["Totems dropped", "112"],
  ["Totem dropped most", "Windfury"],
  ["Shocks cast", "42"],
  ["Hours as a Ghost Wolf", "1.0"],
  ["Reincarnations", "3"],
];

export const CALLOUTS = [
  ["Magma Totem", "Fire, dropped 21 times", "0.1 hours standing"],
  ["Strength of Earth Totem", "Earth, dropped 15 times", "0.1 hours standing"],
  ["Mana Spring Totem", "Water, dropped 9 times", "0.0 hours standing"],
  ["Windfury Totem", "Air, dropped 21 times", "0.1 hours standing"],
];

export const TOTEMS = [
  ["Magma", "21"],
  ["Searing", "15"],
  ["Strength of Earth", "15"],
  ["Stoneclaw", "15"],
  ["Mana Spring", "9"],
  ["Healing Stream", "9"],
  ["Windfury", "21"],
  ["Grace of Air", "7"],
  ["Dropped", "36"],
  ["Dropped", "30"],
  ["Dropped", "18"],
  ["Dropped", "28"],
  ["Stood", "0.2 h"],
  ["Stood", "0.1 h"],
  ["Fights with all four down", "9"],
  ["Median elements down a fight", "3"],
  ["Fights with a totem down", "21"],
];

export const FIGHTING = [
  ["Earth Shock", "24"],
  ["Flame Shock", "12"],
  ["Frost Shock", "6"],
  ["Interrupts landed", "7"],
  ["Of Earth Shocks", "29%"],
  ["Windfury Weapon", "12"],
  ["Flametongue Weapon", "6"],
  ["Frostbrand Weapon", "3"],
  ["Rockbiter Weapon", "1"],
];

export const WALKS = [
  ["Reincarnations", "3"],
  ["Ankhs bought", "8"],
  ["Deaths walked off", "75%"],
  ["Hours as a wolf", "1.0"],
  ["Of travel time", "40%"],
  ["Astral Recalls", "4"],
  ["Ghost Wolf", "1.0 h"],
  ["Mounted", "1.5 h"],
];

export const TALENTS = [
  ["Casts", "15"],
  ["Cast in a fight", "100%"],
  ["Most in a row", "1"],
  ["Times it came up", "6"],
  ["Spent on a spell", "3"],
  ["Used", "50%"],
  ["Time up", "90 s"],
  ["Casts under it", "12"],
];

export const PARTY = [
  ["Windfury", "0.1 h"],
  ["Strength of Earth", "0.1 h"],
  ["Mana Spring", "0.0 h"],
  ["Grace of Air", "0.0 h"],
  ["Healing Wave", "12"],
  ["Chain Heal", "6"],
  ["Ancestral Spirit", "5"],
  ["Lesser Healing Wave", "3"],
  ["Purge", "6"],
  ["Cure Poison", "3"],
  ["Cure Disease", "1"],
  ["Casts", "10"],
  ["Dispels landed", "8"],
  ["Landed", "80%"],
];

export const NOTES = [
  "Windfury Totem went down 21 times.",
  "15 of 112 totems were gone before their time.",
  "All four down, 38% of 24 fights",
  "Some down, 50%",
  "None, 13%",
  "Walked off, 3 of 4 deaths",
];

export const HEADINGS = [
  "Totems, by element",
  "How a totem ended",
  "Totems in a fight",
  "Elements with a totem down, by level",
  "Shocks",
  "Weapon imbues",
  "Back from the dead",
  "Ghost Wolf and Astral Recall",
  "Totems for the group",
  "Heals and raises",
  "Purges and cures",
  "Stormstrike",
  "Elemental Focus",
  "Flurry",
];
