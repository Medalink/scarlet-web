// The scenes tools/scene-art builds: each one's model file ids by the key its scene file in resources/js/scene/scenes
// places, and its icon file ids by the id the page asks for. A model is its file id, or `{ file, display }` for a
// creature drawn with the skin of a creature display (build.mjs). build.mjs writes the art from this table and
// scripts/art-published.mjs checks the art store against it.
export const SCENES = {
    fishing: {
        models: { bobber: 201000, school: 219405, box: 189092, pole: 189093, rack: 189091 },
        icons: {},
    },
    herbalism: {
        models: { peacebloom: 219481, silverleaf: 219487, mageroyal: 219470, briarthorn: 219502, bruiseweed: 219440, kingsblood: 219443, fadeleaf: 219449, khadgar: 219468, goldthorn: 219462, lotus: 219483 },
        icons: {},
    },
    mining: {
        models: { copper: 219514, tin: 219568, iron: 219532, gold: 219524, mithril: 219541, truesilver: 219569, thorium: 219566, forge: 219412, pick: 166560, cart: 189827, lantern: 190869 },
        icons: {},
    },
    skinning: {
        models: { rack: 197200, hides: 199795, pile: 199796, bag: 878854, lantern: 190869 },
        icons: {},
    },
    alchemy: {
        models: { set3: 219401, cauldron: 198100, set: 219399, table: 189914, bowl: 199397, bottle: 198057, green: 198060, books: 198056 },
        icons: {},
    },
    blacksmithing: {
        models: { anvil: 219402, hammer: 198648, forge: 219414, bellows: 198009, grind: 190587, rack: 198636, workbench: 197224, iron: 199861, copper: 199860, mithril: 199862 },
        icons: {},
    },
    enchanting: {
        models: { lectern: 197579, pool: 942868, books: 198056, low: 198055, candelabra: 198073, candle: 199806 },
        icons: {},
    },
    engineering: {
        models: { lathe: 219382, machine: 197682, crane: 197680, toolbox: 197621, wrench: 197622, keg: 197463, bundle: 197331, mortar: 198278 },
        icons: {},
    },
    leatherworking: {
        models: { rack: 197200, workbench: 197224, boots: 199363, pair: 199364, hides: 199795, saddle: 199243, stand: 199245, bag: 878854, lantern: 190869 },
        icons: {},
    },
    tailoring: {
        models: { loom: 198338, wheel: 608748, table: 197571, blue: 198124, spool: 198128, yellow: 198131, green: 198125, red: 198126, white: 198127, orange: 199375 },
        icons: {},
    },
    cooking: {
        models: { pot: 195429, boar: 199231, rack: 199205, table: 197555, platter: 198228, bread: 198212, sack: 190015 },
        icons: {},
    },
    'first-aid': {
        models: { tent: 197388, bed: 197266, crate: 199636, horde: 199639, lantern: 190869 },
        icons: {},
    },
    camp: {
        models: { campfire: 8202386, tent: 197389, chair: 198116 },
        icons: {
            1229519: 136112, 1230124: 136077, 1230172: 132335, 1230164: 136101, 1230587: 237551,
            1229513: 135740, 1229718: 236225, 1230098: 135979, 1230653: 135879,
        },
    },
    warrior: {
        models: { dummy: { file: 125259, display: 3019 }, weaponrack: 198636, armorstand: 197911, brazier: 199132 },
        icons: {},
    },
    paladin: {
        models: { banner: 197996, podium: 198407, warhorse: { file: 126407, display: 8469 }, shieldrack: 200030 },
        icons: {},
    },
    hunter: {
        models: { campfire: 189705, cat: { file: 124781, display: 4424 }, wolf: { file: 126487, display: 802 }, boar: { file: 123090, display: 704 }, owl: { file: 125378, display: 4877 } },
        icons: {},
    },
    rogue: {
        models: { chest: 196976, crate: 190543, vials: 198626, sack: 199745, gold: 200136, barrel: 190885, lantern: 147001 },
        icons: {},
    },
    priest: {
        models: { pew: 198390, podium: 198407, altar: 197831, candelabra: 198073, candles: 198076 },
        icons: {},
    },
    shaman: {
        models: { fire: { file: 126012, display: 4589 }, earth: { file: 126009, display: 4588 }, water: { file: 126041, display: 4587 }, air: { file: 125990, display: 4590 } },
        icons: {},
    },
    mage: {
        models: { portal: 197047, sheep: { file: 125865, display: 117366 }, alchemy: 200767, bookshelf: 189993 },
        icons: {},
    },
    warlock: {
        models: { circle: 201649, voidwalker: { file: 126369, display: 1132 }, succubus: { file: 126081, display: 4162 }, imp: { file: 124622, display: 4449 }, felhunter: { file: 123878, display: 850 } },
        icons: {},
    },
    druid: {
        models: { bear: { file: 7581815, display: 2281 }, cat: { file: 123682, display: 892 }, travel: { file: 126171, display: 918 }, aquatic: { file: 125835, display: 2428 } },
        icons: {},
    },
};

/** The model file id of a table entry: the entry itself, or its `file` when it names a creature display. */
export const modelFile = (entry) => (typeof entry === 'number' ? entry : entry.file);

/** The creature display id of a table entry, or null for a model drawn as its file has it. */
export const modelDisplay = (entry) => (typeof entry === 'number' ? null : entry.display);
