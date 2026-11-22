// Builds public/map-sheets: the Survey look's tile pyramid, made from the Scarlet app's dark survey sheets.
//
//   node sheets.mjs <scarlet-app checkout>/app/maps-dark <OpenMap folder> <labels folder> [--out <folder>]
//
// The world's sheet (uiMap 947) is the base. Each zone's sheet is laid on it at the zone's rectangle
// (docs/map-rects.tsv), cut to the zone's own land (OpenMap's MapZoneLand/<uiMapID>.tga): first every zone well past
// its shore, so the coast is the zone's drawing, then every zone's land alone, so no zone paints its flat neighbour
// tone over the land next door; both fade out towards the sheet's edge. Under the zones the world's sheet is blurred,
// because its own lines, enlarged eight times, are thick smears beside a zone's. Each level is that world scaled down, mixed
// with the world's sheet by ZONES_AT: the zones' drawing is noise when a zone is a thumbnail, so the far levels are
// the world's sheet alone. A zone OpenMap has no MapZoneLand for but does have a MapCore for (Zephras Isle, its own
// continent, placed by rects.mjs's AWAY) is cut to that, and since the world's sheet does not draw it, it is laid on
// the far levels too. A city OpenMap has a MapCityMask for is cut to that and laid over its zone, without the pass
// that reaches past the shore. The levels are cut into 256 px tiles, as build.mjs does for the game's art. Writes <out>/<version>/survey/<z>/<x>/<y>.webp and <out>/manifest.json: the tile
// manifest build.mjs writes (so the viewer reads both the same way), plus `ground`, the colour of a tile that was
// left out because it is the bare sheet, and `places`, each zone's rectangle and its names from
// <labels folder>/<uiMapID>.labels.json (written by the app's tools/map-labels.mjs) as [name, kind, u, v] in world
// coordinates. The sheets are drawn by the app's tools/render-dark.mjs and copy no pixel of the game's art, so the
// tiles are committed.

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { TILE, zoomCount } from './pyramid.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const RECIPE = 'sheet-tiles-6';
const WORLD_MAP = 947;
const WORLD = { width: 1002, height: 668 };
const PX_PER_UNIT = 16;
const LOOK = 'survey';
const SHORE = { units: 7, clear: 0.01, solid: 0.2 };
const LAND = { units: 1.2, clear: 0.25, solid: 0.75 };
const EDGE_FADE = 0.09;
const UNDER_ZONES_BLUR = 7;
const ZONES_AT = { 6: 1, 5: 1, 4: 0.5 };
const APART_PX_PER_UNIT = 2;
const MASKS = [{ folder: 'MapZoneLand' }, { folder: 'MapCore', apart: true }, { folder: 'MapCityMask', city: true }];
const FLAT_WITHIN = 3;
const ENCODE = { quality: 80, effort: 5, smartSubsample: true };

const [sheetsDir, addon, labelsDir] = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const outFlag = process.argv.indexOf('--out');
const out = outFlag > 0 ? resolve(process.argv[outFlag + 1]) : resolve(here, '..', '..', 'public', 'map-sheets');
if (!sheetsDir || !addon || !labelsDir) {
    console.error('usage: node sheets.mjs <scarlet-app checkout>/app/maps-dark <OpenMap folder> <labels folder> [--out <folder>]');
    process.exit(2);
}

const width = WORLD.width * PX_PER_UNIT;
const height = WORLD.height * PX_PER_UNIT;
const apart = { width: WORLD.width * APART_PX_PER_UNIT, height: WORLD.height * APART_PX_PER_UNIT };
apart.cover = Buffer.alloc(apart.width * apart.height);

function landAlpha(file) {
    const tga = readFileSync(file);
    if (tga[1] !== 0 || tga[2] !== 2 || tga[16] !== 32) {
        throw new Error(`${file}: not an uncompressed 32-bit TGA`);
    }
    const w = tga.readUInt16LE(12);
    const h = tga.readUInt16LE(14);
    const start = 18 + tga[0];
    if (tga.length < start + w * h * 4) {
        throw new Error(`${file}: shorter than its header says`);
    }
    const topFirst = (tga[17] & 0x20) !== 0;
    const alpha = Buffer.alloc(w * h);
    for (let y = 0; y < h; y++) {
        const row = topFirst ? y : h - 1 - y;
        for (let x = 0; x < w; x++) {
            alpha[y * w + x] = tga[start + (row * w + x) * 4 + 3];
        }
    }
    return { width: w, height: h, alpha };
}

async function coverage(land, { units, clear, solid }, w, h) {
    const soft = await sharp(land.alpha, { raw: { width: land.width, height: land.height, channels: 1 } })
        .resize(w, h, { fit: 'fill', kernel: 'cubic' })
        .blur(units * PX_PER_UNIT)
        .toColourspace('b-w')
        .raw()
        .toBuffer();
    for (let p = 0; p < soft.length; p++) {
        const t = Math.min(1, Math.max(0, (soft[p] / 255 - clear) / (solid - clear)));
        const x = p % w;
        const y = (p - x) / w;
        const inside = Math.min(1, Math.min(x, w - 1 - x, y, h - 1 - y) / (w * EDGE_FADE));
        soft[p] = Math.round(t * t * (3 - 2 * t) * inside * inside * (3 - 2 * inside) * 255);
    }
    return soft;
}

