// Builds public/map-sheets: the Scarlet app's dark survey sheets, one per zone map, with each zone's land mask and place names.
//
//   node sheets.mjs <scarlet-app checkout>/app/maps-dark <OpenMap folder> <labels folder> [--out <folder>]
//
// Writes <out>/<uiMapID>.webp (the app's sheet, copied), <out>/<uiMapID>.mask.png (256 px, white, the alpha is the zone's own
// land from OpenMap's MapZoneLand/<uiMapID>.tga, feathered a few pixels past the shore) and <out>/manifest.json: per sheet its
// rectangle on the world (docs/map-rects.tsv), its size, whether it has a mask, and its place names from
// <labels folder>/<uiMapID>.labels.json (written by the app's tools/map-labels.mjs) as [name, kind, u, v] in world coordinates.
// A sheet with no rectangle is left out. The sheets are drawn by the app's tools/render-dark.mjs and copy no pixel of the game's art.

import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const here = dirname(fileURLToPath(import.meta.url));
const WORLD = 947;
const FEATHER = 4;
const SOLID_FROM = 0.4;
const CLEAR_UNDER = 0.02;

const [sheetsDir, addon, labelsDir] = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const outFlag = process.argv.indexOf('--out');
const out = outFlag > 0 ? resolve(process.argv[outFlag + 1]) : resolve(here, '..', '..', 'public', 'map-sheets');
if (!sheetsDir || !addon || !labelsDir) {
    console.error('usage: node sheets.mjs <scarlet-app checkout>/app/maps-dark <OpenMap folder> <labels folder> [--out <folder>]');
    process.exit(1);
}

const rects = new Map([[WORLD, [0, 0, 1, 1]]]);
for (const line of readFileSync(join(here, '..', '..', 'docs', 'map-rects.tsv'), 'utf8').trim().split('\n').slice(1)) {
    const [id, , ...rect] = line.trim().split('\t');
    rects.set(Number(id), rect.map(Number));
}

function landAlpha(file) {
    const tga = readFileSync(file);
    if (tga[1] !== 0 || tga[2] !== 2 || tga[16] !== 32) {
        throw new Error(`${file}: not an uncompressed 32-bit TGA`);
    }
    const width = tga.readUInt16LE(12);
    const height = tga.readUInt16LE(14);
    const start = 18 + tga[0];
    const topFirst = (tga[17] & 0x20) !== 0;
    const alpha = Buffer.alloc(width * height);
    for (let y = 0; y < height; y++) {
        const row = topFirst ? y : height - 1 - y;
        for (let x = 0; x < width; x++) {
            alpha[y * width + x] = tga[start + (row * width + x) * 4 + 3];
        }
    }
    return { width, height, alpha };
}

async function writeMask(file, target) {
    const { width, height, alpha } = landAlpha(file);
    const soft = await sharp(alpha, { raw: { width, height, channels: 1 } }).blur(FEATHER).toColourspace('b-w').raw().toBuffer();
    const rgba = Buffer.alloc(width * height * 4, 255);
    for (let p = 0; p < soft.length; p++) {
        const t = Math.min(1, Math.max(0, (soft[p] / 255 - CLEAR_UNDER) / (SOLID_FROM - CLEAR_UNDER)));
        rgba[p * 4 + 3] = Math.round(t * t * (3 - 2 * t) * 255);
    }
    await sharp(rgba, { raw: { width, height, channels: 4 } }).png({ compressionLevel: 9 }).toFile(target);
}

function placeNames(id, [u0, v0, u1, v1]) {
    const file = join(labelsDir, `${id}.labels.json`);
    if (!existsSync(file)) {
        return [];
    }
    const round = (n) => Math.round(n * 1e5) / 1e5;
    return JSON.parse(readFileSync(file, 'utf8')).labels.map((label) => [
        label.name,
        label.kind,
        round(u0 + (label.x / 10000) * (u1 - u0)),
        round(v0 + (label.y / 10000) * (v1 - v0)),
    ]);
}

mkdirSync(out, { recursive: true });
const sheets = {};
const hash = createHash('sha1');
for (const name of readdirSync(sheetsDir).filter((f) => f.endsWith('.webp')).sort((a, b) => parseInt(a, 10) - parseInt(b, 10))) {
    const id = parseInt(name, 10);
    const rect = rects.get(id);
    if (!rect) {
        console.log(`${id}: no rectangle in docs/map-rects.tsv, left out`);
        continue;
    }
    copyFileSync(join(sheetsDir, name), join(out, name));
    const { width, height } = await sharp(join(out, name)).metadata();
    const land = join(addon, 'Media', 'Textures', 'Forever', 'MapZoneLand', `${id}.tga`);
    const mask = existsSync(land);
    if (mask) {
        await writeMask(land, join(out, `${id}.mask.png`));
        hash.update(readFileSync(join(out, `${id}.mask.png`)));
    }
    hash.update(readFileSync(join(out, name)));
    sheets[id] = { rect, width, height, mask, names: placeNames(id, rect) };
}
hash.update(JSON.stringify(sheets));
writeFileSync(join(out, 'manifest.json'), `${JSON.stringify({ version: hash.digest('hex').slice(0, 10), world: WORLD, sheets })}\n`);
console.log(`${Object.keys(sheets).length} sheets, ${Object.values(sheets).filter((s) => s.mask).length} masks, ${Object.values(sheets).reduce((n, s) => n + s.names.length, 0)} names in ${out}`);
