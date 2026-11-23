/**
 * What the class page of the Mage fixture (e2e/mage-lines.mjs) must show, worked out from the lines the fixture writes
 * before the page was read. Each entry is a label and the value written next to it; a callout is its three lines and a
 * note is a whole sentence.
 */

export const HEAD = [
  ["Water conjured", "48"],
  ["Portals opened", "11"],
  ["Teleports", "7"],
  ["Sheep made", "18"],
  ["Blinks", "9"],
  ["Frost Novas", "12"],
];

export const BAND = [
  ["Water conjured", "48"],
  ["Portals opened", "11"],
  ["Sheep made", "18"],
  ["Kills in one fight, most", "9"],
  ["Blinks", "9"],
];

export const CALLOUTS = [
  ["Portals", "11 opened", "Stormwind most, 55%"],
  ["Polymorph", "18 sheep", "16 cast in a fight"],
  ["Conjured water", "48 conjured", "4 handed to others"],
];

export const CONJURING = [
  ["Conjure Water", "48"],
  ["Conjure Food", "12"],
  ["Drunk", "15"],
  ["Eaten", "4"],
  ["Handed to another player", "6"],
  ["Destroyed to make room", "4"],
  ["In the bags or gone at logout", "31"],
  ["Conjured a played hour", "10.0"],
  ["Mana gems conjured", "3"],
  ["Mana gems used", "2"],
];

export const TRAVEL = [
  ["Stormwind", "10"],
  ["Ironforge", "5"],
  ["Darnassus", "3"],
  ["Portals", "11"],
  ["Teleports", "7"],
  ["Interrupted", "1"],
];

export const CASTING = [
  ["Frostbolt", "72"],
  ["Fireball", "24"],
  ["Blizzard", "12"],
  ["Fire Blast", "12"],
  ["Scorch", "12"],
  ["Arcane Missiles", "9"],
  ["Cone of Cold", "9"],
  ["Arcane Explosion", "6"],
  ["Pyroblast", "6"],
  ["Most kills in one fight", "9"],
  ["Fights with 5 kills or more", "4"],
  ["Hours of Blizzard", "0.1"],
];

export const CONTROL = [
  ["Casts", "22"],
  ["In a fight", "91%"],
  ["Interrupted", "1"],
  ["Polymorph", "18"],
  ["Polymorph: Pig", "4"],
  ["Frost Nova", "12"],
  ["Blink", "9"],
  ["Mana Shield", "6"],
  ["Counterspell", "4"],
  ["Ice Block", "3"],
  ["Evocation channels", "3"],
];

export const PARTY = [
  ["Water", "4"],
  ["Food", "2"],
  ["Handed out", "6"],
  ["Of what was conjured", "10%"],
  ["Arcane Intellect", "6"],
  ["Arcane Brilliance", "2"],
  ["Casts a played hour", "1.3"],
  ["Remove Curse casts", "5"],
  ["Curses lifted", "5"],
];

export const TALENTS = [
  ["Casts", "9"],
  ["Damage absorbed", "12k"],
  ["In a fight", "67%"],
  ["Hours between casts", "2.0"],
  ["Deaths within 10 s of one ending", "1"],
];

export const NOTES = [
  "Frost did the most damage at every level from 50 to 52. The meter lists the five largest spells of each fight, so the shares count the Frost, Fire and Arcane spells it listed.",
  "3 interrupts landed in 4 Counterspell casts.",
  "The addon records what goes into and out of the bags, not what they hold, so items left in the bags and items gone at logout are one row.",
];