async function lay(world, zone, edge) {
    const x0 = Math.round(zone.rect[0] * width);
    const y0 = Math.round(zone.rect[1] * height);
    const w = Math.round((zone.rect[2] - zone.rect[0]) * width);
    const h = Math.round((zone.rect[3] - zone.rect[1]) * height);
    const sheet = await sharp(zone.sheet).resize(w, h, { fit: 'fill', kernel: 'lanczos3' }).removeAlpha().raw().toBuffer();
    const cover = await coverage(zone.land, edge, w, h);
    if (zone.apart) {
        await keepOnFarLevels(cover, w, h, zone.rect);
    }
    for (let y = Math.max(0, -y0); y < h && y0 + y < height; y++) {
        for (let x = Math.max(0, -x0); x < w && x0 + x < width; x++) {
            const a = cover[y * w + x];
            if (a === 0) {
                continue;
            }
            const from = (y * w + x) * 3;
            const to = ((y0 + y) * width + x0 + x) * 3;
            world[to] += ((sheet[from] - world[to]) * a) / 255;
            world[to + 1] += ((sheet[from + 1] - world[to + 1]) * a) / 255;
            world[to + 2] += ((sheet[from + 2] - world[to + 2]) * a) / 255;
        }
    }
}

async function keepOnFarLevels(cover, w, h, rect) {
    const x0 = Math.round(rect[0] * apart.width);
    const y0 = Math.round(rect[1] * apart.height);
    const sw = Math.round((rect[2] - rect[0]) * apart.width);
    const sh = Math.round((rect[3] - rect[1]) * apart.height);
    const small = await sharp(cover, { raw: { width: w, height: h, channels: 1 } }).resize(sw, sh, { fit: 'fill' }).toColourspace('b-w').raw().toBuffer();
    for (let y = Math.max(0, -y0); y < sh && y0 + y < apart.height; y++) {
        for (let x = Math.max(0, -x0); x < sw && x0 + x < apart.width; x++) {
            const to = (y0 + y) * apart.width + x0 + x;
            apart.cover[to] = Math.max(apart.cover[to], small[y * sw + x]);
        }
    }
}

function cut(level, x, y, ground) {
    const tile = Buffer.alloc(TILE * TILE * 3);
    for (let p = 0; p < tile.length; p += 3) {
        tile[p] = ground[0];
        tile[p + 1] = ground[1];
        tile[p + 2] = ground[2];
    }
    const x0 = x * TILE;
    const y0 = y * TILE;
    const w = Math.min(TILE, level.width - x0);
    const h = Math.min(TILE, level.height - y0);
    for (let row = 0; row < h; row++) {
        const from = ((y0 + row) * level.width + x0) * 3;
        level.rgb.copy(tile, row * TILE * 3, from, from + w * 3);
    }
    return tile;
}

function isBareSheet(tile, ground) {
    for (let p = 0; p < tile.length; p += 3) {
        if (Math.abs(tile[p] - ground[0]) > FLAT_WITHIN || Math.abs(tile[p + 1] - ground[1]) > FLAT_WITHIN || Math.abs(tile[p + 2] - ground[2]) > FLAT_WITHIN) {
            return false;
        }
    }
    return true;
}

function runs(xs) {
    const list = [];
    for (const x of xs) {
        if (list.length && list[list.length - 2] + list[list.length - 1] === x) {
            list[list.length - 1]++;
        } else {
            list.push(x, 1);
        }
    }
    return list;
}

async function pool(items, limit, work) {
    let next = 0;
    await Promise.all(Array.from({ length: limit }, async () => {
        while (next < items.length) {
            await work(items[next++]);
        }
    }));
}

function folderBytes(dir) {
    let total = 0;
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        total += entry.isDirectory() ? folderBytes(path) : statSync(path).size;
    }
    return total;
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

