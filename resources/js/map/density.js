// The heat layer of the map viewer: counters on the world grid drawn as a density surface (docs/DESIGN.md, Maps). A level
// of cells is blurred, put through a log scale that tops out at the 98th percentile, coloured by the ramp and kept as one
// texture; a frame draws the visible part of it, scaled with bilinear filtering.

const SIGMA = 0.6;
const CHUNK = 64;
const MAX_CHUNKS = 48;
const PERCENTILE = 0.98;
const BINS = 2048;
const FLOOR = 0.02;
const FINER_AT = 3.5;
const COARSER_AT = 2.8;
const BASE_COLUMNS = 192;

const RAMP = [
    [0, 90, 20, 40, 0],
    [0.1, 90, 20, 40, 0.22],
    [0.4, 168, 28, 60, 0.6],
    [0.75, 255, 45, 85, 0.88],
    [1, 255, 143, 166, 0.96],
];

const DETAIL = [
    { from: 6, scale: 3, sigma: 0.4 },
    { from: 10, scale: 5, sigma: 0.35 },
    { from: 18, scale: 7, sigma: 0.3 },
    { from: 30, scale: 9, sigma: 0.25 },
];
const LINE_GAIN = 0.665;

function gaussian(sigma) {
    const radius = Math.ceil(sigma * 2.5);
    const weights = [];
    for (let k = -radius; k <= radius; k++) {
        weights.push(Math.exp(-(k * k) / (2 * sigma * sigma)));
    }
    const total = weights.reduce((a, b) => a + b, 0);
    return Float32Array.from(weights, (w) => w / total);
}

const KERNEL = gaussian(SIGMA);
const RADIUS = (KERNEL.length - 1) / 2;

const LUT = (() => {
    const lut = new Uint8ClampedArray(256 * 4);
    for (let n = 0; n < 256; n++) {
        const t = n / 255;
        let k = 1;
        while (k < RAMP.length - 1 && RAMP[k][0] < t) {
            k++;
        }
        const [t0, r0, g0, b0, a0] = RAMP[k - 1];
        const [t1, r1, g1, b1, a1] = RAMP[k];
        const f = (t - t0) / (t1 - t0);
        const fade = Math.min(1, Math.max(0, (t - 0.03) / 0.17));
        lut.set([r0 + (r1 - r0) * f, g0 + (g1 - g0) * f, b0 + (b1 - b0) * f, (a0 + (a1 - a0) * f) * fade * fade * 255], n * 4);
    }
    return lut;
})();

function blur(field, width, height, kernel = KERNEL) {
    const radius = (kernel.length - 1) / 2;
    const out = new Float32Array(field.length);
    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            let sum = 0;
            for (let k = -radius; k <= radius; k++) {
                const xx = x + k;
                if (xx >= 0 && xx < width) {
                    sum += field[row + xx] * kernel[k + radius];
                }
            }
            out[row + x] = sum;
        }
    }
    const result = new Float32Array(field.length);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            let sum = 0;
            for (let k = -radius; k <= radius; k++) {
                const yy = y + k;
                if (yy >= 0 && yy < height) {
                    sum += out[yy * width + x] * kernel[k + radius];
                }
            }
            result[y * width + x] = sum;
        }
    }
    return result;
}

const tierAt = (px) => DETAIL.findLast((tier) => px >= tier.from) ?? null;

/**
 * The texture of one level: cells is a flat [i, j, counter, ...] list on a grid of `columns` by `rows`. The finest level
 * (`fine`) also redraws the cells it has in view at a higher resolution once a cell is `DETAIL[0].from` px wide.
 */
