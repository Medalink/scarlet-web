/**
 * What the class page of the Priest fixture (e2e/priest-lines.mjs) must show, worked out from the plan that file writes
 * (24 fights of 30 seconds over three levels, a dungeon and a raid among them, 172 heals cast, 36 shields, 15 players
 * raised, 28 Fortitudes, one hour of Shadowform, 20 seconds of wand in each fight) before the page was read. Each pair
 * is a label and the value written next to it; each note is a line of text.
 */

export const HEAD = [
  ["Healing done", "72k"],
  ["Shields cast", "36"],
  ["Players raised", "15"],
  ["Fortitudes cast", "28"],
  ["Hours in Shadowform", "1.0"],
  ["Hours of wand", "0.1"],
];

export const BAND = [
  ["Healing done", "72k"],
  ["Shields cast", "36"],
  ["Players raised", "15"],
  ["Hours in Shadowform", "1.0"],
  ["Heal cast most", "Flash Heal"],
];

export const CALLOUTS = [
  ["Flash Heal", "The heal cast most", "26% of heals cast"],
  ["Power Word: Shield", "36 shields", "9,600 damage absorbed"],
  ["Resurrection", "15 players raised"],
];

export const HEALING = [
  ["Share of group healing", "33%"],
  ["Fights healed", "24"],
  ["Most in one fight", "4,000"],
  ["Flash Heal", "27k"],
  ["Greater Heal", "22k"],
  ["Renew", "10k"],
  ["Prayer of Healing", "8k"],
  ["Heal", "4k"],
];

export const SHADOW = [
  ["Smite", "30"],
  ["Shadow Word: Pain", "24"],
  ["Mind Blast", "18"],
  ["Holy Fire", "10"],
  ["Mind Flay", "10"],
  ["Devouring Plague", "4"],
  ["Hours of wand", "0.1"],
  ["Share of fight time", "67%"],
];

export const SUPPORT = [
  ["Cast", "36"],
  ["Absorbed", "9,600"],
  ["Each fight", "400"],
  ["Fade", "18"],
  ["Psychic Scream", "10"],
  ["Shackle Undead", "4"],
  ["Mind Soothe", "3"],
  ["Mind Control", "2"],
  ["Inner Fire", "12"],
  ["Shadow Protection", "3"],
  ["Levitate", "2"],
];

export const TALENTS = [
  ["Casts", "1"],
  ["Time up", "15 s"],
  ["Casts under it", "2"],
  ["Times it came up", "1"],
  ["Heals cast as one", "2"],
  ["Casts", "2"],
  ["Cast in a fight", "0%"],
  ["Most in a row", "1"],
];

export const PARTY = [
  ["Players raised", "15"],
  ["A played hour", "2.5"],
  ["Most in a row", "6"],
  ["Casts", "16"],
  ["Dispels landed", "15"],
  ["Dispel Magic", "10"],
  ["Cure Disease", "4"],
  ["Abolish Disease", "2"],
  ["Power Word: Fortitude", "20"],
  ["Prayer of Fortitude", "8"],
  ["Holy Candles used", "8"],
];

export const NOTES = [
  "Healing first passed damage at level 50, with 2,000 against 500.",
  "The meter names the top five spells of a fight.",
  "Shadowform was up for 1.0 hours, 17% of played time.",
  "The open world, 22%",
  "Dungeons, 33%",
  "Raids, 44%",
];

export const HEADINGS = [
  "Healing and damage a fight, by level",
  "Where the healing was done",
  "The heal cast, by level",
  "Healing by spell",
  "The shadow side",
  "Wand",
  "Shields",
  "Control",
  "Buffs kept up",
  "Raised from the dead",
  "Dispels",
  "Fortitude",
  "Inner Focus",
  "Spirit of Redemption",
  "Lightwell",
];