async function main() {
    const started = Date.now();
    const rects = new Map();
    for (const line of readFileSync(join(here, '..', '..', 'docs', 'map-rects.tsv'), 'utf8').trim().split('\n').slice(1)) {
        const [id, , ...rect] = line.trim().split('\t');
        rects.set(Number(id), rect.map(Number));
    }

    const hash = createHash('sha1').update(JSON.stringify({ RECIPE, WORLD, PX_PER_UNIT, SHORE, LAND, EDGE_FADE, UNDER_ZONES_BLUR, ZONES_AT, APART_PX_PER_UNIT, FLAT_WITHIN, ENCODE }));
    const worldSheet = readFileSync(join(sheetsDir, `${WORLD_MAP}.webp`));
    hash.update(worldSheet);
    const zones = [];
    for (const name of readdirSync(sheetsDir).filter((f) => f.endsWith('.webp')).sort((a, b) => parseInt(a, 10) - parseInt(b, 10))) {
        const id = parseInt(name, 10);
        const rect = rects.get(id);
        const mask = MASKS.map((kind) => ({ ...kind, file: join(addon, 'Media', 'Textures', 'Forever', kind.folder, `${id}.tga`) })).find((kind) => existsSync(kind.file));
        if (!rect || !mask) {
            continue;
        }
        const landFile = mask.file;
        const sheet = readFileSync(join(sheetsDir, name));
        const names = placeNames(id, rect);
        hash.update(`${id}:${rect.join(',')}:`).update(sheet).update(readFileSync(landFile)).update(JSON.stringify(names));
        zones.push({ id, rect, sheet, land: landAlpha(landFile), apart: mask.apart === true, city: mask.city === true, names });
    }
    zones.sort((a, b) => (b.rect[2] - b.rect[0]) * (b.rect[3] - b.rect[1]) - (a.rect[2] - a.rect[0]) * (a.rect[3] - a.rect[1]));
    const version = hash.digest('hex').slice(0, 10);

    const plain = (w, h) => sharp(worldSheet).resize(w, h, { fit: 'fill', kernel: 'cubic' }).removeAlpha().raw().toBuffer();
    const blurred = await sharp(worldSheet).blur(UNDER_ZONES_BLUR).png().toBuffer();
    const world = await sharp(blurred).resize(width, height, { fit: 'fill', kernel: 'cubic' }).removeAlpha().raw().toBuffer();
    const corner = await sharp(worldSheet).extract({ left: 0, top: 0, width: 1, height: 1 }).removeAlpha().raw().toBuffer();
    const ground = [corner[0], corner[1], corner[2]];
    for (const edge of [SHORE, LAND]) {
        for (const zone of zones.filter((z) => edge === LAND || !z.city)) {
            await lay(world, zone, edge);
        }
    }
    console.log(`${zones.length} zone sheets laid on ${width} x ${height} px (${((Date.now() - started) / 1000).toFixed(1)} s)`);

    rmSync(out, { recursive: true, force: true });
    const maxZoom = zoomCount(width, height);
    const levels = [];
    const tiles = {};
    let written = 0;
    for (let z = maxZoom; z >= 0; z--) {
        const scale = 2 ** (maxZoom - z);
        const level = { width: Math.ceil(width / scale), height: Math.ceil(height / scale) };
        const share = ZONES_AT[z] ?? 0;
        level.rgb = z === maxZoom ? world : await sharp(world, { raw: { width, height, channels: 3 } }).resize(level.width, level.height, { fit: 'fill', kernel: 'lanczos3' }).raw().toBuffer();
        if (share < 1) {
            const under = await plain(level.width, level.height);
            const kept = await sharp(apart.cover, { raw: { width: apart.width, height: apart.height, channels: 1 } }).resize(level.width, level.height, { fit: 'fill' }).toColourspace('b-w').raw().toBuffer();
            for (let p = 0; p < kept.length; p++) {
                const zones = Math.max(share, kept[p] / 255);
                for (let c = p * 3; c < p * 3 + 3; c++) {
                    level.rgb[c] = under[c] + (level.rgb[c] - under[c]) * zones;
                }
            }
        }
        const cols = Math.ceil(level.width / TILE);
        const rows = Math.ceil(level.height / TILE);
        const present = [];
        const jobs = [];
        for (let y = 0; y < rows; y++) {
            const xs = [];
            for (let x = 0; x < cols; x++) {
                if (!isBareSheet(cut(level, x, y, ground), ground)) {
                    xs.push(x);
                    jobs.push({ x, y });
                }
            }
            present.push(runs(xs));
        }
        await pool(jobs, 6, async (job) => {
            const dir = join(out, version, LOOK, String(z), String(job.x));
            mkdirSync(dir, { recursive: true });
            await sharp(cut(level, job.x, job.y, ground), { raw: { width: TILE, height: TILE, channels: 3 } }).webp(ENCODE).toFile(join(dir, `${job.y}.webp`));
        });
        written += jobs.length;
        levels.push({ z, width: level.width, height: level.height, cols, rows });
        tiles[z] = present;
        console.log(`z${z}: ${level.width} x ${level.height} px, ${jobs.length} of ${cols * rows} tiles`);
    }

    const manifest = {
        version,
        tile: TILE,
        looks: [LOOK],
        min_zoom: 0,
        max_zoom: maxZoom,
        width,
        height,
        world: { ...WORLD, px_per_unit: PX_PER_UNIT },
        ground: `#${ground.map((c) => c.toString(16).padStart(2, '0')).join('')}`,
        levels: levels.reverse(),
        tiles,
        places: zones.map((zone) => ({ id: zone.id, rect: zone.rect, names: zone.names })).sort((a, b) => a.id - b.id),
    };
    writeFileSync(join(out, 'manifest.json'), `${JSON.stringify(manifest)}\n`);
    console.log(`${written} tiles, ${(folderBytes(join(out, version)) / 1048576).toFixed(1)} MiB, ${zones.reduce((n, zone) => n + zone.names.length, 0)} names, version ${version}, in ${((Date.now() - started) / 1000).toFixed(1)} s`);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
