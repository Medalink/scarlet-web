#!/usr/bin/env node
// Writes one profession scene's game art from an installed WoW Forever client: its models as one JSON file, their
// textures and the page's icons as PNG. Reads the client through a local file server (README.md). The output is
// Blizzard's art: it goes on the maps disk, never into git.
// Usage: node tools/scene-art/build.mjs <scene> <out dir> [server]
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { readModel } from '../camp-art/m2.mjs';

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
