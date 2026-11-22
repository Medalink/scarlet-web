// Path cells drawn as lines (docs/DESIGN.md, Maps, Lines): the occupied cells of the finest grid are thinned to their
// one-cell-wide middle, each cell left becomes a point at the weighted middle of the counters round it, neighbouring
// points are joined, and the lines are stroked at a width and a colour that follow how much was walked there.

const PAD = 2;
const CHUNK = 32;
const THIN_PASSES = 40;
const SMOOTH_PASSES = 3;
const HOLE = 3;
const NONE = 0xffffffff;

const STYLES = [
    { color: '#6d1a2d', width: 1 },
    { color: '#a21f3e', width: 1.25 },
    { color: '#e2264b', width: 1.6 },
    { color: '#ff4f72', width: 2.1 },
    { color: '#ffa6b8', width: 2.7 },
];
const USUAL = 2;

function zhangSuenThin(mask, width, live) {
    const around = [-width, -width + 1, 1, width + 1, width, width - 1, -1, -width - 1];
    const v = new Uint8Array(8);
    let left = live;
    for (let pass = 0, changed = true; changed && pass < THIN_PASSES; pass++) {
        changed = false;
        for (let step = 0; step < 2; step++) {
            const gone = [];
            for (const p of left) {
                if (!mask[p]) {
                    continue;
                }
                let count = 0;
                let turns = 0;
                for (let k = 0; k < 8; k++) {
                    v[k] = mask[p + around[k]];
                    count += v[k];
                }
                if (count < 2 || count > 6) {
                    continue;
                }
                for (let k = 0; k < 8; k++) {
                    if (!v[k] && v[(k + 1) & 7]) {
                        turns++;
                    }
                }
                const held = step === 0 ? v[0] * v[2] * v[4] || v[2] * v[4] * v[6] : v[0] * v[2] * v[6] || v[0] * v[4] * v[6];
                if (turns === 1 && !held) {
                    gone.push(p);
                }
            }
            for (const p of gone) {
                mask[p] = 0;
            }
            changed ||= gone.length > 0;
        }
        left = left.filter((p) => mask[p]);
    }
}

function closeGapsUpToHole(mask, width, live) {
    const filled = [];
    const gap = [];
    for (const p of live) {
        for (const d of [1, width, -1, -width]) {
            if (mask[p + d]) {
                continue;
            }
            gap.length = 0;
            gap.push(p + d);
            let open = false;
            for (let n = 0; n < gap.length && !open; n++) {
                for (const e of [1, width, -1, -width]) {
                    const q = gap[n] + e;
                    if (!mask[q] && !gap.includes(q)) {
                        gap.push(q);
                    }
                }
                open = gap.length > HOLE;
            }
            if (!open) {
                for (const q of gap) {
                    mask[q] = 1;
                    filled.push(q);
                }
            }
        }
    }
    return filled;
}

/**
 * The lines of a grid of path counters: `cells` is a flat [i, j, counter, ...] list in the order walked, on `columns`
 * across. Positions are kept in cells.
 */
export class Trails {
    constructor(columns, cells) {
        this.columns = columns;
        this.count = 0;
        this.chunks = new Map();
        this.paths = new Map();
        if (cells.length === 0) {
            return;
        }
        let i0 = Infinity;
        let j0 = Infinity;
        let i1 = 0;
        let j1 = 0;
        for (let n = 0; n < cells.length; n += 3) {
            i0 = Math.min(i0, cells[n]);
            i1 = Math.max(i1, cells[n]);
            j0 = Math.min(j0, cells[n + 1]);
            j1 = Math.max(j1, cells[n + 1]);
        }
        const width = i1 - i0 + 1 + 2 * PAD;
        const height = j1 - j0 + 1 + 2 * PAD;
        const field = new Float32Array(width * height);
        const first = new Uint32Array(width * height).fill(NONE);
        const mask = new Uint8Array(width * height);
        const walked = [];
        for (let n = 0, index = 0; n < cells.length; n += 3, index++) {
            const p = (cells[n + 1] - j0 + PAD) * width + cells[n] - i0 + PAD;
            if (!mask[p]) {
                mask[p] = 1;
                walked.push(p);
            }
            field[p] += cells[n + 2];
            first[p] = Math.min(first[p], index);
        }
        zhangSuenThin(mask, width, [...walked, ...closeGapsUpToHole(mask, width, walked)]);

        const ring = [-width - 1, -width, -width + 1, -1, 1, width - 1, width, width + 1];
        for (const p of walked) {
            if (!mask[p] && !ring.some((d) => mask[p + d])) {
                mask[p] = 1;
            }
        }

        const points = [];
        for (let p = 0; p < mask.length; p++) {
            if (mask[p]) {
                points.push(p);
            }
        }
        const at = new Int32Array(width * height).fill(-1);
        points.forEach((p, n) => {
            at[p] = n;
        });
        const total = points.length;
        const x = new Float32Array(total);
        const y = new Float32Array(total);
        const weight = new Float32Array(total);
        const order = new Uint32Array(total);
        const links = new Int32Array(total * 8).fill(-1);
        const degree = new Uint8Array(total);
        points.forEach((p, n) => {
            const px = p % width;
            const py = (p - px) / width;
            let sum = field[p];
            let mx = field[p] * px;
            let my = field[p] * py;
            let earliest = first[p];
            for (const d of ring) {
                const value = field[p + d];
                if (value > 0) {
                    const qx = (p + d) % width;
                    sum += value;
                    mx += value * qx;
                    my += value * ((p + d - qx) / width);
                    earliest = Math.min(earliest, first[p + d]);
                }
            }
            x[n] = mx / sum;
            y[n] = my / sum;
            weight[n] = sum / 3;
            order[n] = earliest;
            for (const [dx, dy] of [[1, 0], [0, 1], [-1, 0], [0, -1]]) {
                const q = at[p + dy * width + dx];
                if (q >= 0) {
                    links[n * 8 + degree[n]++] = q;
                }
            }
            for (const [dx, dy] of [[1, 1], [-1, 1], [-1, -1], [1, -1]]) {
                const q = at[p + dy * width + dx];
                if (q >= 0 && !mask[p + dx] && !mask[p + dy * width]) {
                    links[n * 8 + degree[n]++] = q;
                }
            }
        });

        const relax = (values, pinned) => {
            const next = new Float32Array(total);
            for (let n = 0; n < total; n++) {
                if (degree[n] < pinned) {
                    next[n] = values[n];
                    continue;
                }
                let sum = values[n];
                for (let k = 0; k < degree[n]; k++) {
                    sum += values[links[n * 8 + k]];
                }
                next[n] = sum / (degree[n] + 1);
            }
            values.set(next);
        };
        for (let pass = 0; pass < SMOOTH_PASSES; pass++) {
            relax(x, 2);
            relax(y, 2);
            relax(weight, 1);
        }

        const usual = Float32Array.from(weight).sort()[total >> 1];
        const style = new Uint8Array(total);
        const across = Math.ceil(width / CHUNK);
        for (let n = 0; n < total; n++) {
            style[n] = Math.min(STYLES.length - 1, Math.max(0, Math.round(USUAL + Math.log2(weight[n] / usual))));
            x[n] += i0 - PAD + 0.5;
            y[n] += j0 - PAD + 0.5;
            const p = points[n];
            const key = Math.floor(Math.floor(p / width) / CHUNK) * across + Math.floor((p % width) / CHUNK);
            const list = this.chunks.get(key);
            if (list) {
                list.push(n);
            } else {
                this.chunks.set(key, [n]);
            }
        }
        Object.assign(this, { count: total, x, y, order, links, degree, style, across, left: i0 - PAD, top: j0 - PAD });
    }

