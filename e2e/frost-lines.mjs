import { talentLines } from "./mage-tree.mjs";

const ELWYNN = 1429;

/** A Mage of the first install who casts no spell and holds a Frost build that differs from Frost-Weaver's, so the talent page of the spec has pick rates between none and all. */
export const FROST_STUDY_LINES = [
  [0, "S", "1.15.8", "MAGE", "Gnome", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  ...talentLines({ held: [[2001, 1], [2003, 1], [2010, 5], [2011, 3], [2014, 3], [2013, 2], [2100, 5], [2101, 2], [2208, 1, 1]], spent: [7, 1, 15] }),
  [60000, "ES"],
];
