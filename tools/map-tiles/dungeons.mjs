// Builds the dungeon floor maps the dungeon pages show, from the maps the OpenMap addon ships.
//
//   node dungeons.mjs <OpenMap folder> <out folder, normally storage/app/maps/dungeons>
//
// OpenMap has a picture per floor of each instance, as 12 tiles of 256 px (4 across, 3 down, the picture the first
// 1002 x 668 px of them), from three tables under Data/Forever:
//   DungeonMaps.lua     Blizzard's own dungeon maps, tiles by file id in Media/Textures/Forever/MapTile/<id>.blp
//   DungeonDrawn.lua    maps of WoW Forever's own dungeons drawn by a player who lets OpenMap use them, with a credit
//   DungeonMinimap.lua  maps laid from the game's minimap pictures, for a dungeon with neither of the others
// An instance takes the first table that has it, as OpenMap does. DungeonBosses.lua gives each boss a floor and a
// place on that floor's picture, under the instance's map id, or "minimap:<map id>" for a map of the third table.
// A drawn or minimap tile is an uncompressed 24 or 32 bit TGA.
//
// Each floor is written as <out>/<instance map id>/<floor>.webp in the site's dark look: the picture's light and
// dark kept, most of its colour taken out, on the sheet's greys. <out>/dungeons.json lists the floors, their names,
// credits and bosses, with a `version` that changes when any picture or the list does.
//
// The pictures are Blizzard's art and a player's, so <out> stays out of git like the map tiles (storage/app/maps is
// ignored). `bin/deploy maps` carries it to the server. A page shows a dungeon's map only while dungeons.json is there.

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import sharp from 'sharp';
import { decodeBlp } from './blp.mjs';

const TILE = 256;
const ACROSS = 4;
const DOWN = 3;
const WIDTH = 1002;
const HEIGHT = 668;
const GROUND = [20, 20, 22];
const LIGHT = [196, 194, 204];
const COLOUR_KEPT = 0.3;
const GAMMA = 1.25;
const ENCODE = { quality: 84, effort: 6 };

const [addon, out] = process.argv.slice(2);
if (!addon || !out || basename(resolve(out)) !== 'dungeons' || resolve(addon).startsWith(resolve(out))) {
    console.error('usage: node dungeons.mjs <OpenMap folder> <out folder named dungeons, which is replaced>');
    process.exit(2);
}
const textures = join(addon, 'Media', 'Textures', 'Forever');
const table = (name) => readFileSync(join(addon, 'Data', 'Forever', name), 'utf8').split(/\r?\n/);

function floorsOf(file, tilePath) {
    const found = new Map();
    let instance = null;
    for (const line of table(file)) {
        const head = line.match(/^\t\[(\d+)\] = \{/);
        if (head) {
            instance = Number(head[1]);
            found.set(instance, []);
            continue;
        }
        if (/^\t\S/.test(line)) {
            instance = null;
        }
        const tiles = line.match(/tiles = \{([^}]*)\}/);
        if (!tiles || instance === null) {
            continue;
        }
        const names = tiles[1].split(',').map((t) => t.trim().replace(/^P \.\. "|"$/g, '')).filter(Boolean);
        if (names.length !== ACROSS * DOWN) {
            throw new Error(`${file}: a floor of ${instance} has ${names.length} tiles, not ${ACROSS * DOWN}`);
        }
        const rest = line.replace(tiles[0], '');
        found.get(instance).push({
            tiles: names.map(tilePath),
            name: rest.match(/\bname = "([^"]*)"/)?.[1] ?? null,
            credit: rest.match(/\bcredit = "([^"]*)"/)?.[1] ?? null,
        });
    }
    return found;
}

function bossesByFloor(file) {
    const found = new Map();
    let key = null;
    for (const line of table(file)) {
        const head = line.match(/^\t\[(?:(\d+)|"([^"]+)")\] = \{/);
        if (head) {
            key = head[1] ?? head[2];
            found.set(key, new Map());
            continue;
        }
        const boss = line.match(/^\t\t\["((?:[^"\\]|\\.)*)"\] = \{ (\d+), ([\d.]+), ([\d.]+) \}/);
        if (!boss || key === null) {
            continue;
        }
        const floor = Number(boss[2]);
        const onFloor = found.get(key).get(floor) ?? [];
        onFloor.push({ name: boss[1].replace(/\\(.)/g, '$1'), x: Number(boss[3]), y: Number(boss[4]) });
        found.get(key).set(floor, onFloor);
    }
    return found;
}

