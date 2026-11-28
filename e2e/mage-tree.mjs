/**
 * The talent tree of the fixture mages (Frost-Weaver in e2e/mage-lines.mjs, Ice-Reader in e2e/frost-lines.mjs): three
 * tabs of four columns by seven rows, in the raw numbers the client sends (a column is 600 apart, a tab starts 4000 to
 * the right of the one before, a row is 600 down), with the arrows between nodes, a choice node with two spells and a
 * y written ten times too large, which the page reads back. Each row of NODES is node id, column, row, the spell (or
 * the two spells of a choice) as `[id, name]`, ranks, the nodes it points to and its tab. A build is the nodes a
 * character holds, and with them the lines of its `TV`, `TD`, `TA` and `TG` codes (docs/ROWS.md).
 */

export const ICE_BLOCK = 11958;
export const ICE_BARRIER = 11426;
export const COLD_SNAP = 12472;
export const PRESENCE_OF_MIND = 12043;
export const ARCANE_POWER = 12042;
export const COMBUSTION = 11129;
export const PYROBLAST = 11366;

export const TREE = 1020;
export const ARCANE = 301;
export const FIRE = 302;
export const FROST = 303;

const CLASS_MAGE = 8;
const LAYOUT_HASH = "7c41d2e9";
const COLUMN = 600;
const ROW = 600;
const TAB_WIDTH = 4000;
const LEFT = 1020;
const TOP = 2130;
const TYPO = 10;

const NODES = [
  [2100, 0, 0, [12101, "Arcane Focus"], 5, [2103], ARCANE],
  [2101, 1, 0, [12102, "Wand Specialization"], 2, [], ARCANE],
  [2102, 2, 0, [12103, "Magic Absorption"], 5, [2105], ARCANE],
  [2103, 0, 1, [12104, "Arcane Concentration"], 5, [2106], ARCANE],
  [2104, 1, 1, [12105, "Magic Attunement"], 2, [2108], ARCANE],
  [2105, 2, 1, [12106, "Improved Counterspell"], 3, [], ARCANE],
  [2106, 0, 2, [12107, "Arcane Resilience"], 1, [], ARCANE],
  [2107, 3, 2, [12108, "Improved Mana Shield"], 2, [2109], ARCANE],
  [2108, 1, 3, [PRESENCE_OF_MIND, "Presence of Mind"], 1, [2110], ARCANE],
  [2109, 3, 3, [12109, "Arcane Instability"], 3, [], ARCANE],
  [2110, 1, 5, [ARCANE_POWER, "Arcane Power"], 1, [], ARCANE],
  [2200, 0, 0, [12201, "Improved Fireball"], 5, [2203], FIRE],
  [2201, 1, 0, [12202, "Impact"], 5, [], FIRE],
  [2202, 3, 0, [12203, "Fire Power"], 5, [2204], FIRE],
  [2203, 0, 1, [12204, "Ignite"], 5, [2205], FIRE],
  [2204, 3, 1, [12205, "Flame Throwing"], 2, [2206], FIRE],
  [2205, 1, 2, [PYROBLAST, "Pyroblast"], 1, [2207], FIRE],
  [2206, 2, 2, [12206, "Burning Soul"], 2, [], FIRE],
  [2208, 3, 4, [[12207, "Blast Wave"], [12208, "Dragon's Breath"]], 1, [], FIRE],
  [2207, 1, 4, [12209, "Critical Mass"], 3, [2209], FIRE],
  [2209, 1, 6, [COMBUSTION, "Combustion"], 1, [], FIRE],
  [2010, 0, 0, [12301, "Improved Frostbolt"], 5, [2013], FROST],
  [2011, 1, 0, [12302, "Frostbite"], 3, [2014], FROST],
  [2012, 2, 0, [12303, "Piercing Ice"], 3, [2015], FROST],
  [2013, 0, 1, [12304, "Improved Frost Nova"], 2, [2016], FROST],
  [2014, 1, 1, [12305, "Frost Channeling"], 3, [2002], FROST],
  [2015, 2, 1, [12306, "Shatter"], 5, [2018], FROST],
  [2016, 0, 2, [12307, "Improved Blizzard"], 3, [], FROST],
  [2002, 1, 2, [COLD_SNAP, "Cold Snap"], 1, [2003], FROST],
  [2017, 3, 2, [12308, "Improved Cone of Cold"], 3, [2019], FROST],
  [2018, 2, 3, [12309, "Winter's Chill"], 5, [], FROST, true],
  [2019, 3, 3, [12310, "Ice Shards"], 5, [], FROST],
  [2003, 1, 4, [ICE_BLOCK, "Ice Block"], 1, [2001], FROST],
  [2020, 2, 4, [12311, "Permafrost"], 3, [], FROST],
  [2021, 0, 5, [12312, "Arctic Reach"], 2, [], FROST],
  [2001, 1, 6, [ICE_BARRIER, "Ice Barrier"], 1, [], FROST],
];

const entriesOf = (spell) => (Array.isArray(spell[0]) ? spell : [spell]);
const tabIndex = (tab) => [ARCANE, FIRE, FROST].indexOf(tab);

export const TREE_SPELL_NAMES = NODES.flatMap(([, , , spell]) => entriesOf(spell));

export const NODE_NAMES = Object.fromEntries(NODES.map(([node, , , spell]) => [node, entriesOf(spell)[0][1]]));

/** The talent lines of a session whose character holds `held`, `[node, rank]` pairs (`[node, rank, entry]` for the entry of a choice node other than the first), with `spent` points in Arcane, Fire and Frost. */
export function talentLines({ held, spent, spec = "Frost" }) {
  const entryOf = (node) => node + 1000;
  const heldNodes = held.map(([node, rank, index = 0]) => [NODES.find(([id]) => id === node), rank, index]);
  return [
    [500, "TY", ARCANE, "Arcane"],
    [510, "TY", FIRE, "Fire"],
    [520, "TY", FROST, "Frost"],
    [800, "TV", TREE, CLASS_MAGE, NODES.length, LAYOUT_HASH],
    ...NODES.map(([node, column, row, spell, ranks, to, tab, typo], at) => {
      const x = LEFT + tabIndex(tab) * TAB_WIDTH + column * COLUMN;
      const y = TOP + row * ROW;
      const entries = entriesOf(spell).map(([id], index) => `${entryOf(node) + index * 100000}:${id}:${ranks}`).join(",");
      return [810 + at * 10, "TD", TREE, node, x, typo ? y * TYPO : y, "single", ranks, tab, entries, to.map((id) => `${id}:0`).join(",")];
    }),
    ...heldNodes.map(([[node, , , spell, ranks, , tab], rank, index], at) => [1500 + at * 10, "TA", 1, node, entryOf(node) + index * 100000, rank, "", ranks, entriesOf(spell)[index][0], tab, "s"]),
    [2000, "TG", "s", 1, 1, "", spec, TREE, `${ARCANE}:${spent[0]}:0,${FIRE}:${spent[1]}:0,${FROST}:${spent[2]}:0`, 0, 0, 1, ""],
  ];
}