export class Surface {
    constructor(columns, rows, cells, { ordered = false, fine = false } = {}) {
        this.columns = columns;
        this.rows = rows;
        this.cells = cells;
        this.fine = fine;
        this.buckets = null;
        this.chunks = new Map();
        let i0 = columns;
        let j0 = rows;
        let i1 = 0;
        let j1 = 0;
        for (let n = 0; n < cells.length; n += 3) {
            i0 = Math.min(i0, cells[n]);
            i1 = Math.max(i1, cells[n]);
            j0 = Math.min(j0, cells[n + 1]);
            j1 = Math.max(j1, cells[n + 1]);
        }
        this.empty = cells.length === 0;
        if (this.empty) {
            return;
        }
        this.ox = i0 - RADIUS;
        this.oy = j0 - RADIUS;
        this.width = i1 - i0 + 1 + 2 * RADIUS;
        this.height = j1 - j0 + 1 + 2 * RADIUS;
        const field = new Float32Array(this.width * this.height);
        for (let n = 0; n < cells.length; n += 3) {
            field[(cells[n + 1] - this.oy) * this.width + cells[n] - this.ox] += Math.log1p(cells[n + 2]);
        }
        const soft = blur(field, this.width, this.height);
        let top = 0;
        for (let p = 0; p < soft.length; p++) {
            top = Math.max(top, soft[p]);
        }
        const histogram = new Uint32Array(BINS);
        let seen = 0;
        for (let p = 0; p < soft.length; p++) {
            if (soft[p] > top * FLOOR) {
                histogram[Math.min(BINS - 1, Math.floor((soft[p] / top) * BINS))]++;
                seen++;
            }
        }
        let bin = 0;
        for (let count = 0; bin < BINS && count < seen * PERCENTILE; bin++) {
            count += histogram[bin];
        }
        const reference = Math.max(top * FLOOR, (bin / BINS) * top);
        this.reference = reference;
        this.rgba = new Uint8ClampedArray(soft.length * 4);
        for (let p = 0; p < soft.length; p++) {
            const level = Math.min(255, Math.round((soft[p] / reference) * 255));
            this.rgba.set(LUT.subarray(level * 4, level * 4 + 4), p * 4);
        }
        this.image = new ImageData(new Uint8ClampedArray(this.rgba), this.width, this.height);
        this.canvas = document.createElement('canvas');
        this.canvas.width = this.width;
        this.canvas.height = this.height;
        this.context = this.canvas.getContext('2d');
        this.context.putImageData(this.image, 0, 0);
        this.order = ordered ? this.arrival(cells) : null;
        this.shown = -1;
    }

    /** The cells of each chunk of the grid, as indices into `cells`. */
    index() {
        if (!this.buckets) {
            this.buckets = new Map();
            const across = Math.ceil(this.columns / CHUNK);
            for (let n = 0; n < this.cells.length; n += 3) {
                const key = Math.floor(this.cells[n + 1] / CHUNK) * across + Math.floor(this.cells[n] / CHUNK);
                const list = this.buckets.get(key);
                if (list) {
                    list.push(n);
                } else {
                    this.buckets.set(key, [n]);
                }
            }
        }
        return this.buckets;
    }

    /** One chunk of the grid drawn from its cells at `tier.scale` texels to a cell, with a margin of cells that overlap its neighbours. */
    chunk(cx, cy, tier) {
        const key = `${tier.scale}:${cx}:${cy}`;
        const cached = this.chunks.get(key);
        if (cached) {
            this.chunks.delete(key);
            this.chunks.set(key, cached);
            return cached;
        }
        const buckets = this.index();
        const across = Math.ceil(this.columns / CHUNK);
        const scale = tier.scale;
        const kernel = gaussian(tier.sigma * scale);
        const margin = Math.ceil((kernel.length - 1) / 2 / scale) + 1;
        const span = CHUNK + 2 * margin;
        const size = span * scale;
        const x0 = cx * CHUNK - margin;
        const y0 = cy * CHUNK - margin;
        const field = new Float32Array(size * size);
        let any = false;
        for (let by = cy - 1; by <= cy + 1; by++) {
            for (let bx = cx - 1; bx <= cx + 1; bx++) {
                for (const n of buckets.get(by * across + bx) ?? []) {
                    const i = this.cells[n] - x0;
                    const j = this.cells[n + 1] - y0;
                    if (i >= 0 && j >= 0 && i < span && j < span) {
                        const value = Math.log1p(this.cells[n + 2]);
                        for (let row = j * scale; row < (j + 1) * scale; row++) {
                            for (let column = i * scale; column < (i + 1) * scale; column++) {
                                field[row * size + column] += value;
                            }
                        }
                        any = true;
                    }
                }
            }
        }
        let made = null;
        if (any) {
            const soft = blur(field, size, size, kernel);
            const rgba = new Uint8ClampedArray(soft.length * 4);
            const gain = (LINE_GAIN * 255) / this.reference;
            for (let p = 0; p < soft.length; p++) {
                const level = Math.min(255, Math.round(soft[p] * gain));
                rgba.set(LUT.subarray(level * 4, level * 4 + 4), p * 4);
            }
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            canvas.getContext('2d').putImageData(new ImageData(rgba, size, size), 0, 0);
            made = { canvas, inset: margin * scale, side: CHUNK * scale };
        }
        this.chunks.set(key, made);
        if (this.chunks.size > MAX_CHUNKS) {
            this.chunks.delete(this.chunks.keys().next().value);
        }
        return made;
    }

