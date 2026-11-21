// Builds the web tile pyramid of the world map from an installed copy of the OpenMap addon.
//
//   node build.mjs <OpenMap folder> [--out <folder>] [--force]
//
// Writes <out>/<version>/{game,survey}/<z>/<x>/<y>.webp (256 px tiles), <out>/<version>/manifest.json and
// <out>/manifest.json (the same file: what the site reads to find the current version). <out> defaults to the app's
// `maps` disk root (storage/app/maps). The tiles are never committed.
//
// The art is OpenMap's world tiles (MIT, tools/map-tiles/OPENMAP-LICENSE.txt); the map art itself is Blizzard's.

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { TILE, halve, stitchWorld, zoomCount } from './pyramid.mjs';
import { SEA, surveyTop } from './survey.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const LOOKS = ['game', 'survey'];

// The world map's size in world units: the art of Azeroth's map (uiMap 947) in WoW Forever is 1002 x 668, the size
// OpenMap's Stitch.lua calls W, H. A point of the stitched world is u = x / 1002, v = y / 668.
const WORLD = { width: 1002, height: 668 };

// Raised when the way tiles are made changes, so the content version changes with it.
const RECIPE = 'tiles-1';

// Alpha at or under this counts as empty, in a tile: no file is written, and the viewer never asks for it.
const EMPTY_ALPHA = 2;

const ENCODE = {
    game: { quality: 82, alphaQuality: 90, effort: 5 },
    survey: { quality: 80, effort: 5, smartSubsample: true },
};

function args() {
    const list = process.argv.slice(2);
    const options = { out: resolve(here, '..', '..', 'storage', 'app', 'maps'), force: false, addon: null };
    for (let i = 0; i < list.length; i++) {
        if (list[i] === '--out') {
            options.out = resolve(list[++i]);
        } else if (list[i] === '--force') {
            options.force = true;
        } else if (!options.addon) {
            options.addon = resolve(list[i]);
        }
    }
    if (!options.addon) {
        console.error('usage: node build.mjs <OpenMap folder> [--out <folder>] [--force]');
        process.exit(2);
    }
    return options;
}

function readAddon(addon) {
    const toc = readFileSync(join(addon, 'OpenMap.toc'), 'utf8');
    const version = toc.match(/^## Version:\s*(\S+)/m)?.[1];
    const dataDir = readdirSync(join(addon, 'Data')).find((name) => existsSync(join(addon, 'Data', name, 'ZoneArt.lua')));
    if (!version || !dataDir) {
        throw new Error(`${addon} is not an OpenMap folder`);
    }

    const lua = readFileSync(join(addon, 'Data', dataDir, 'ZoneArt.lua'), 'utf8').replace(/--[^\n]*/g, '');
    const number = (name) => Number(lua.match(new RegExp(`\\b${name}\\s*=\\s*(\\d+)`))[1]);
    const tiles = lua.match(/tiles\s*=\s*\{([^}]*)\}/s)[1].match(/\d+/g).map(Number);
    const art = { px: number('px'), size: number('size'), step: number('step'), tiles };
    const tilesDir = join(addon, 'Media', 'Textures', dataDir, 'MapZones');

    return { version, art, tilesDir };
}

async function pool(items, limit, work) {
    let next = 0;
    const runners = Array.from({ length: limit }, async () => {
        while (next < items.length) {
            await work(items[next++]);
        }
    });
    await Promise.all(runners);
}

function cut(level, x, y, fill) {
    const out = Buffer.alloc(TILE * TILE * 4);
    if (fill) {
        for (let p = 0; p < out.length; p += 4) {
            out[p] = fill[0];
            out[p + 1] = fill[1];
            out[p + 2] = fill[2];
            out[p + 3] = 255;
        }
    }
    const x0 = x * TILE;
    const y0 = y * TILE;
    const w = Math.min(TILE, level.width - x0);
    const h = Math.min(TILE, level.height - y0);
    for (let row = 0; row < h; row++) {
        const from = ((y0 + row) * level.width + x0) * 4;
        level.rgba.copy(out, row * TILE * 4, from, from + w * 4);
    }
    return out;
}

function isEmpty(tile) {
    for (let p = 3; p < tile.length; p += 4) {
        if (tile[p] > EMPTY_ALPHA) {
            return false;
        }
    }
    return true;
}

