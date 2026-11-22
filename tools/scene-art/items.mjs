#!/usr/bin/env node
// Writes every item's icon from an installed WoW Forever client: `items.json` (item id to icon file id) and one PNG per
// icon file. Reads the client through the local file server (README.md). The output is Blizzard's art: it goes on the
// maps disk, never into git.
// Usage: node tools/scene-art/items.mjs <Item table as CSV> <out dir> [server]
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, statSync, unlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const WORKERS = 8;
const FETCH_MS = 30000;
const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

const [csvPath, out, server = 'http://127.0.0.1:5123'] = process.argv.slice(2);
if (!csvPath || !out) {
    console.error('usage: node tools/scene-art/items.mjs <Item table as CSV> <out dir> [server]');
    process.exit(2);
}

const [head, ...lines] = readFileSync(csvPath, 'utf8').split(/\r?\n/).filter(Boolean);
const columns = head.split(',');
const idAt = columns.indexOf('ID');
const iconAt = columns.indexOf('IconFileDataID');
if (idAt < 0 || iconAt < 0) {
    console.error('the CSV needs the columns ID and IconFileDataID');
    process.exit(2);
}

const iconOf = new Map();
for (const line of lines) {
    if (line.includes('"')) continue;
    const cells = line.split(',');
    const item = Number(cells[idAt]);
    const icon = Number(cells[iconAt]);
    if (Number.isInteger(item) && Number.isInteger(icon) && icon > 0) iconOf.set(item, icon);
}

const folder = join(out, 'icons');
mkdirSync(folder, { recursive: true });
for (const name of readdirSync(folder)) {
    if (name.endsWith('.part')) unlinkSync(join(folder, name));
}
const files = [...new Set(iconOf.values())];
const have = new Set();
let fetched = 0;
let kept = 0;
let skipped = 0;
let bytes = 0;
const queue = [...files];

const fetchIcon = async (id) => {
    const r = await fetch(`${server}/casc/blp2png?fileDataID=${id}`, { signal: AbortSignal.timeout(FETCH_MS) });
    if (!r.ok) throw new Error(String(r.status));
    const png = Buffer.from(await r.arrayBuffer());
    if (!png.subarray(0, PNG.length).equals(PNG)) throw new Error('not a PNG');
    return png;
};

const work = async () => {
    for (let id = queue.pop(); id !== undefined; id = queue.pop()) {
        const path = join(folder, `${id}.png`);
        if (existsSync(path) && statSync(path).size > 0) {
            have.add(id);
            bytes += statSync(path).size;
            kept++;
            continue;
        }
        try {
            const png = await fetchIcon(id);
            writeFileSync(`${path}.part`, png);
            renameSync(`${path}.part`, path);
            have.add(id);
            bytes += png.length;
            fetched++;
        } catch (e) {
            skipped++;
            console.warn(`icon file ${id}: ${e.message}`);
        }
    }
};
await Promise.all(Array.from({ length: WORKERS }, work));

const items = {};
for (const item of [...iconOf.keys()].sort((a, b) => a - b)) {
    if (have.has(iconOf.get(item))) items[item] = iconOf.get(item);
}
const json = JSON.stringify(items);
const mapPath = join(out, 'items.json');
if (!existsSync(mapPath) || readFileSync(mapPath, 'utf8') !== json) {
    writeFileSync(`${mapPath}.part`, json);
    renameSync(`${mapPath}.part`, mapPath);
}
console.log(`${Object.keys(items).length} items, ${have.size} icon files (${fetched} fetched, ${kept} already there, ${skipped} skipped), ${(bytes / 1048576).toFixed(1)} MiB -> ${out}`);
