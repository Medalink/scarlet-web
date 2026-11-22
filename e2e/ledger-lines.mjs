/**
 * The session of the fixture character Spell-Ledger (e2e/fixture/Spell-Ledger): 800 seconds of a rogue that levels from
 * 10 to 11 at second 700 and plays seven fights, two deaths, the auras, totems, pets, mounts, tracking, weapons and
 * bag changes of the class tables (docs/ROWS.md, "The class tables"), so every table and every `class_tallies` key
 * holds a row. Each line is `[milliseconds since the session began, code, ...fields]`, as e2e/build-fixture.mjs reads
 * them. The rows they must decode to are in e2e/ledger-expected.mjs, and e2e/class-rows.mjs compares them with the
 * decoder's output.
 */

const ELWYNN = 1429;
const DEADMINES = 36;

const AMBUSH = 8676;
const SINISTER_STRIKE = 1752;
const EVISCERATE = 2098;
const SLICE_AND_DICE = 5171;
const KIDNEY_SHOT = 408;
const CHEAP_SHOT = 1833;
const GARROTE = 703;
const STEALTH = 1784;
const PROWL = 5215;
const ICE_BLOCK = 11958;
const CLEARCASTING = 16870;
const CURSE_OF_AGONY = 980;
const FORTITUDE = 1243;
const PRAYER_OF_FORTITUDE = 21564;
const MARK_OF_THE_WILD = 1126;
const CAT_FORM = 768;
const BEAR_FORM = 5487;
const BATTLE_STANCE = 2457;
const DEFENSIVE_STANCE = 71;
const ASPECT_OF_THE_HAWK = 13165;
const ASPECT_OF_THE_MONKEY = 13163;
const DEVOTION_AURA = 465;
const RETRIBUTION_AURA = 7294;
const SEAL_OF_RIGHTEOUSNESS = 20154;
const SEAL_OF_COMMAND = 20375;
const SEAL_OF_THE_CRUSADER = 20162;
const JUDGEMENT = 20271;
const STONECLAW_TOTEM = 5730;
const SEARING_TOTEM = 2075;
const SEARING_TOTEM_RANK_2 = 3599;
const GROUNDING_TOTEM = 8177;
const HEALING_STREAM_TOTEM = 5394;
const DRAIN_SOUL = 1120;
const CONJURE_WATER = 5504;
const AUTO_SHOT = 75;
const SHOOT = 5019;
const SOULSTONE_RESURRECTION = 20707;
const ANKH = 17030;
const BROWN_HORSE = 458;

const SOUL_SHARD = 6265;
const CONJURED_WATER = 5350;
const ARROW = 2512;
const LINEN_CLOTH = 2589;
const WORN_SHORTSWORD = 25;
const WORN_DAGGER = 2092;
const WORN_BUCKLER = 1396;
const WORN_CHEST = 6125;

const MAIN_HAND = 16;
const OFF_HAND = 17;
const CHEST = 5;

const gain = (spell, helpful, seconds, by, initial = "") => ["A", 1, spell, helpful, seconds, by, initial];
const fade = (spell, seconds) => ["A", 0, spell, seconds];
const cast = (spell, seconds = "") => ["C", spell, seconds];
const kill = (npc) => ["K", npc, 1];
const equip = (slot, item) => ["G", slot, item, "", "", ""];
const happiness = (name, level, mood, why) => ["PD", name, level, 0, 100, mood, "Faithful", 0, 0, why];
const totem = (slot, spell, seconds) => ["TW", slot, spell, seconds];
const meter = (kind, total, perSecond, seconds) => ["DM", kind, total, perSecond, seconds];
const peer = (kind, who, classFile, total) => ["DP", kind, who, classFile, total];
const top = (kind, spell, amount) => ["DS", kind, spell, amount];
const bag = (item, delta, where = "") => ["I", item, delta, where];

