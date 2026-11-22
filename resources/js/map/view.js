// The camera: which part of the world the canvas shows. The world is the stitched Azeroth of docs/DESIGN.md, Maps:
// a point is u, v from 0 to 1 across and down, over `world.width` x `world.height` world units (1002 x 668), and the
// camera is a centre in world units and a scale in CSS px per world unit.

export const TILE = 256;

// The most the art is magnified past its own pixels (16 px per world unit at the top zoom): twice, in device px.
const MAX_DEVICE_PX_PER_UNIT = 32;
const WORLD_LIMIT = { u0: 0, v0: 0, u1: 1, v1: 1 };
const CLOSEST_OVER_FIT = 4;

export class View {
    constructor(world) {
        this.W = world.width;
        this.H = world.height;
        this.width = 1;
        this.height = 1;
        this.dpr = 1;
        this.cx = this.W / 2;
        this.cy = this.H / 2;
        this.s = 1;
        this.limit = WORLD_LIMIT;
        this.deepest = MAX_DEVICE_PX_PER_UNIT;
    }

    /** Keeps the camera on a rectangle of the world (a sheet), with `deepest` device px per world unit as its closest zoom; null for the whole world. */
    setLimit(rect, deepest = MAX_DEVICE_PX_PER_UNIT) {
        this.limit = rect ?? WORLD_LIMIT;
        this.deepest = deepest;
        this.clamp();
    }

    setWorld(world) {
        this.W = world.width;
        this.H = world.height;
    }

    resize(width, height, dpr) {
        this.width = Math.max(1, width);
        this.height = Math.max(1, height);
        this.dpr = dpr;
        this.clamp();
    }

    /** The scale at which the whole limit is in view. */
    get fitScale() {
        return Math.min(this.width / ((this.limit.u1 - this.limit.u0) * this.W), this.height / ((this.limit.v1 - this.limit.v0) * this.H));
    }

    get minScale() {
        return this.fitScale * 0.9;
    }

    get maxScale() {
        return Math.max(this.deepest / this.dpr, this.fitScale * CLOSEST_OVER_FIT);
    }

    clamp() {
        this.s = Math.min(this.maxScale, Math.max(this.minScale, this.s));
        this.cx = Math.min(this.limit.u1 * this.W, Math.max(this.limit.u0 * this.W, this.cx));
        this.cy = Math.min(this.limit.v1 * this.H, Math.max(this.limit.v0 * this.H, this.cy));
    }

    toScreen(u, v) {
        return [(u * this.W - this.cx) * this.s + this.width / 2, (v * this.H - this.cy) * this.s + this.height / 2];
    }

    toWorld(x, y) {
        return [((x - this.width / 2) / this.s + this.cx) / this.W, ((y - this.height / 2) / this.s + this.cy) / this.H];
    }

    // The world rectangle in view, as u0, v0, u1, v1.
    bounds() {
        const [u0, v0] = this.toWorld(0, 0);
        const [u1, v1] = this.toWorld(this.width, this.height);
        return { u0, v0, u1, v1 };
    }

    panBy(dx, dy) {
        this.cx -= dx / this.s;
        this.cy -= dy / this.s;
        this.clamp();
    }

    /** Put the world point (u, v) under screen (x, y), as far as the limit allows. */
    anchor(u, v, x, y) {
        this.cx = u * this.W - (x - this.width / 2) / this.s;
        this.cy = v * this.H - (y - this.height / 2) / this.s;
        this.clamp();
    }

    // Zoom by `factor`, keeping the world point under screen (x, y) where it is.
    zoomAt(factor, x, y) {
        const [u, v] = this.toWorld(x, y);
        this.s *= factor;
        this.s = Math.min(this.maxScale, Math.max(this.minScale, this.s));
        this.anchor(u, v, x, y);
    }

    // The camera that shows a rectangle of the world, with `padding` of its size around it.
    framing({ u0, v0, u1, v1 }, padding = 0.08) {
        const w = (u1 - u0) * this.W * (1 + padding * 2);
        const h = (v1 - v0) * this.H * (1 + padding * 2);
        const s = Math.min(this.maxScale, Math.max(this.minScale, Math.min(this.width / w, this.height / h)));
        return { cx: ((u0 + u1) / 2) * this.W, cy: ((v0 + v1) / 2) * this.H, s };
    }

    worldFraming() {
        return { cx: ((this.limit.u0 + this.limit.u1) / 2) * this.W, cy: ((this.limit.v0 + this.limit.v1) / 2) * this.H, s: this.fitScale };
    }

    // The tile zoom to draw at: the coarsest level whose pixels are not magnified much, within the manifest's range.
    // Level z has 2^(z - 2) px per world unit (16 at z 6).
    level(minZoom, maxZoom, pxPerUnitAtTop) {
        const needed = this.s * this.dpr;
        const z = Math.ceil(Math.log2(needed / pxPerUnitAtTop) + maxZoom - 0.3);
        return Math.min(maxZoom, Math.max(minZoom, z));
    }
}