    /** Draws the chunks in view from the cells; false when the level on screen is too coarse for that or a replay is still revealing. */
    drawDetail(ctx, view, px) {
        const tier = this.fine ? tierAt(px) : null;
        if (!tier || (this.order && this.shown >= 0 && this.shown < this.cells.length / 3)) {
            return false;
        }
        const [x0, y0] = view.toScreen(0, 0);
        const width = CHUNK * px;
        const height = CHUNK * (view.H / this.rows) * view.s;
        const right = Math.min(Math.ceil(this.columns / CHUNK) - 1, Math.floor((view.width - x0) / width));
        const below = Math.min(Math.ceil(this.rows / CHUNK) - 1, Math.floor((view.height - y0) / height));
        for (let cy = Math.max(0, Math.floor(-y0 / height)); cy <= below; cy++) {
            for (let cx = Math.max(0, Math.floor(-x0 / width)); cx <= right; cx++) {
                const chunk = this.chunk(cx, cy, tier);
                if (chunk) {
                    ctx.drawImage(chunk.canvas, chunk.inset, chunk.inset, chunk.side, chunk.side, x0 + cx * width, y0 + cy * height, width, height);
                }
            }
        }
        return true;
    }

    arrival(cells) {
        const order = new Uint32Array(this.width * this.height).fill(0xffffffff);
        for (let n = 0, index = 0; n < cells.length; n += 3, index++) {
            for (let dy = -RADIUS; dy <= RADIUS; dy++) {
                for (let dx = -RADIUS; dx <= RADIUS; dx++) {
                    const p = (cells[n + 1] + dy - this.oy) * this.width + cells[n] + dx - this.ox;
                    if (order[p] > index) {
                        order[p] = index;
                    }
                }
            }
        }
        return order;
    }

    reveal(count) {
        if (!this.order || count === this.shown) {
            return;
        }
        const data = this.image.data;
        for (let p = 0, a = 3; p < this.order.length; p++, a += 4) {
            data[a] = this.order[p] < count ? this.rgba[a] : 0;
        }
        this.context.putImageData(this.image, 0, 0);
        this.shown = count;
    }

    /** Draws the part of the texture in view. */
    draw(ctx, view) {
        if (this.empty) {
            return;
        }
        const cellW = view.W / this.columns;
        const cellH = view.H / this.rows;
        const [x0, y0] = view.toScreen((this.ox / this.columns), (this.oy / this.rows));
        const sx = cellW * view.s;
        const sy = cellH * view.s;
        if (this.drawDetail(ctx, view, sx)) {
            return;
        }
        const from = Math.max(0, Math.floor(-x0 / sx) - 1);
        const to = Math.min(this.width, Math.ceil((view.width - x0) / sx) + 1);
        const top = Math.max(0, Math.floor(-y0 / sy) - 1);
        const bottom = Math.min(this.height, Math.ceil((view.height - y0) / sy) + 1);
        if (to <= from || bottom <= top) {
            return;
        }
        ctx.drawImage(this.canvas, from, top, to - from, bottom - top, x0 + from * sx, y0 + top * sy, (to - from) * sx, (bottom - top) * sy);
    }
}

