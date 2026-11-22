/**
 * Writes the gathering, crafting and camp session of the fixture character Echo-Witness (e2e/fixture/Echo-Witness): one
 * session of fishing, mining, herbalism, skinning, cooking and blacksmithing, a 90 second visit to a campfire, a few
 * spell casts and two fights of damage-meter lines, as the recorder would write it, and the session of Tailor-Stranger
 * (e2e/fixture/Tailor-Stranger), a character of another install that no account of the lane is linked to, with two
 * tailoring crafts and a 90 second visit to a campfire. Each line is
 * `[milliseconds since the session began, code, ...fields]`; the builder splits the lines into the parts of
 * their sharing groups (docs/groups.tsv), writes each part under the SHA-256 of its text and rewrites the manifest.
 * Parts of the character's other sessions are kept as they are, so running it twice changes nothing.
 *
 *   node e2e/build-fixture.mjs
 */

import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { xorDigest } from "./client.mjs";

const FIXTURES = path.join(path.dirname(fileURLToPath(import.meta.url)), "fixture");

const ELWYNN = 1429;
const FISHING_POLE = 6256;
const SHINY_BAUBLE_SPELL = 8087;
const FISHING = 356;
const HERBALISM = 182;
const MINING = 186;
const SKINNING = 393;
const COOKING = 185;
const TAILORING = 197;
const BLACKSMITHING = 164;

const FISHING_CAST = 7620;
const MINING_CAST = 2575;
const HERB_CAST = 2366;
const SKINNING_CAST = 8613;
const FISHING_BOBBER = 35591;
const COPPER_VEIN = 1731;
const PEACEBLOOM_NODE = 1617;
const SILVERLEAF_NODE = 1618;
const STONETUSK_BOAR = 113;

const CAMPFIRE_SPELL = 1307227;
const NEAR_CAMPFIRE = 1283391;
const SITTING = 1229739;
const CAMP_BENEFITS = 1229741;

const SMALLFISH = 6291;
const MACKEREL = 6303;
const CLAM = 7973;
const BLACK_PEARL = 7971;
const COPPER_ORE = 2770;
const PEACEBLOOM = 2447;
const SILVERLEAF = 765;
const SCRAPS = 2934;
const LIGHT_LEATHER = 2318;
const CHARRED_WOLF_MEAT = 2679;
const WOLF_MEAT_RECIPE = 2538;
const SHARPENING_STONE = 2862;
const SHARPENING_RECIPE = 2660;
const LINEN_CLOTH = 2589;
const LINEN_BOLT = 2996;
const LINEN_BOLT_RECIPE = 2963;
const SINISTER_STRIKE = 1752;
const EVISCERATE = 2098;

const GROUP_OF = {
  S: "core", Z: "core", ES: "core",
  P: "movement", G: "build", C: "combat", CH: "combat", A: "combat", DM: "combat", LO: "loot",
  SL: "professions", TS: "professions", CB: "professions", RD: "professions", CR: "professions", CS: "professions",
  IN: "names", SN: "names",
};
const GROUP_ORDER = ["core", "movement", "build", "combat", "loot", "professions", "names"];

const crafted = (item, recipe, enchant) => ["CR", item, 1, 0, 0, recipe, "", "", "", "", "", "", enchant];
const LURE = crafted(FISHING_POLE, "", 1);
const craft = (item, recipe) => crafted(item, recipe, 0);
const cast = (seconds) => ["CH", FISHING_CAST, seconds];
const bobber = (items) => ["LO", "g", FISHING_BOBBER, 1, items, 0];
const gather = (spell, seconds) => ["C", spell, seconds];
const node = (id, items, again) => ["LO", "g", id, 1, items, again];