function tileRgb(file) {
    const data = readFileSync(file);
    const rgb = Buffer.alloc(TILE * TILE * 3);
    if (file.endsWith('.blp')) {
        const { width, height, rgba } = decodeBlp(data);
        if (width !== TILE || height !== TILE) {
            throw new Error(`${file} is ${width} x ${height}, not ${TILE} x ${TILE}`);
        }
        for (let p = 0; p < TILE * TILE; p++) {
            rgba.copy(rgb, p * 3, p * 4, p * 4 + 3);
        }
        return rgb;
    }
    const bytes = data[16] / 8;
    if (data[1] !== 0 || data[2] !== 2 || (bytes !== 3 && bytes !== 4) || data.readUInt16LE(12) !== TILE || data.readUInt16LE(14) !== TILE) {
        throw new Error(`${file} is not an uncompressed ${TILE} x ${TILE} true-colour TGA`);
    }
    const start = 18 + data[0];
    const topFirst = (data[17] & 0x20) !== 0;
    for (let y = 0; y < TILE; y++) {
        const row = topFirst ? y : TILE - 1 - y;
        for (let x = 0; x < TILE; x++) {
            const from = start + (row * TILE + x) * bytes;
            const to = (y * TILE + x) * 3;
            rgb[to] = data[from + 2];
            rgb[to + 1] = data[from + 1];
            rgb[to + 2] = data[from];
        }
    }
    return rgb;
}

async function darkPicture(tiles) {
    const sheet = Buffer.alloc(WIDTH * HEIGHT * 3);
    tiles.forEach((file, n) => {
        const tile = tileRgb(file);
        const left = (n % ACROSS) * TILE;
        const top = Math.floor(n / ACROSS) * TILE;
        for (let y = 0; y < TILE && top + y < HEIGHT; y++) {
            const width = Math.min(TILE, WIDTH - left);
            tile.copy(sheet, ((top + y) * WIDTH + left) * 3, y * TILE * 3, (y * TILE + width) * 3);
        }
    });
    for (let p = 0; p < sheet.length; p += 3) {
        const light = (0.2126 * sheet[p] + 0.7152 * sheet[p + 1] + 0.0722 * sheet[p + 2]) / 255;
        const share = light ** GAMMA;
        for (let c = 0; c < 3; c++) {
            const grey = GROUND[c] + (LIGHT[c] - GROUND[c]) * share;
            const tinted = grey * (1 + COLOUR_KEPT * (sheet[p + c] / 255 - light) / Math.max(light, 0.08));
            sheet[p + c] = Math.max(0, Math.min(255, Math.round(tinted)));
        }
    }
    return sharp(sheet, { raw: { width: WIDTH, height: HEIGHT, channels: 3 } }).webp(ENCODE).toBuffer();
}

async function main() {
    const sources = [
        { key: (id) => String(id), floors: floorsOf('DungeonMaps.lua', (id) => join(textures, 'MapTile', `${id}.blp`)) },
        { key: (id) => String(id), floors: floorsOf('DungeonDrawn.lua', (stem) => join(textures, 'MapDungeonDrawn', `${stem}.tga`)) },
        { key: (id) => `minimap:${id}`, floors: floorsOf('DungeonMinimap.lua', (stem) => join(textures, 'MapDungeonMinimap', `${stem}.tga`)) },
    ];
    const bosses = bossesByFloor('DungeonBosses.lua');
    const instances = [...new Set(sources.flatMap((source) => [...source.floors.keys()]))].sort((a, b) => a - b);
    const sourceOf = (id) => sources.find((source) => source.floors.has(id));
    const missing = instances.flatMap((id) => sourceOf(id).floors.get(id)).flatMap((floor) => floor.tiles).find((file) => !existsSync(file));
    if (missing) {
        throw new Error(`${missing} is not in the addon; nothing was written`);
    }
    rmSync(out, { recursive: true, force: true });
    const maps = {};
    const hash = createHash('sha256');
    let count = 0;
    let bytes = 0;
    for (const id of instances) {
        const source = sourceOf(id);
        const pins = bosses.get(source.key(id)) ?? new Map();
        const floors = [];
        mkdirSync(join(out, String(id)), { recursive: true });
        for (const [n, floor] of source.floors.get(id).entries()) {
            const picture = await darkPicture(floor.tiles);
            writeFileSync(join(out, String(id), `${n + 1}.webp`), picture);
            hash.update(picture);
            bytes += picture.length;
            count++;
            floors.push({ name: floor.name, credit: floor.credit, bosses: pins.get(n + 1) ?? [] });
        }
        maps[id] = { floors };
    }
    hash.update(JSON.stringify(maps));
    const version = hash.digest('hex').slice(0, 10);
    writeFileSync(join(out, 'dungeons.json'), JSON.stringify({ version, width: WIDTH, height: HEIGHT, maps }));
    console.log(`${instances.length} instances, ${count} floors, ${(bytes / 1048576).toFixed(1)} MiB, version ${version}`);
}

await main();
