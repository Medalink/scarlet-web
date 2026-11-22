#!/usr/bin/env node
// Writes the camp page's game art from an installed WoW Forever client: the campfire, tent and chair models as
// one JSON file, their textures and the Camp Benefits icons as PNG. Reads the client through a local file server
// (README.md). The output is Blizzard's art: it goes on the maps disk, never into git.
// Usage: node tools/camp-art/build.mjs <out dir> [server]
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { readModel } from './m2.mjs';

const [out, server = 'http://127.0.0.1:5123'] = process.argv.slice(2);
if (!out) {
    console.error('usage: node tools/camp-art/build.mjs <out dir> [server]');
    process.exit(2);
}

const MODEL_FILE_IDS = { campfire: 8202386, tent: 197389, chair: 198116 };
const ICON_FILE_ID_BY_SPELL = {
    1229519: 136112, 1230124: 136077, 1230172: 132335, 1230164: 136101, 1230587: 237551,
    1229513: 135740, 1229718: 236225, 1230098: 135979, 1230653: 135879,
};

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
for (const [key, id] of Object.entries(MODEL_FILE_IDS)) {
    models[key] = await readModel(id, file);
    for (const t of models[key].textures) if (t.fdid) textures.add(t.fdid);
    console.log(`${key}: ${models[key].batches.length} batches, ${models[key].particles.length} emitters, ${models[key].lights.length} lights`);
}
for (const id of textures) writeFileSync(join(out, 'tex', `${id}.png`), await png(id));
for (const [spell, id] of Object.entries(ICON_FILE_ID_BY_SPELL)) writeFileSync(join(out, 'icons', `${spell}.png`), await png(id));
writeFileSync(join(out, 'camp.json'), JSON.stringify({ models, textures: [...textures] }));
console.log(`${textures.size} textures, ${Object.keys(ICON_FILE_ID_BY_SPELL).length} icons -> ${out}`);