// [x0, count, x0, count, ...] for the tiles present in one row
function runs(xs) {
    const out = [];
    for (const x of xs) {
        if (out.length && out[out.length - 2] + out[out.length - 1] === x) {
            out[out.length - 1]++;
        } else {
            out.push(x, 1);
        }
    }
    return out;
}

function folderBytes(dir) {
    let total = 0;
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        total += entry.isDirectory() ? folderBytes(path) : statSync(path).size;
    }
    return total;
}

async function main() {
    const started = Date.now();
    const { addon, out, force } = args();
    const { version: openmap, art, tilesDir } = readAddon(addon);

    const hash = createHash('sha1').update(JSON.stringify({ RECIPE, ENCODE, WORLD, EMPTY_ALPHA, px: art.px, size: art.size, step: art.step }));
    for (const key of [...art.tiles].sort((a, b) => a - b)) {
        hash.update(`${key}:`);
        hash.update(readFileSync(join(tilesDir, `${key >> 8}_${key & 255}.blp`)));
    }
    const version = hash.digest('hex').slice(0, 10);

    const final = join(out, version);
    if (existsSync(join(final, 'manifest.json')) && !force) {
        writeFileSync(join(out, 'manifest.json'), readFileSync(join(final, 'manifest.json')));
        console.log(`version ${version} is already built in ${final}; --force builds it again`);
        return;
    }

    const work = join(out, `.building-${version}`);
    rmSync(work, { recursive: true, force: true });
    mkdirSync(work, { recursive: true });

    console.log(`OpenMap ${openmap}: ${art.tiles.length} baked tiles, version ${version}`);
    const world = stitchWorld(art, tilesDir, WORLD);
    const maxZoom = zoomCount(world.width, world.height);
    console.log(`stitched ${world.width} x ${world.height} px, zoom 0 to ${maxZoom}`);

    let game = { width: world.width, height: world.height, rgba: world.rgba };
    let survey = await surveyTop(game);
    console.log(`survey look made (${((Date.now() - started) / 1000).toFixed(1)} s)`);

    const levels = [];
    const tiles = {};

    for (let z = maxZoom; z >= 0; z--) {
        const cols = Math.ceil(game.width / TILE);
        const rows = Math.ceil(game.height / TILE);
        const present = [];
        const jobs = [];

        for (let y = 0; y < rows; y++) {
            const xs = [];
            for (let x = 0; x < cols; x++) {
                const tile = cut(game, x, y, null);
                if (isEmpty(tile)) {
                    continue;
                }
                xs.push(x);
                jobs.push({ x, y, game: tile });
            }
            present.push(runs(xs));
        }

        await pool(jobs, 6, async (job) => {
            const files = {
                game: sharp(job.game, { raw: { width: TILE, height: TILE, channels: 4 } }).webp(ENCODE.game),
                survey: sharp(cut(survey, job.x, job.y, SEA), { raw: { width: TILE, height: TILE, channels: 4 } })
                    .removeAlpha()
                    .webp(ENCODE.survey),
            };
            for (const look of LOOKS) {
                const dir = join(work, look, String(z), String(job.x));
                mkdirSync(dir, { recursive: true });
                await files[look].toFile(join(dir, `${job.y}.webp`));
            }
        });

        levels.push({ z, width: game.width, height: game.height, cols, rows });
        tiles[z] = present;
        console.log(`z${z}: ${game.width} x ${game.height} px, ${jobs.length} tiles per look`);

        if (z > 0) {
            game = halve(game);
            survey = halve(survey);
        }
    }

    const manifest = {
        version,
        built_at: new Date().toISOString(),
        openmap: { version: openmap, license: 'MIT' },
        credit: 'Map art © Blizzard Entertainment. Map by OpenMap.',
        tile: TILE,
        looks: LOOKS,
        min_zoom: 0,
        max_zoom: maxZoom,
        width: world.width,
        height: world.height,
        world: { ...WORLD, px_per_unit: art.px },
        levels: levels.reverse(),
        tiles,
    };
    writeFileSync(join(work, 'manifest.json'), JSON.stringify(manifest));

    rmSync(final, { recursive: true, force: true });
    renameSync(work, final);
    writeFileSync(join(out, 'manifest.json'), JSON.stringify(manifest));

    const seconds = ((Date.now() - started) / 1000).toFixed(1);
    for (const look of LOOKS) {
        console.log(`${look}: ${(folderBytes(join(final, look)) / 1048576).toFixed(1)} MiB`);
    }
    console.log(`built ${final} in ${seconds} s, peak memory ${Math.round(process.resourceUsage().maxRSS / 1024)} MiB`);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