export const LEDGER_LINES = [
  [0, "S", "1.15.8", "ROGUE", "Human", 10, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  [2000, "MO", 1, BROWN_HORSE, 1, 0, "s"],
  [2500, ...gain(PRAYER_OF_FORTITUDE, 1, 3600, 0, "s")],
  [3000, "MP", "1494", "s"],
  [3500, ...equip(MAIN_HAND, WORN_SHORTSWORD)],
  [4000, ...equip(CHEST, WORN_CHEST)],
  [8000, "MO", 0, BROWN_HORSE, 1, 6.0],

  [10000, ...gain(STEALTH, 1, 0, 1)],
  [12000, ...cast(AMBUSH)],
  [12500, "F", 1],
  [13000, ...fade(STEALTH, 3.0)],
  [13100, ...cast(SINISTER_STRIKE)],
  [14000, ...cast(EVISCERATE)],
  [15000, ...kill(990001)],
  [15500, "F", 0, 3.0],

  [30000, "F", 1],
  [31000, ...cast(SLICE_AND_DICE)],
  [32000, ...cast(SINISTER_STRIKE)],
  [33000, ...kill(990002)],
  [33500, ...kill(990003)],
  [34000, "K", 990004, 0],
  [35000, "F", 0, 5.0],
  [36000, ...meter("d", 4000, 800.0, 5.0)],
  [36200, ...peer("d", "~7f3a", "MAGE", 6000)],
  [36400, ...peer("d", "~9c21", "PRIEST", 2000)],
  [36600, ...top("d", SINISTER_STRIKE, 2500)],
  [36700, ...top("d", EVISCERATE, 1500)],
  [37000, ...meter("t", 900, 180.0, 5.0)],

  [50000, "F", 1],
  [54000, ...cast(SINISTER_STRIKE)],
  [55000, "F", 0, 5.0],

  [70000, ...gain(ICE_BLOCK, 1, 10, 1)],
  [71000, ...cast(EVISCERATE)],
  [72000, ...fade(ICE_BLOCK, 2.0)],
  [73000, ...cast(SINISTER_STRIKE)],
  [74000, "D"],
  [74500, "SR", 0, SOULSTONE_RESURRECTION],
  [74600, "SR", 1, ANKH],
  [75000, "U"],
  [90000, "D"],
  [90500, "SR", 0, SOULSTONE_RESURRECTION],
  [91000, "R"],
  [92000, "SH"],
  [100000, ...fade(PRAYER_OF_FORTITUDE, 100.0)],

  [110000, ...gain(CAT_FORM, 1, 0, 1)],
  [115000, ...gain(BEAR_FORM, 1, 0, 1)],
  [115200, ...fade(CAT_FORM, 5.2)],
  [120000, ...fade(BEAR_FORM, 5.0)],
  [120500, ...gain(BEAR_FORM, 1, 0, 1)],
  [125000, ...fade(BEAR_FORM, 4.5)],
  [130000, ...gain(BATTLE_STANCE, 1, 0, 1)],
  [135000, ...fade(BATTLE_STANCE, 5.0)],
  [135100, ...gain(DEFENSIVE_STANCE, 1, 0, 1)],
  [140000, ...fade(DEFENSIVE_STANCE, 4.9)],
  [145000, ...gain(ASPECT_OF_THE_HAWK, 1, 0, 1)],
  [150000, ...gain(ASPECT_OF_THE_MONKEY, 1, 0, 1)],
  [150100, ...fade(ASPECT_OF_THE_HAWK, 5.1)],
  [155000, ...fade(ASPECT_OF_THE_MONKEY, 5.0)],
  [160000, ...gain(DEVOTION_AURA, 1, 0, 1)],
  [165000, ...gain(RETRIBUTION_AURA, 1, 0, 1)],
  [165100, ...fade(DEVOTION_AURA, 5.1)],
  [170000, ...fade(RETRIBUTION_AURA, 5.0)],

  [180000, ...gain(SEAL_OF_RIGHTEOUSNESS, 1, 30, 1)],
  [184000, ...cast(JUDGEMENT)],
  [184200, ...fade(SEAL_OF_RIGHTEOUSNESS, 4.2)],
  [190000, ...gain(SEAL_OF_RIGHTEOUSNESS, 1, 30, 1)],
  [195000, ...gain(SEAL_OF_COMMAND, 1, 30, 1)],
  [195100, ...fade(SEAL_OF_RIGHTEOUSNESS, 5.1)],
  [225000, ...fade(SEAL_OF_COMMAND, 30.0)],
  [235000, ...gain(SEAL_OF_THE_CRUSADER, 1, 30, 1)],
  [240000, ...fade(SEAL_OF_THE_CRUSADER, 5.0)],

  [250000, "F", 1],
  [251000, ...gain(SEAL_OF_RIGHTEOUSNESS, 1, 30, 1)],
  [252000, ...cast(JUDGEMENT)],
  [254000, "F", 0, 4.0],
  [260000, ...fade(SEAL_OF_RIGHTEOUSNESS, 9.0)],

  [270000, ...totem(1, STONECLAW_TOTEM, 15.0)],
  [275000, ...totem(2, SEARING_TOTEM, 55.0)],
  [278000, "F", 1],
  [279000, ...totem(3, GROUNDING_TOTEM, 45.0)],
  [282000, ...totem(4, HEALING_STREAM_TOTEM, 60.0)],
  [285000, ...totem(1, 0, 0)],
  [287000, ...totem(2, SEARING_TOTEM_RANK_2, 55.0)],
  [290000, ...totem(3, 0, 0)],
  [295000, "F", 0, 17.0],
  [296000, ...meter("d", 7000, 411.0, 17.0)],
  [296300, ...peer("d", "~7f3a", "MAGE", 5000)],
  [296600, ...peer("d", "~9c21", "PRIEST", 3000)],
  [296800, ...peer("d", "~11aa", "HUNTER", 1000)],
  [297000, ...top("d", SINISTER_STRIKE, 4000)],
  [297100, ...top("d", SLICE_AND_DICE, 3000)],
  [300000, "F", 1],
  [301000, ...cast(SINISTER_STRIKE)],
  [304000, "F", 0, 4.0],
  [345000, ...totem(2, 0, 0)],
  [346000, ...totem(4, 0, 0)],

  [360000, ...gain(PROWL, 1, 0, 1)],
  [362000, ...fade(PROWL, 2.0)],
  [362500, ...cast(CHEAP_SHOT)],
  [363000, ...cast(GARROTE)],
  [365000, ...cast(AMBUSH)],

  [380000, "CH", DRAIN_SOUL, 5.0],
  [380500, ...bag(SOUL_SHARD, 1)],
  [395000, "CH", DRAIN_SOUL, 4.0],
  [395400, ...bag(SOUL_SHARD, 1)],
  [400000, ...cast(CONJURE_WATER, 3.0)],
  [400200, ...bag(CONJURED_WATER, 2)],
  [410000, ...bag(CONJURED_WATER, -1)],
  [420000, "AR", 1, AUTO_SHOT],
  [423000, ...bag(ARROW, -1)],
  [424000, ...bag(ARROW, -1)],
  [426000, "AR", 0, AUTO_SHOT, 6.0],
  [440000, "I", LINEN_CLOTH, -5, "v", 1234, 15],
  [450000, ...bag(LINEN_CLOTH, 3, "l")],
  [460000, "DI", LINEN_CLOTH, 2],
  [460300, ...bag(LINEN_CLOTH, -2)],
  [465000, ...bag(LINEN_CLOTH, 1, "m")],
  [470000, ...bag(LINEN_CLOTH, -1, "t")],

  [480000, "MO", 1, BROWN_HORSE, 1, 0, ""],
  [490000, "MO", 0, BROWN_HORSE, 1, 10.0],
  [500000, "PT", "Whiskers", "Cat"],
  [500500, ...happiness("Whiskers", 10, 3, "c")],
  [520000, ...happiness("Whiskers", 10, 2, "c")],
  [540000, ...happiness("Whiskers", 10, 3, "c")],
  [560000, "PT", "", ""],
  [565000, ...happiness("Rex", 10, 3, "s")],
  [580000, "MP", "1494,Find Minerals", "c"],
  [600000, "MP", "Find Minerals", "c"],
  [600500, ...equip(MAIN_HAND, WORN_DAGGER)],
  [601000, ...gain(FORTITUDE, 1, 1800, 0)],
  [605000, ...gain(CLEARCASTING, 1, 15, 1)],
  [606000, ...cast(SINISTER_STRIKE)],
  [607000, ...fade(CLEARCASTING, 2.0)],
  [607200, ...cast(SINISTER_STRIKE)],
  [610000, ...equip(OFF_HAND, WORN_BUCKLER)],

  [620000, "F", 1],
  [621500, ...cast(AMBUSH)],
  [622000, ...gain(CURSE_OF_AGONY, 0, 24, 0)],
  [623000, "AR", 1, SHOOT],
  [625000, "CI", EVISCERATE, 0.5],
  [625500, "CI", SHOOT, 1.0],
  [626000, ...cast(KIDNEY_SHOT)],
  [627000, ...kill(990005)],
  [628000, ...fade(CURSE_OF_AGONY, 6.0)],
  [628500, "AR", 0, SHOOT, 5.0],
  [629000, ...fade(CURSE_OF_AGONY, 6.0)],
  [630000, "F", 0, 10.0],

  [640000, "Z", "", DEADMINES, "party", 1, "The Deadmines", ""],
  [641000, ...fade(FORTITUDE, 40.0)],
  [645000, ...meter("d", 3000, 300.0, 10.0)],
  [645200, ...peer("d", "~7f3a", "MAGE", 4000)],
  [648000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],

  [650000, ...gain(MARK_OF_THE_WILD, 1, 1800, 0)],
  [652000, ...gain(MARK_OF_THE_WILD, 1, 1800, 0)],
  [653000, ...fade(MARK_OF_THE_WILD, 3.0)],
  [700000, "L", 11],
  [710000, ...fade(MARK_OF_THE_WILD, 60.0)],
  [720000, ...meter("h", 1500, 100.0, 15.0)],
  [720500, ...cast(SINISTER_STRIKE)],
  [721000, ...cast(SINISTER_STRIKE)],
  [722000, ...cast(SINISTER_STRIKE)],
  [723000, ...kill(990006)],
  [724000, ...cast(EVISCERATE)],
  [725000, ...meter("d", 2500, 250.0, 10.0)],
  [725300, ...peer("d", "~7f3a", "MAGE", 2500)],
  [725500, ...top("d", SINISTER_STRIKE, 1800)],
  [730000, ...bag(CONJURED_WATER, 1)],
  [750000, "MP", "", "c"],

  [800000, "ES"],
];
