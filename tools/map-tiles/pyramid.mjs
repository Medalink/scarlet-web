// Stitches OpenMap's baked world tiles into one raster, and halves it level by level.

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { decodeBlp } from './blp.mjs';

export const TILE = 256;

// Straight RGBA of the whole world at the top zoom. Each baked tile is `size` px of which the middle `step`
// are its own (its first and last px repeat its neighbours' edge), so the tile is cropped to those and put at
// (i * step, j * step): the seams then fall between whole pixels and nothing is blended twice.
export function stitchWorld(art, tilesDir, worldUnits) {
    const inset = (art.size - art.step) / 2;
    const width = Math.round(worldUnits.width * art.px);
    const height = Math.round(worldUnits.height * art.px);
    const rgba = Buffer.alloc(width * height * 4);
    const sources = [];

    for (const key of art.tiles) {
        const i = key >> 8;
        const j = key & 255;
        const bytes = readFileSync(join(tilesDir, `${i}_${j}.blp`));
        const tile = decodeBlp(bytes);
        sources.push(bytes);

        if (tile.width !== art.size || tile.height !== art.size) {
            throw new Error(`${i}_${j}.blp is ${tile.width}x${tile.height}, ZoneArt says ${art.size}`);
        }

        const x0 = i * art.step;
        const y0 = j * art.step;
        for (let row = 0; row < art.step; row++) {
            const y = y0 + row;
            if (y >= height) {
                break;
            }
            const copy = Math.min(art.step, width - x0);
            if (copy <= 0) {
                break;
            }
            const from = ((row + inset) * art.size + inset) * 4;
            tile.rgba.copy(rgba, (y * width + x0) * 4, from, from + copy * 4);
        }
    }

    return { width, height, rgba, sources };
}

// One level down: 2x2 pixels become one, averaged by alpha so a transparent pixel adds no colour.
// Pixels past an odd edge count as transparent, so every level is the top one divided by a power of two exactly.
export function halve({ width, height, rgba }) {
    const w = Math.ceil(width / 2);
    const h = Math.ceil(height / 2);
    const out = Buffer.alloc(w * h * 4);

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            let r = 0;
            let g = 0;
            let b = 0;
            let a = 0;
            for (let dy = 0; dy < 2; dy++) {
                const sy = y * 2 + dy;
                if (sy >= height) {
                    continue;
                }
                for (let dx = 0; dx < 2; dx++) {
                    const sx = x * 2 + dx;
                    if (sx >= width) {
                        continue;
                    }
                    const p = (sy * width + sx) * 4;
                    const pa = rgba[p + 3];
                    r += rgba[p] * pa;
                    g += rgba[p + 1] * pa;
                    b += rgba[p + 2] * pa;
                    a += pa;
                }
            }
            const o = (y * w + x) * 4;
            if (a > 0) {
                out[o] = r / a + 0.5;
                out[o + 1] = g / a + 0.5;
                out[o + 2] = b / a + 0.5;
                out[o + 3] = Math.min(255, Math.ceil(a / 4));
            }
        }
    }

    return { width: w, height: h, rgba: out };
}

// The levels, top zoom first: the largest z whose level is the world's own px size, down to one tile.
export function zoomCount(width, height) {
    let z = 0;
    while (Math.ceil(width / 2 ** (z)) > TILE || Math.ceil(height / 2 ** z) > TILE) {
        z++;
    }
    return z;
}