    /** Adds the lines that leave a point, each as far as halfway to the neighbour, to the path of the point's style. */
    trace(paths, n) {
        const { x, y, links, degree } = this;
        const path = (paths[this.style[n]] ??= new Path2D());
        const half = (k) => {
            const q = links[n * 8 + k];
            return [(x[n] + x[q]) / 2, (y[n] + y[q]) / 2];
        };
        if (degree[n] === 0) {
            path.moveTo(x[n], y[n]);
            path.lineTo(x[n] + 0.01, y[n]);
            return;
        }
        if (degree[n] === 2) {
            path.moveTo(...half(0));
            path.quadraticCurveTo(x[n], y[n], ...half(1));
            return;
        }
        for (let k = 0; k < degree[n]; k++) {
            path.moveTo(x[n], y[n]);
            path.lineTo(...half(k));
        }
    }

    /** The paths of one chunk by style, or of its points walked before `shown` while a replay reveals them. */
    chunk(key, shown) {
        const list = this.chunks.get(key);
        if (!list) {
            return null;
        }
        if (shown !== null) {
            const partial = [];
            for (const n of list) {
                if (this.order[n] < shown) {
                    this.trace(partial, n);
                }
            }
            return partial;
        }
        let paths = this.paths.get(key);
        if (!paths) {
            paths = [];
            for (const n of list) {
                this.trace(paths, n);
            }
            this.paths.set(key, paths);
        }
        return paths;
    }

    /**
     * Strokes the lines in view. `px` is the CSS px a cell is wide, `alpha` fades the lines in, and `shown` is the
     * number of cells a replay has walked (null for all).
     */
    draw(ctx, view, px, alpha, shown = null) {
        if (this.count === 0 || alpha <= 0) {
            return;
        }
        const [x0, y0] = view.toScreen(0, 0);
        const cx0 = Math.max(0, Math.floor((-x0 / px - this.left - 1) / CHUNK));
        const cy0 = Math.max(0, Math.floor((-y0 / px - this.top - 1) / CHUNK));
        const cx1 = Math.min(this.across - 1, Math.floor(((view.width - x0) / px - this.left + 1) / CHUNK));
        const cy1 = Math.floor(((view.height - y0) / px - this.top + 1) / CHUNK);
        const visible = [];
        for (let cy = cy0; cy <= cy1; cy++) {
            for (let cx = cx0; cx <= cx1; cx++) {
                const paths = this.chunk(cy * this.across + cx, shown);
                if (paths) {
                    visible.push(paths);
                }
            }
        }
        if (visible.length === 0) {
            return;
        }
        const grow = Math.min(1.5, Math.max(1, px / 9));
        ctx.save();
        ctx.translate(x0, y0);
        ctx.scale(px, px);
        ctx.globalAlpha = alpha;
        ctx.lineCap = ctx.lineJoin = 'round';
        STYLES.forEach((style, n) => {
            ctx.strokeStyle = style.color;
            ctx.lineWidth = (style.width * grow) / px;
            for (const paths of visible) {
                if (paths[n]) {
                    ctx.stroke(paths[n]);
                }
            }
        });
        ctx.restore();
    }
}
