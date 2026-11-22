#!/usr/bin/env node
// Says whether the art store holds the art this commit draws, and exits 1 when it does not. The flow runs it as the
// ship gate `art` on medaware, where the store is (docs/HOSTING.md, Map art): the live ring sends the droplet what the
// store holds, so a commit that needs art nobody published would otherwise ship pages without it.
//
// For every scene in tools/scene-art/scenes.mjs the store must hold <scene>/<scene>.json with each model of the table
// under its key and file id, every texture that file lists, and each of the scene's icons. It must also hold the map
// tiles' manifest.json, the dungeon maps' dungeons/dungeons.json and the item icons' items/items.json.
//
//   node scripts/art-published.mjs [store]     the default is $SCARLET_ART_STORE, then medaware's store
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { SCENES, modelDisplay, modelFile } from '../tools/scene-art/scenes.mjs';

const store = process.argv[2] ?? process.env.SCARLET_ART_STORE ?? '/data/medalink/scarlet-web/maps';
const LISTS = ['manifest.json', 'dungeons/dungeons.json', 'items/items.json', 'spells/spells.json'];

function builtScene(file) {
    try {
        const { models = {}, textures = [] } = JSON.parse(readFileSync(join(store, file), 'utf8'));
        return { models, textures };
    } catch {
        return null;
    }
}

function sceneGaps(name, scene) {
    const file = `${name}/${name}.json`;
    const built = builtScene(file);
    if (built === null) return [`${file} is missing or is not JSON`];
    const models = Object.entries(scene.models)
        .filter(([key, entry]) => built.models[key]?.fileDataID !== modelFile(entry) || (built.models[key].display ?? null) !== modelDisplay(entry))
        .map(([key, entry]) => `${file} has no model ${key} built from file ${modelFile(entry)}${modelDisplay(entry) === null ? '' : ` with the skin of display ${modelDisplay(entry)}`}`);
    const textures = built.textures
        .filter((id) => !existsSync(join(store, name, 'tex', `${id}.png`)))
        .map((id) => `${name}/tex/${id}.png is missing`);
    const icons = Object.keys(scene.icons)
        .filter((icon) => !existsSync(join(store, name, 'icons', `${icon}.png`)))
        .map((icon) => `${name}/icons/${icon}.png is missing`);
    return [...models, ...textures, ...icons];
}

if (!existsSync(store)) {
    console.error(`art: no art store at ${store}. This check runs on medaware (docs/HOSTING.md, Map art).`);
    process.exit(1);
}
const gaps = [
    ...LISTS.filter((file) => !existsSync(join(store, file))).map((file) => `${file} is missing`),
    ...Object.entries(SCENES).flatMap(([name, scene]) => sceneGaps(name, scene)),
];
if (gaps.length > 0) {
    console.error(`art: the store at ${store} lacks art this commit draws:`);
    for (const gap of gaps) console.error(`  ${gap}`);
    console.error('Build it in a dev checkout (tools/scene-art/README.md, DEV.md) and publish it with: bin/deploy maps storage/app/maps');
    process.exit(1);
}
console.log(`art: the store holds the art of ${Object.keys(SCENES).length} scenes, the map tiles, the dungeon maps and the item icons`);