const ECHO_LINES = [
  [0, "S", "1.15.8", "ROGUE", "Human", 3, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  [100, "IN", SMALLFISH, "Raw Brilliant Smallfish"],
  [100, "IN", MACKEREL, "Raw Slitherskin Mackerel"],
  [100, "IN", CLAM, "Big-mouth Clam"],
  [100, "IN", BLACK_PEARL, "Black Pearl"],
  [100, "IN", FISHING_POLE, "Fishing Pole"],
  [100, "SN", SHINY_BAUBLE_SPELL, "Shiny Bauble"],
  [100, "IN", COPPER_ORE, "Copper Ore"],
  [100, "IN", PEACEBLOOM, "Peacebloom"],
  [100, "IN", SILVERLEAF, "Silverleaf"],
  [100, "IN", SCRAPS, "Ruined Leather Scraps"],
  [100, "IN", LIGHT_LEATHER, "Light Leather"],
  [100, "IN", CHARRED_WOLF_MEAT, "Charred Wolf Meat"],
  [100, "SN", WOLF_MEAT_RECIPE, "Charred Wolf Meat"],
  [500, "SL", FISHING, 5, 75, 0, "s"],
  [520, "SL", HERBALISM, 15, 75, 0, "s"],
  [540, "SL", MINING, 20, 75, 0, "s"],
  [560, "SL", SKINNING, 30, 75, 0, "s"],
  [580, "SL", COOKING, 50, 75, 0, "s"],
  [600, "SL", BLACKSMITHING, 40, 75, 0, "s"],

  [2000, "G", 16, FISHING_POLE, "", "", ""],
  [2500, "P", ELWYNN, 3800, 5200, 0],
  [3000, "C", SHINY_BAUBLE_SPELL, ""],
  [3200, ...LURE],

  [10000, "C", FISHING_CAST],
  [18200, ...cast(8.2)],
  [18900, ...bobber(`${SMALLFISH}x1`)],
  [19400, "SL", FISHING, 6, 75, 0, ""],

  [25000, "C", FISHING_CAST],
  [31000, ...cast(6.0)],

  [36000, "C", FISHING_CAST],
  [41500, ...cast(5.5)],
  [42000, ...bobber(`${MACKEREL}x1`)],

  [46000, "C", FISHING_CAST],
  [52000, ...cast(6.0)],
  [52500, ...bobber(`${CLAM}x1`)],
  [58000, "LO", "i", CLAM, 1, `${BLACK_PEARL}x1`, ""],

  [64000, "C", FISHING_CAST],
  [71000, ...cast(7.0)],

  [76000, "C", FISHING_CAST],
  [83300, ...cast(7.3)],
  [83900, ...bobber(`${SMALLFISH}x1`)],

  [98000, "P", ELWYNN, 4600, 6100, 0],
  [106000, ...gather(MINING_CAST, 3.0)],
  [106600, ...node(COPPER_VEIN, `${COPPER_ORE}x2`, 0)],
  [107000, "SL", MINING, 21, 75, 0, ""],
  [130000, ...gather(MINING_CAST, 3.0)],
  [130600, ...node(COPPER_VEIN, `${COPPER_ORE}x1`, 1)],
  [140000, ...gather(MINING_CAST, 3.0)],
  [140600, ...node(COPPER_VEIN, `${COPPER_ORE}x1`, 0)],

  [148000, "P", ELWYNN, 4500, 6000, 0],
  [152000, ...gather(HERB_CAST, 2.0)],
  [152500, ...node(PEACEBLOOM_NODE, `${PEACEBLOOM}x1`, 0)],
  [165000, ...gather(HERB_CAST, 2.0)],
  [165500, ...node(PEACEBLOOM_NODE, `${PEACEBLOOM}x1`, 0)],
  [178000, ...gather(HERB_CAST, 2.0)],
  [178500, ...node(SILVERLEAF_NODE, `${SILVERLEAF}x1`, 0)],

  [198000, "P", ELWYNN, 4450, 6060, 0],
  [203000, ...gather(SKINNING_CAST, 1.5)],
  [203500, "LO", "c", STONETUSK_BOAR, 1, `${SCRAPS}x1`, 1],
  [220000, ...gather(SKINNING_CAST, 1.5)],
  [220500, "LO", "c", STONETUSK_BOAR, 2, `${LIGHT_LEATHER}x1,${SCRAPS}x1`, 1],

  [250000, "TS", 1, COOKING, "Cooking", 50, 75, 0, ""],
  [251000, "CB", WOLF_MEAT_RECIPE],
  [251100, "RD", WOLF_MEAT_RECIPE, 1, 1, 1, 0, 0, 0, 0, ""],
  [254000, ...craft(CHARRED_WOLF_MEAT, WOLF_MEAT_RECIPE)],
  [254800, "SL", COOKING, 51, 75, 0, ""],
  [258000, "CB", WOLF_MEAT_RECIPE],
  [261000, ...craft(CHARRED_WOLF_MEAT, WOLF_MEAT_RECIPE)],
  [264000, "CB", WOLF_MEAT_RECIPE],
  [264100, "RD", WOLF_MEAT_RECIPE, 2, 1, 1, 0, 0, 0, 0, ""],
  [267000, ...craft(CHARRED_WOLF_MEAT, WOLF_MEAT_RECIPE)],
  [268000, "CS", 0],
  [270000, "TS", 0],

  [275000, "TS", 1, BLACKSMITHING, "Blacksmithing", 40, 75, 0, ""],
  [276000, "CB", SHARPENING_RECIPE],
  [279000, ...craft(SHARPENING_STONE, SHARPENING_RECIPE)],
  [282000, "CB", SHARPENING_RECIPE],
  [285000, ...craft(SHARPENING_STONE, SHARPENING_RECIPE)],
  [286000, "CS", 0],
  [287000, "TS", 0],

  [292000, "C", CAMPFIRE_SPELL, ""],
  [300000, "A", 1, NEAR_CAMPFIRE, "", 0, "", ""],
  [305000, "A", 1, SITTING, "", 0, "", ""],
  [320000, "A", 1, CAMP_BENEFITS, "", 3600, "", ""],
  [380000, "A", 0, SITTING, 75],
  [390000, "A", 0, NEAR_CAMPFIRE, 90],

  [392000, "C", SINISTER_STRIKE, 1.5],
  [393000, "C", SINISTER_STRIKE, 1.5],
  [394000, "C", EVISCERATE, ""],
  [395000, "DM", "d", 5300, 1060.0, 5.0],
  [396000, "DM", "h", 2400, 480.0, 5.0],
  [397000, "DM", "d", 2100, 700.0, 3.0],
  [398000, "DM", "h", 1900, 475.0, 4.0],

  [400000, "ES"],
];

const STRANGER_LINES = [
  [0, "S", "1.15.8", "MAGE", "Human", 3, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", ELWYNN, 0, "none", 0, "Elwynn Forest", "Northshire Valley"],
  [100, "IN", LINEN_CLOTH, "Linen Cloth"],
  [100, "IN", LINEN_BOLT, "Bolt of Linen Cloth"],
  [100, "SN", LINEN_BOLT_RECIPE, "Bolt of Linen Cloth"],
  [500, "SL", TAILORING, 10, 75, 0, "s"],

  [5000, "TS", 1, TAILORING, "Tailoring", 10, 75, 0, ""],
  [6000, "CB", LINEN_BOLT_RECIPE],
  [9000, ...craft(LINEN_BOLT, LINEN_BOLT_RECIPE)],
  [12000, "CB", LINEN_BOLT_RECIPE],
  [15000, ...craft(LINEN_BOLT, LINEN_BOLT_RECIPE)],
  [16000, "CS", 0],
  [17000, "TS", 0],

  [22000, "C", CAMPFIRE_SPELL, ""],
  [30000, "A", 1, NEAR_CAMPFIRE, "", 0, "", ""],
  [35000, "A", 1, SITTING, "", 0, "", ""],
  [50000, "A", 1, CAMP_BENEFITS, "", 3600, "", ""],
  [110000, "A", 0, SITTING, 75],
  [120000, "A", 0, NEAR_CAMPFIRE, 90],

  [130000, "ES"],
];

const BUILDS = [
  { character: "Echo-Witness", session: 1795270000, lines: ECHO_LINES },
  { character: "Tailor-Stranger", session: 1795360000, lines: STRANGER_LINES },
];

function partsOf(character, session, lines) {
  const byGroup = new Map();
  for (const [at, code, ...fields] of lines) {
    const group = GROUP_OF[code];
    if (group === undefined) throw new Error(`no sharing group for the line code ${code}`);
    if (!byGroup.has(group)) byGroup.set(group, []);
    byGroup.get(group).push({ at, text: [code, ...fields].join("\t") });
  }
  return GROUP_ORDER.filter((group) => byGroup.has(group)).map((group) => {
    let before = 0;
    const rows = byGroup.get(group).sort((a, b) => a.at - b.at).map(({ at, text }) => {
      const row = `${at - before}\t${text}`;
      before = at;
      return row;
    });
    const text = [["scarlet-part", 1, character, session, group].join("\t"), ...rows].join("\n") + "\n";
    return { group, text, lines: rows.length };
  });
}

function build({ character, session, lines }) {
  const dir = path.join(FIXTURES, character);
  fs.mkdirSync(dir, { recursive: true });
  const manifestPath = path.join(dir, "manifest.json");
  const manifest = fs.existsSync(manifestPath) ? JSON.parse(fs.readFileSync(manifestPath, "utf8")) : { character, parts: [] };
  const kept = manifest.parts.filter((part) => part.session !== session);
  for (const part of manifest.parts) {
    if (part.session === session) fs.rmSync(path.join(dir, `${part.id}.part`), { force: true });
  }

  const added = partsOf(character, session, lines).map(({ group, text, lines: count }) => {
    const id = crypto.createHash("sha256").update(text).digest("hex");
    fs.writeFileSync(path.join(dir, `${id}.part`), text);
    return { group, id, lines: count, raw_bytes: Buffer.byteLength(text), session };
  });

  manifest.parts = [...kept, ...added];
  manifest.set_digest = xorDigest(manifest.parts.map((part) => part.id));
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
  console.log(`${character}: ${kept.length} parts kept, ${added.length} written for session ${session}`);
}

for (const one of BUILDS) build(one);
