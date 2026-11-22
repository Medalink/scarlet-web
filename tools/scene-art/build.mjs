#!/usr/bin/env node
// Writes one scene's game art from an installed WoW Forever client: its models as one JSON file, their
// textures and the camp page's icons as PNG (item icons are items.mjs). Reads the client through a local file
// server (README.md). The output is Blizzard's art: it goes on the maps disk, never into git.
// Usage: node tools/scene-art/build.mjs <scene> <out dir> [server]
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { readModel } from './m2.mjs';
import { SCENES } from './scenes.mjs';

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
