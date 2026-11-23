/**
 * What the class page of the Druid fixture (e2e/druid-lines.mjs) must show, worked out from the lines the fixture writes
 * before the page was read. Each entry is a label and the value written next to it; a callout is its three lines and a
 * note is a whole sentence.
 */

export const HEAD = [
  ["Shapeshifts", "17"],
  ["Hours as a bear", "0.5"],
  ["Hours as a cat", "0.6"],
  ["Hours in Travel Form", "0.4"],
  ["Innervates", "3"],
  ["Rebirths", "2"],
];

export const BAND = [
  ["Form worn most", "Cat Form"],
  ["Shapeshifts", "17"],
  ["Hours in Travel Form", "0.4"],
  ["Innervates", "3"],
  ["Rebirths", "2"],
];

export const CALLOUTS = [
  ["Bear Form", "Worn for 0.5 hours", "30% of time in a form"],
  ["Cat Form", "Worn for 0.6 hours", "35% of time in a form"],
  ["Travel Form", "Worn for 0.4 hours", "22% of time in a form"],
  ["Aquatic Form", "Worn for 0.1 hours", "7% of time in a form"],
];

export const FORMS = [
  ["Cat Form", "0.6"],
  ["Bear Form", "0.5"],
  ["Travel Form", "0.4"],
  ["Aquatic Form", "0.1"],
  ["Moonkin Form", "0.1"],
  ["No form", "4.3"],
  ["Shapeshifts", "17"],
  ["Shifts a fight", "1.3"],
];

export const TRAVEL = [
  ["Mounted", "0.2"],
  ["Travel Form share of travel", "54%"],
  ["Dash casts", "5"],
];

export const DID = [
  ["Maul", "21"],
  ["Swipe", "13"],
  ["Bash", "4"],
  ["Demoralizing Roar", "4"],
  ["Growl", "4"],
  ["Claw", "24"],
  ["Shred", "18"],
  ["Rake", "8"],
  ["Rip", "5"],
  ["Ferocious Bite", "4"],
  ["Ravage", "2"],
  ["Wrath", "9"],
  ["Rejuvenation", "8"],
  ["Moonfire", "7"],
  ["Healing Touch", "6"],
  ["Starfire", "5"],
  ["Regrowth", "4"],
  ["Rejuvenation", "43%"],
  ["Regrowth", "26%"],
  ["Healing Touch", "18%"],
  ["Swiftmend", "3%"],
  ["Every other spell", "11%"],
];

export const WILD = [
  ["Faerie Fire", "5"],
  ["Entangling Roots", "4"],
  ["Nature's Grasp", "3"],
  ["Hibernate", "2"],
  ["Soothe Animal", "1"],
  ["Hours prowling", "0.1"],
  ["Prowl casts", "3"],
  ["Spells cast from Prowl", "3"],
  ["Mark of the Wild", "4"],
  ["Teleport: Moonglade", "3"],
  ["Thorns", "3"],
  ["Abolish Poison", "2"],
  ["Remove Curse", "2"],
  ["Cure Poison", "1"],
];

export const PARTY = [
  ["Casts", "2"],
  ["Inside a fight", "50%"],
  ["Interrupted", "0"],
  ["Casts", "3"],
  ["Inside a fight", "0%"],
  ["Hours between casts", "2.0"],
];

export const TALENTS = [
  ["Casts", "3"],
  ["Fights it opened", "3"],
  ["Share of fights", "23%"],
  ["Clearcasting procs", "5"],
  ["Used", "60%"],
  ["Ran out", "2"],
  ["Hours up", "1.0"],
  ["Of the time in Bear or Cat Form", "88%"],
  ["Times it went on", "8"],
];

export const NOTES = [
  "4 shifts went back into the form just left within 2 seconds, 3 of them into Cat Form.",
  "Spells are grouped by the form they need. The addon does not record which form was worn at a cast.",
  "The meter lists the five largest spells of each fight.",
  "Who a spell was cast on is not recorded, so the page counts casts.",
  "A fight counts as opened by Feral Charge when it was the first spell cast, from 1.5 seconds before the fight to 3 seconds after its start.",
  "In Bear Form, 40%",
  "In Cat Form, 35%",
  "In Moonkin Form, 9%",
  "In no form, 16%",
];
