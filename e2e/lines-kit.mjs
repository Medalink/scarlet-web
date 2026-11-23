/**
 * What the class fixtures (warrior-lines.mjs, paladin-lines.mjs, hunter-lines.mjs) share when they write a session as
 * `[milliseconds since the session began, code, ...fields]` lines (docs/ROWS.md, "The class tables"): a clock that
 * moves as lines are written, the name and item lines, and the talent lines of one build.
 */

export const HOUR = 3_600_000;
export const GAP = 4;

export const ms = (seconds) => Math.round(seconds * 1000);

export class Track {
  lines = [];
  creature = 990100;
  fights = [];

  constructor(start) {
    this.t = start;
  }

  at(seconds, ...line) {
    this.t += ms(seconds);
    this.lines.push([this.t, ...line]);
  }

  wait(seconds) {
    this.t += ms(seconds);
  }

  npc() {
    this.creature += 1;
    return this.creature;
  }
}

export const nameLines = ({ spells, items = [], infos = [], descriptions = [] }) => [
  ...spells.map(([id, name], at) => [100 + at, "SN", id, name]),
  ...items.map(([id, name], at) => [1000 + at, "IN", id, name]),
  ...infos.map(([id, classId, subclassId, slot], at) => [1500 + at, "II", id, classId, subclassId, slot, 50, 40, 100, 1, 1]),
  ...descriptions.map(([id, text], at) => [300 + at, "SD", id, text]),
];

/**
 * The lines of one talent build: the tab names, the layout of the tree, the rank of each talent held and the active
 * group. `nodes` are `{ node, spell, ranks, held, tab }`; `spent` are the points in the three tabs, in the order of `tabs`.
 */
export function talentLines({ tree, classId, hash, spec, tabs, nodes, spent }) {
  return [
    ...tabs.map(([id, name], at) => [500 + at * 10, "TY", id, name]),
    [800, "TV", tree, classId, nodes.length, hash],
    ...nodes.map((one, at) => [810 + at * 10, "TD", tree, one.node, 100 + at * 100, 200, "single", one.ranks, one.tab, `${one.node + 1000}:${one.spell}:${one.ranks}`, ""]),
    ...nodes.map((one, at) => [1500 + at * 10, "TA", 1, one.node, one.node + 1000, one.held, "", one.ranks, one.spell, one.tab, "s"]),
    [2000, "TG", "s", 1, 1, "", spec, tree, tabs.map(([id], at) => `${id}:${spent[at]}:0`).join(","), 0, 0, 1, ""],
  ];
}

export const sessionStart = (cls, race, level, extra = []) => [
  [0, "S", "1.15.8", cls, race, level, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", 1429, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...extra,
];
