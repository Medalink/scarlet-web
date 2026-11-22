#!/usr/bin/env node
// Writes one scene's game art from an installed WoW Forever client: its models as one JSON file, their
// textures and the page's icons as PNG. Reads the client through a local file server (README.md). The output is
// Blizzard's art: it goes on the maps disk, never into git.
// Usage: node tools/scene-art/build.mjs <scene> <out dir> [server]
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { readModel } from './m2.mjs';

const SCENES = {
    fishing: {
        models: { bobber: 201000, school: 219405, box: 189092, pole: 189093, rack: 189091 },
        icons: { 4603: 133887, 13755: 133899, 13422: 133897, 6362: 133890, 6359: 134299, 7973: 134433, 13754: 134301, 21153: 133907, 6351: 132765 },
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
};

const [name, out, server = 'http://127.0.0.1:5123'] = process.argv.slice(2);
const scene = Object.hasOwn(SCENES, name ?? '') ? SCENES[name] : null;
if (!scene || !out) {
    console.error(`usage: node tools/scene-art/build.mjs <${Object.keys(SCENES).join('|')}> <out dir> [server]`);
    process.exit(2);
}

const get = async (path) => {
    const r = await fetch(server + path);
    if (!r.ok) throw new Error(`${path}: ${r.status}`);
    return Buffer.from(await r.arrayBuffer());
};
const file = (id) => get(`/casc/fdid?fileDataID=${id}&filename=${id}`);
const png = (id) => get(`/casc/blp2png?fileDataID=${id}`);

mkdirSync(join(out, 'tex'), { recursive: true });
mkdirSync(join(out, 'icons'), { recursive: true });
const models = {};
const textures = new Set();
for (const [key, id] of Object.entries(scene.models)) {
    models[key] = await readModel(id, file);
    for (const t of models[key].textures) if (t.fdid) textures.add(t.fdid);
    console.log(`${key}: ${models[key].batches.length} batches, ${models[key].particles.length} emitters, ${models[key].lights.length} lights`);
}
for (const id of textures) writeFileSync(join(out, 'tex', `${id}.png`), await png(id));
for (const [icon, id] of Object.entries(scene.icons)) writeFileSync(join(out, 'icons', `${icon}.png`), await png(id));
writeFileSync(join(out, `${name}.json`), JSON.stringify({ models, textures: [...textures] }));
console.log(`${textures.size} textures, ${Object.keys(scene.icons).length} icons -> ${out}`);
