// Writes docs/map-rects.tsv: where each zone map lies on the stitched world, as the game's
// C_Map.GetMapRectOnMap(uiMapID, 947) gives it, worked out from the game's map tables without the game.
//
//   node rects.mjs <folder of the map tables as CSV: UiMap, UiMapAssignment> [--out <file>]
//
// Columns: ui_map_id, name, u0, v0, u1, v1 (left, top, right, bottom of the map on the world, 0 to 1 across and down).
//
// A map whose continent the world's map does not hold (Zephras Isle, 2521, is game map 2991) has no rectangle in the
// tables. AWAY gives it one: the middle of its map on the world, with its size worked out from its yards at the
// world's own scale. The tables are read with either spelling of an array column, `Region_0` or `Region[0]`.
//
// The rectangle code is a port of World.rect and its helpers in OpenMap's tools/uimap.py, MIT licence,
// Copyright (c) 2026 OpenMap contributors (tools/map-tiles/OPENMAP-LICENSE.txt).

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const WORLD = 947;
const EASTERN_KINGDOMS = 0;

/**
 * Where the middle of a map lies on the world when the tables cannot say: uiMapID => [u, v]. Zephras Isle is put
 * on the island the world's own art paints south of the Maelstrom, island on island.
 */
export const AWAY = new Map([[2521, [0.5311, 0.6835]]]);

function csv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (quoted) {
            if (c === '"' && text[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (c === '"') {
                quoted = false;
            } else {
                cell += c;
            }
        } else if (c === '"') {
            quoted = true;
        } else if (c === ',') {
            row.push(cell);
            cell = '';
        } else if (c === '\n' || c === '\r') {
            if (c === '\r' && text[i + 1] === '\n') {
                i++;
            }
            row.push(cell);
            cell = '';
            if (row.length > 1 || row[0] !== '') {
                rows.push(row);
            }
            row = [];
        } else {
            cell += c;
        }
    }
    if (cell !== '' || row.length) {
        row.push(cell);
        rows.push(row);
    }
    const [head, ...body] = rows;
    const names = head.map((name) => name.replace(/\[(\d+)\]$/, '_$1'));
    return body.map((r) => Object.fromEntries(names.map((name, i) => [name, r[i]])));
}

export class World {
    constructor(dir) {
        const read = (name) => csv(readFileSync(join(dir, `${name}.csv`), 'utf8'));
        this.maps = new Map(read('UiMap').map((r) => [Number(r.ID), r]));
        this.assign = new Map();
        for (const r of read('UiMapAssignment')) {
            const id = Number(r.UiMapID);
            this.assign.set(id, [...(this.assign.get(id) ?? []), r]);
        }
        // the biggest part of each map's art first: the one GetMapRectOnMap goes by
        const area = (r) => (+r.UiMax_0 - +r.UiMin_0) * (+r.UiMax_1 - +r.UiMin_1);
        for (const rows of this.assign.values()) {
            rows.sort((a, b) => Number(a.OrderIndex) - Number(b.OrderIndex) || area(b) - area(a));
        }
        this.cache = new Map();
    }

    parent(id) {
        const r = this.maps.get(id);
        return r ? Number(r.ParentUiMapID) : 0;
    }

    underWorld(id) {
        for (let i = 0; i < 12; i++) {
            if (id === WORLD) {
                return true;
            }
            if (!this.maps.has(id)) {
                return false;
            }
            id = this.parent(id);
        }
        return false;
    }

    // a world point (yards, on r's game map) in r's map, normalized
    static norm(r, x, y) {
        const u = +r.UiMin_0 + ((+r.Region_4 - y) / (+r.Region_4 - +r.Region_1)) * (+r.UiMax_0 - +r.UiMin_0);
        const v = +r.UiMin_1 + ((+r.Region_3 - x) / (+r.Region_3 - +r.Region_0)) * (+r.UiMax_1 - +r.UiMin_1);
        return [u, v];
    }

    static contains(r, x, y) {
        return +r.Region_0 <= x && x <= +r.Region_3 && +r.Region_1 <= y && y <= +r.Region_4;
    }

    // the host's assignment on that game map that holds the point, else the one nearest it
    hostRow(host, gameMap, x, y) {
        const rows = (this.assign.get(host) ?? []).filter((r) => Number(r.MapID) === gameMap);
        if (!rows.length) {
            return null;
        }
        const inside = rows.find((r) => World.contains(r, x, y));
        if (inside) {
            return inside;
        }
        const away = (r) => ((+r.Region_0 + +r.Region_3) / 2 - x) ** 2 + ((+r.Region_1 + +r.Region_4) / 2 - y) ** 2;
        return rows.reduce((best, r) => (away(r) < away(best) ? r : best));
    }

