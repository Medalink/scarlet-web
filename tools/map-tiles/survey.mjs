// The survey look: the game's art mapped onto the site's dark sheet (static-site/assets/map.js: land greys from the
// page background to a lighter grey, the sea darkest, a faint contour line every so often, no colour).
// Made once at the top zoom and halved like the game look, so the contours thin out as the map zooms out.

import sharp from 'sharp';

export const SEA = [14, 14, 16];
const FLOOR = [29, 29, 33];
const HIGH = [45, 45, 52];
const LINE = [74, 74, 86];
const BANDS = 6;
const FINE = 0.8;
const COARSE = 14;
const RELIEF = 0.34;

const smooth = (a, b, x) => {
    const t = Math.min(1, Math.max(0, (x - a) / (b - a)));
    return t * t * (3 - 2 * t);
};

// game: { width, height, rgba } straight RGBA. Returns RGBA with alpha 255 everywhere: the sea is painted in.
export async function surveyTop(game) {
    const { width, height, rgba } = game;
    const lum = Buffer.alloc(width * height);

    for (let p = 0, q = 0; q < lum.length; p += 4, q++) {
        lum[q] = 0.2126 * rgba[p] + 0.7152 * rgba[p + 1] + 0.0722 * rgba[p + 2];
    }

    const grey = (sigma) => sharp(lum, { raw: { width, height, channels: 1 } }).blur(sigma).toColourspace('b-w').raw().toBuffer();
    const fine = await grey(FINE);
    const coarse = await grey(COARSE);
    lum.fill(0);

    const out = Buffer.alloc(width * height * 4);
    for (let p = 0, q = 0; q < coarse.length; p += 4, q++) {
        const a = rgba[p + 3] / 255;
        if (a === 0) {
            out[p] = SEA[0];
            out[p + 1] = SEA[1];
            out[p + 2] = SEA[2];
            out[p + 3] = 255;
            continue;
        }

        const r = rgba[p];
        const b = rgba[p + 2];
        const water = smooth(2, 22, b - r);
        const t = Math.min(1, Math.max(0, (coarse[q] / 255 - 0.3) / 0.4));
        const relief = (fine[q] - coarse[q]) * RELIEF;
        const band = t * BANDS;
        const edge = Math.abs(band - Math.round(band));
        const line = (1 - smooth(0.015, 0.06, edge)) * 0.35 * (1 - water);

        for (let c = 0; c < 3; c++) {
            let v = FLOOR[c] + (HIGH[c] - FLOOR[c]) * t + relief;
            v += (LINE[c] - v) * line;
            v += (SEA[c] - v) * water;
            out[p + c] = SEA[c] + (v - SEA[c]) * a + 0.5;
        }
        out[p + 3] = 255;
    }

    return { width, height, rgba: out };
}
