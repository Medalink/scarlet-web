// The world map viewer: one canvas over the tile pyramid of tools/map-tiles, with no map library.
//
//   import { mountMap } from './map/index.js';
//   const map = mountMap(canvas, { manifestUrl: '/map-tiles/manifest.json', layers, zones });
//
// options
//   manifestUrl   the tile manifest (required); tiles are read from the same folder, under the manifest's version
//   layers        { heat, dots, paths, marks }: heat is { source: { layer, levels, ruleset, creature } } read from
//                 `densityUrl` a level at a time, or { grid: [columns], cells: [i, j, counter, ...] } on a grid of
//                 columns by two thirds as many rows; dots [[u, v], ...]; paths [{ points: [[u, v], ...], mode }];
//                 marks [{ kind: kill|death|quest|level, u, v }]
//   densityUrl    where a heat source is read (default /api/v1/public/map/density)
//   zones         [{ id, name, u0, v0, u1, v1, href? }]: the zone rectangles, for the pointer readout, `fit(id)` and
//                 the zone names drawn on the map; a zone with an href is a link
//   reveal        { cells, marks }: draw only the heat of that many cells and that many marks, from the first, as a replay does; default all
//   look          'survey' or 'game'; default is the last one chosen on this device, else survey
//   fit           a rectangle or zone id to open on; default is the whole world
//   onPointer     ({ u, v, zone } | null) => void, as the pointer moves over the map
//   onZoneClick   (zone) => void, for a click on a zone name; default follows its href
//   onView        ({ u, v, scale, bounds }) => void, after the view moved
//   onLook        (look) => void
//   lookSwitch    an element to fill with the Survey / Game switch
//   chrome        false to leave out the zoom buttons and the credit line (the page then draws its own and uses the
//                 returned controls)
//   label         the canvas's accessible name
// returns { fit, fitWorld, zoomBy, setLayers, setReveal, setLook, look, view, pending, destroy }

import { addLookSwitch, addZoomAndCredit, LOOKS } from './chrome.js';
import { attachInput, ZOOM_STEP } from './input.js';
import { Heat } from './density.js';
import { drawDots, drawHead, drawMarks, drawPaths, drawZoneNames } from './layers.js';
import { loadManifest, TileSource } from './tiles.js';
import { TILE, View } from './view.js';

const STORAGE_KEY = 'scarlet.map.look';
const DEFAULT_LOOK = 'survey';
const BACKGROUND = { survey: '#0e0e10', game: '#2b3a42' };
const LABEL = 'The world map. Drag to move, scroll or press plus and minus to zoom.';
const ANIMATION_MS = 260;

function savedLook() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function saveLook(look) {
    try {
        localStorage.setItem(STORAGE_KEY, look);
    } catch {
        // a private window or blocked storage: the choice lasts for this page only
    }
}

const DENSITY_URL = '/api/v1/public/map/density';

const area = (z) => (z.u1 - z.u0) * (z.v1 - z.v0);