    // [l, r, t, b] of the map on Azeroth's map, normalized, or null when it is not on it
    rect(id) {
        if (id === WORLD) {
            return [0, 1, 0, 1];
        }
        if (this.cache.has(id)) {
            return this.cache.get(id);
        }

        let out = null;
        for (const a of this.assign.get(id) ?? []) {
            const gameMap = Number(a.MapID);
            const cx = (+a.Region_0 + +a.Region_3) / 2;
            const cy = (+a.Region_1 + +a.Region_4) / 2;
            let hostRect = [0, 1, 0, 1];
            let w = this.hostRow(WORLD, gameMap, cx, cy);

            if (!w) {
                // through the nearest map above it that has this game map
                let p = this.parent(id);
                for (let i = 0; i < 10 && p && p !== WORLD; i++) {
                    w = this.hostRow(p, gameMap, cx, cy);
                    if (w) {
                        hostRect = this.rect(p);
                        break;
                    }
                    p = this.parent(p);
                }
                if (!w || !hostRect) {
                    continue;
                }
            }

            // the corners of a's world box on the host, then a's UiMin..UiMax stretched to its whole art
            const [u0, v0] = World.norm(w, +a.Region_3, +a.Region_4);
            const [u1, v1] = World.norm(w, +a.Region_0, +a.Region_1);
            let l = Math.min(u0, u1);
            const r = Math.max(u0, u1);
            let t = Math.min(v0, v1);
            const b = Math.max(v0, v1);
            const um0 = +a.UiMin_0;
            const um1 = +a.UiMin_1;
            const uM0 = +a.UiMax_0;
            const uM1 = +a.UiMax_1;
            if (uM0 <= um0 || uM1 <= um1) {
                continue;
            }
            const w2 = (r - l) / (uM0 - um0);
            const h2 = (b - t) / (uM1 - um1);
            l -= um0 * w2;
            t -= um1 * h2;
            const [hl, hr, ht, hb] = hostRect;
            out = [hl + l * (hr - hl), hl + (l + w2) * (hr - hl), ht + t * (hb - ht), ht + (t + h2) * (hb - ht)];
            break;
        }

        out ??= this.away(id);
        this.cache.set(id, out);
        return out;
    }

    /** [l, r, t, b] of a map in AWAY: its own yards at the world's scale, about its middle; null for any other map. */
    away(id) {
        const middle = AWAY.get(id);
        const a = this.assign.get(id)?.[0];
        const scale = this.yards(EASTERN_KINGDOMS);
        if (!middle || !a || !scale) {
            return null;
        }
        const w = Math.abs(scale.bu) * (+a.Region_4 - +a.Region_1);
        const h = Math.abs(scale.bv) * (+a.Region_3 - +a.Region_0);
        return [middle[0] - w / 2, middle[0] + w / 2, middle[1] - h / 2, middle[1] + h / 2];
    }

    // how a continent's yards land on the world: a point (x, y) of game map `gameMap` is
    // u = au + bu * y, v = av + bv * x, from the world map's own assignment for it
    yards(gameMap) {
        const r = (this.assign.get(WORLD) ?? []).find((row) => Number(row.MapID) === gameMap);
        if (!r) {
            return null;
        }
        const [ua, va] = World.norm(r, 0, 0);
        const [ub, vb] = World.norm(r, 1000, 1000);
        return { au: ua, bu: (ub - ua) / 1000, av: va, bv: (vb - va) / 1000 };
    }
}

function main() {
    const list = process.argv.slice(2);
    let out = resolve(here, '..', '..', 'docs', 'map-rects.tsv');
    let dir = null;
    for (let i = 0; i < list.length; i++) {
        if (list[i] === '--out') {
            out = resolve(list[++i]);
        } else {
            dir = resolve(list[i]);
        }
    }
    if (!dir) {
        console.error('usage: node rects.mjs <folder of the map tables as CSV> [--out <file>]');
        process.exit(2);
    }

    const world = new World(dir);
    const lines = ['ui_map_id\tname\tu0\tv0\tu1\tv1'];
    const ids = [...world.maps.keys()].filter((id) => id !== WORLD && world.underWorld(id)).sort((a, b) => a - b);
    for (const id of ids) {
        const rect = world.rect(id);
        if (!rect) {
            continue;
        }
        const [l, r, t, b] = rect;
        const name = world.maps.get(id).Name_lang.replace(/\s+/g, ' ');
        lines.push([id, name, l, t, r, b].map((v, i) => (i < 2 ? v : v.toFixed(6))).join('\t'));
    }
    writeFileSync(out, `${lines.join('\n')}\n`);
    console.log(`${lines.length - 1} maps written to ${out}`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    main();
}