/** The cells of an answer of `/public/map/density`: flat [i, j, counter, ...]. */
export function decodeCells(columns, pairs) {
    const flat = new Float32Array((pairs.length / 2) * 3);
    let index = 0;
    for (let n = 0, out = 0; n < pairs.length; n += 2, out += 3) {
        index += pairs[n];
        flat[out] = index % columns;
        flat[out + 1] = Math.floor(index / columns);
        flat[out + 2] = pairs[n + 1];
    }
    return flat;
}

const columnsOf = (level) => BASE_COLUMNS * 2 ** level;

/**
 * The heat of a map: cells the page gave (`{ grid: [columns], cells: [i, j, counter, ...] }`, one surface, drawn up to a
 * replay's cell count), the same read after the first paint from a `remote` URL whose answer is `{ data: { grid, cells } }`,
 * or a source (`{ layer, levels, ruleset, creature }`) read from `endpoint` a level at a time, the
 * finest level whose cells are at least `FINER_AT` px wide, keeping the level on screen until the next one is ready.
 */
export class Heat {
    constructor(spec, endpoint, redraw) {
        this.endpoint = endpoint;
        this.redraw = redraw;
        this.level = 0;
        this.surfaces = new Map();
        this.requested = new Set();
        this.alive = true;
        this.source = spec?.source ?? null;
        this.fixed = null;
        this.loading = 0;
        if (spec?.cells) {
            this.fix(spec.grid[0], spec.cells);
        } else if (spec?.remote) {
            this.loading = 1;
            fetch(spec.remote, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`cells ${response.status}`))))
                .then(({ data }) => {
                    if (this.alive) {
                        this.fix(data.grid[0], data.cells);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    this.loading = 0;
                    if (this.alive) {
                        this.redraw();
                    }
                });
        }
    }

    fix(columns, cells) {
        this.fixed = new Surface(columns, Math.round((columns * 2) / 3), Float32Array.from(cells), { ordered: true, fine: true });
    }

    get count() {
        return this.fixed ? this.fixed.cells.length / 3 : 0;
    }

    head(n) {
        if (!this.fixed || n < 1 || n > this.count) {
            return null;
        }
        const cells = this.fixed.cells;
        return [(cells[(n - 1) * 3] + 0.5) / this.fixed.columns, (cells[(n - 1) * 3 + 1] + 0.5) / this.fixed.rows];
    }

    pick(view) {
        const px = (level) => (view.W / columnsOf(level)) * view.s;
        let level = this.level;
        while (level < this.source.levels - 1 && px(level + 1) >= FINER_AT) {
            level++;
        }
        while (level > 0 && px(level) < COARSER_AT) {
            level--;
        }
        this.level = level;
        return level;
    }

    request(level) {
        if (this.requested.has(level)) {
            return;
        }
        this.requested.add(level);
        const query = new URLSearchParams({ layer: this.source.layer, level });
        if (this.source.ruleset) {
            query.set('ruleset', this.source.ruleset);
        }
        if (this.source.creature) {
            query.set('creature', this.source.creature);
        }
        fetch(`${this.endpoint}?${query}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`density ${response.status}`))))
            .then(({ data }) => {
                if (this.alive) {
                    this.surfaces.set(level, new Surface(data.columns, data.rows, decodeCells(data.columns, data.cells), { fine: level === this.source.levels - 1 }));
                    this.redraw();
                }
            })
            .catch(() => this.requested.delete(level));
    }

    surface(view) {
        if (this.fixed) {
            return this.fixed;
        }
        if (!this.source) {
            return null;
        }
        const target = this.pick(view);
        this.request(target);
        for (let distance = 0; distance < this.source.levels; distance++) {
            for (const level of [target - distance, target + distance]) {
                if (this.surfaces.has(level)) {
                    return this.surfaces.get(level);
                }
            }
        }
        this.request(0);
        return null;
    }

    draw(ctx, view, reveal) {
        const surface = this.surface(view);
        if (!surface) {
            return;
        }
        if (this.fixed && reveal !== undefined && reveal !== null) {
            surface.reveal(reveal);
        } else if (this.fixed) {
            surface.reveal(this.count);
        }
        surface.draw(ctx, view);
    }

    destroy() {
        this.alive = false;
    }
}