export function mountMap(canvas, options) {
    const ctx = canvas.getContext('2d');
    const view = new View({ width: 1002, height: 668 });
    const zones = (options.zones ?? []).map((z) => ({ ...z }));
    const tileBase = options.tileBase ?? options.manifestUrl.replace(/\/[^/]*$/, '');
    const unbind = [];

    let layers = null;
    let heat = null;
    let reveal = options.reveal ?? null;
    let look = options.look ?? savedLook() ?? DEFAULT_LOOK;
    if (!LOOKS.some((l) => l.look === look)) {
        look = DEFAULT_LOOK;
    }
    let manifest = null;
    let tiles = null;
    let frame = 0;
    let animation = 0;
    let boxes = [];
    let hovered = null;
    let moved = false;
    let initial = true;
    let paintLook = () => {};

    canvas.tabIndex = 0;
    if (!canvas.hasAttribute('role')) {
        canvas.setAttribute('role', 'img');
    }
    canvas.setAttribute('aria-label', options.label ?? canvas.getAttribute('aria-label') ?? LABEL);

    const schedule = () => {
        if (!frame) {
            frame = requestAnimationFrame(draw);
        }
    };

    const setLayers = (next = {}) => {
        heat?.destroy();
        layers = { dots: next.dots ?? [], paths: next.paths ?? [], marks: next.marks ?? [] };
        heat = new Heat(next.heat, options.densityUrl ?? DENSITY_URL, schedule);
        schedule();
    };

    setLayers(options.layers);

    const zoneAt = (u, v) => zones.filter((z) => u >= z.u0 && u <= z.u1 && v >= z.v0 && v <= z.v1).sort((a, b) => area(a) - area(b))[0] ?? null;

    const rectOf = (target) => {
        if (typeof target === 'number' || typeof target === 'string') {
            return zones.find((z) => String(z.id) === String(target)) ?? null;
        }
        if (Array.isArray(target)) {
            return { u0: target[0], v0: target[1], u1: target[2], v1: target[3] };
        }
        return target;
    };

    const stop = () => {
        if (animation) {
            cancelAnimationFrame(animation);
            animation = 0;
        }
    };

    const go = (target, animate = true) => {
        stop();
        if (!animate) {
            Object.assign(view, target);
            view.clamp();
            schedule();
            return;
        }
        const from = { cx: view.cx, cy: view.cy, s: view.s };
        const started = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - started) / ANIMATION_MS);
            const e = 1 - (1 - t) ** 3;
            view.cx = from.cx + (target.cx - from.cx) * e;
            view.cy = from.cy + (target.cy - from.cy) * e;
            view.s = from.s * (target.s / from.s) ** e;
            view.clamp();
            schedule();
            animation = t < 1 ? requestAnimationFrame(step) : 0;
        };
        animation = requestAnimationFrame(step);
    };

    const fit = (target, { animate = true, padding } = {}) => {
        const rect = rectOf(target);
        if (rect) {
            moved = true;
            go(view.framing(rect, padding), animate);
        }
    };

    const fitWorld = (animate = true) => go(view.worldFraming(), animate);

    const zoomBy = (factor) => {
        moved = true;
        go({ cx: view.cx, cy: view.cy, s: Math.min(view.maxScale, Math.max(view.minScale, view.s * factor)) });
    };

    const setLook = (next) => {
        if (!LOOKS.some((l) => l.look === next) || next === look) {
            return;
        }
        look = next;
        saveLook(look);
        paintLook(look);
        options.onLook?.(look);
        schedule();
    };

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = Math.max(1, Math.round(width * dpr));
        canvas.height = Math.max(1, Math.round(height * dpr));
        const first = initial;
        view.resize(width, height, dpr);
        if (first || !moved) {
            initial = false;
            const open = options.fit && rectOf(options.fit);
            Object.assign(view, open ? view.framing(open) : view.worldFraming());
            view.clamp();
        }
        schedule();
    };

    function drawTiles() {
        const { min_zoom: minZoom, max_zoom: maxZoom, world } = manifest;
        const z = view.level(minZoom, maxZoom, world.px_per_unit);
        const perUnit = world.px_per_unit * 2 ** (z - maxZoom);
        const span = TILE / perUnit;
        const level = manifest.levels.find((l) => l.z === z);
        const bounds = view.bounds();
        const x0 = Math.max(0, Math.floor((bounds.u0 * view.W) / span));
        const x1 = Math.min(level.cols - 1, Math.floor((bounds.u1 * view.W) / span));
        const y0 = Math.max(0, Math.floor((bounds.v0 * view.H) / span));
        const y1 = Math.min(level.rows - 1, Math.floor((bounds.v1 * view.H) / span));
        const wanted = [];
        const edge = (unit, centre, extent) => Math.round(((unit - centre) * view.s + extent / 2) * view.dpr);

        for (let y = y0; y <= y1; y++) {
            for (let x = x0; x <= x1; x++) {
                if (!tiles.has(z, x, y)) {
                    continue;
                }
                const left = edge(x * span, view.cx, view.width);
                const top = edge(y * span, view.cy, view.height);
                const width = edge((x + 1) * span, view.cx, view.width) - left;
                const height = edge((y + 1) * span, view.cy, view.height) - top;
                const image = tiles.image(look, z, x, y);
                if (image) {
                    ctx.drawImage(image, left, top, width, height);
                    continue;
                }
                const du = (x + 0.5) * span - view.cx;
                const dv = (y + 0.5) * span - view.cy;
                wanted.push({ z, x, y, d: du * du + dv * dv });
                for (let up = 1; up <= z; up++) {
                    const parent = tiles.image(look, z - up, x >> up, y >> up);
                    if (parent) {
                        const size = TILE / 2 ** up;
                        ctx.drawImage(parent, (x - ((x >> up) << up)) * size, (y - ((y >> up) << up)) * size, size, size, left, top, width, height);
                        break;
                    }
                }
            }
        }

        if (tiles.has(0, 0, 0) && !tiles.image(look, 0, 0, 0)) {
            wanted.push({ z: 0, x: 0, y: 0, d: -1 });
        }
        tiles.want(look, wanted);
    }

    function draw() {
        frame = 0;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.fillStyle = BACKGROUND[look];
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        if (tiles) {
            drawTiles();
        }
        ctx.setTransform(view.dpr, 0, 0, view.dpr, 0, 0);
        heat.draw(ctx, view, reveal?.cells);
        drawDots(ctx, view, layers.dots);
        drawPaths(ctx, view, layers.paths);
        drawMarks(ctx, view, reveal ? layers.marks.slice(0, reveal.marks) : layers.marks);
        if (reveal) {
            drawHead(ctx, view, heat.head(reveal.cells));
        }
        boxes = drawZoneNames(ctx, view, zones);
        options.onView?.({ u: view.cx / view.W, v: view.cy / view.H, scale: view.s, bounds: view.bounds() });
    }

    const boxAt = (x, y) => boxes.find((b) => x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h)?.zone ?? null;

    const hover = (x, y) => {
        if (x === null) {
            hovered = null;
            options.onPointer?.(null);
            return;
        }
        const [u, v] = view.toWorld(x, y);
        const zone = zoneAt(u, v);
        if (u < 0 || v < 0 || u > 1 || v > 1) {
            options.onPointer?.(null);
            return;
        }
        canvas.style.cursor = boxAt(x, y)?.href ? 'pointer' : '';
        if (!hovered || hovered.zone !== zone || Math.abs(hovered.u - u) > 1e-4 || Math.abs(hovered.v - v) > 1e-4) {
            hovered = { u, v, zone };
            options.onPointer?.(hovered);
        }
    };

    unbind.push(
        attachInput(canvas, {
            start: () => {
                stop();
                moved = true;
            },
            drag: (dx, dy) => {
                view.panBy(dx, dy);
                schedule();
            },
            zoomAt: (factor, x, y) => {
                view.zoomAt(factor, x, y);
                schedule();
            },
            zoomCentre: (factor) => zoomBy(factor),
            fitWorld: () => fitWorld(),
            hover,
            tap: (x, y) => {
                const zone = boxAt(x, y);
                if (!zone) {
                    return;
                }
                if (options.onZoneClick) {
                    options.onZoneClick(zone);
                } else if (zone.href) {
                    window.location.assign(zone.href);
                }
            },
        }),
    );

    const observer = new ResizeObserver(resize);
    observer.observe(canvas);
    unbind.push(() => observer.disconnect());

    if (options.chrome !== false && canvas.parentElement) {
        unbind.push(addZoomAndCredit(canvas.parentElement, { zoomIn: () => zoomBy(ZOOM_STEP), zoomOut: () => zoomBy(1 / ZOOM_STEP) }));
    }
    if (options.lookSwitch) {
        paintLook = addLookSwitch(options.lookSwitch, setLook);
        paintLook(look);
    }

    loadManifest(options.manifestUrl)
        .then((loaded) => {
            manifest = loaded;
            view.setWorld(loaded.world);
            if (!moved) {
                const open = options.fit && rectOf(options.fit);
                Object.assign(view, open ? view.framing(open) : view.worldFraming());
            }
            tiles = new TileSource(loaded, { tileBase, onLoad: schedule });
            schedule();
        })
        .catch((error) => {
            console.error(error);
            options.onError?.(error);
        });

    resize();

    return {
        fit,
        fitWorld,
        zoomBy,
        setLayers,
        setReveal: (next) => {
            reveal = next;
            schedule();
        },
        setLook,
        get look() {
            return look;
        },
        view: () => ({ u: view.cx / view.W, v: view.cy / view.H, scale: view.s, bounds: view.bounds() }),
        // how many tiles are still on their way: 0 once the picture is complete
        pending: () => (tiles ? tiles.pending() : 1),
        destroy: () => {
            stop();
            heat.destroy();
            cancelAnimationFrame(frame);
            unbind.forEach((fn) => fn());
            tiles?.destroy();
        },
    };
}
