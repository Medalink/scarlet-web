/**
 * What the class page of the Warlock fixture (e2e/warlock-lines.mjs) must show, worked out from the lines the fixture
 * writes and compared with the rows the decoder made of them, before the page was read. Each entry is a label and the
 * value written next to it.
 *
 * Soul shards made are the `I` lines at place `o` that bring a shard in: 14, 12 and 10 Drain Souls and the 2 Shadowburn
 * kills, 38. The 2 shards looted (place `l`) are in the bags but not made, so the bags end at 40 in, 37 out, 3 held.
 * The 21 kills with a credit line are 8 with a Shadow Bolt before them, 2 with a Shadowburn, 7 with no cast in the 3
 * seconds before, and 4 by the party: 10 of 21 is 48%, 7 of 21 is 33%, 4 of 21 is 19%.
 */

export const HEAD = [
  ["Soul shards made", "38"],
  ["Life Taps", "18"],
  ["Hours with a demon", "5.9"],
  ["Healthstones made", "7"],
  ["Soulstones made", "3"],
  ["Players summoned", "4"],
];

export const BAND = [
  ["Soul shards made", "38"],
  ["Life Taps", "18"],
  ["Demon out most", "Voidwalker"],
  ["Healthstones made", "7"],
  ["Players summoned", "4"],
];

export const CALLOUTS = [
  ["Zharmok", "Voidwalker, out 2.7 hours", "45% of demon time"],
  ["Nethira", "Succubus, out 0.7 hours", "11% of demon time"],
  ["Piptik", "Imp, out 0.8 hours", "14% of demon time"],
  ["Shaadrun", "Felhunter, out 1.7 hours", "29% of demon time"],
];

export const SHARDS = [
  ["Drain Soul", "36"],
  ["Shadowburn", "2"],
  ["Summoning a demon", "4"],
  ["Soul Fire", "3"],
  ["Healthstones", "7"],
  ["Soulstones", "3"],
  ["Ritual of Summoning", "4"],
  ["Destroyed to make room", "11"],
  ["In the bags now", "3"],
];

export const TAPS = [
  ["Health traded", "5k"],
  ["Taps in combat", "44%"],
  ["Most taps in a row", "4"],
];

export const DEMONS = [
  ["Voidwalker", "2.7 h"],
  ["Felhunter", "1.7 h"],
  ["Imp", "0.8 h"],
  ["Succubus", "0.7 h"],
  ["No demon out", "0.1 h"],
  ["Summons", "5"],
  ["Health Funnel", "0.1 h"],
];

export const CASTING = [
  ["Curse of Agony", "19"],
  ["Curse of Weakness", "6"],
  ["Curse of the Elements", "4"],
  ["Curse of Tongues", "3"],
  ["Shadow Bolt", "34%"],
  ["Corruption", "24%"],
  ["Siphon Life", "9%"],
  ["Immolate", "2%"],
  ["Every other spell", "9%"],
  ["Your killing blow", "48%"],
  ["No cast in the 3 seconds before", "33%"],
  ["A party member", "19%"],
];

export const PARTY = [
  ["Healthstones made", "7"],
  ["Eaten", "4"],
  ["Handed to another player", "2"],
  ["Soulstones made", "3"],
  ["Deaths undone by a soulstone", "1"],
  ["Players summoned", "4"],
  ["Interrupted", "1"],
  ["Fear", "8"],
  ["Death Coil", "5"],
  ["Howl of Terror", "2"],
  ["Banish", "1"],
];

export const TALENTS = [
  ["Shadow Trances", "8"],
  ["Used", "75%"],
  ["Shadow Bolts under it", "6"],
  ["Casts", "5"],
  ["Killed within 3 seconds", "40%"],
  ["Shards spent", "5"],
  ["Of your healing", "30%"],
  ["Of your damage", "9%"],
];
