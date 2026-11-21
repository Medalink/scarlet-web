// Checks docs/map-rects.tsv against recorded positions.
//
//   node check-rects.mjs <OpenMap folder> <folder of the map tables as CSV> <events.csv>...
//
// 1. Flight points. A recording writes the player's zone map and position on `TN` lines with state 0 (the flight
//    master whose map is open) and on `T` lines (a flight started or ended). OpenMap's FlightData.lua has every flight
//    point in game yards, which reach the world through the world map's own assignment for the continent, while the
//    recorded position reaches it through the zone map's rectangle. Where the two land apart, the rectangle is wrong.
//    The player stands some yards from the flight master, so under one world unit (45 yards) is the player.
// 2. Border crossings. Two path points (`P` lines) written within a few seconds of each other on different maps are
//    the same place seen through two rectangles, so they must land a travelled distance apart, not further. The time
//    between them is printed with the distance, and a pair that implies more than 60 yards a second was a
//    teleport, not a crossing, and is left out.

import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { World } from './rects.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const WORLD = { width: 1002, height: 668 };
const YARDS_PER_UNIT = 44.7;
const FLIGHT_MATCH = 2;
const TELEPORT = 60;

const median = (list) => [...list].sort((a, b) => a - b)[Math.floor(list.length / 2)];

function rects(file) {
    const map = new Map();
    for (const line of readFileSync(file, 'utf8').split('\n').slice(1)) {
        const [id, name, u0, v0, u1, v1] = line.split('\t');
        if (id) {
            map.set(Number(id), { name, u0: +u0, v0: +v0, u1: +u1, v1: +v1 });
        }
    }
    return map;
}

function flightNodes(addon, game) {
    const text = readFileSync(join(addon, 'Data', 'Forever', 'FlightData.lua'), 'utf8');
    const nodes = [];
    for (const m of text.matchAll(/\[(\d+)\] = \{ "([^"]*)", (\d+), (-?[\d.]+), (-?[\d.]+)/g)) {
        const c = game.yards(Number(m[3]));
        if (c) {
            const x = +m[4];
            const y = +m[5];
            nodes.push({ id: Number(m[1]), name: m[2], u: (c.au + c.bu * y) * WORLD.width, v: (c.av + c.bv * x) * WORLD.height });
        }
    }
    return nodes;
}

// one CSV row, quotes honoured
function cells(row) {
    return row.match(/("([^"]|"")*"|[^,]*)(,|$)/g).map((c) => c.replace(/,$/, '').replace(/^"|"$/g, ''));
}

// the recorded `P`, `T` and `TN` positions as { code, t, map, x, y }: a zone map and a position on it, 0 to 1
function positions(files) {
    const out = [];
    for (const file of files) {
        const rows = readFileSync(file, 'utf8').split('\n');
        const head = cells(rows[0]);
        const col = Object.fromEntries(['ms', 'code', 'f1', 'f2', 'f3', 'f4', 'map', 'x', 'y'].map((name) => [name, head.indexOf(name)]));
        for (const row of rows.slice(1)) {
            const c = cells(row);
            const code = c[col.code];
            const t = Number(c[col.ms]);
            if (code === 'P' && c[col.f2]) {
                out.push({ code, t, map: Number(c[col.f1]), x: +c[col.f2] / 1e4, y: +c[col.f3] / 1e4 });
            } else if (code === 'T' && c[col.f4]) {
                out.push({ code, t, map: Number(c[col.f2]), x: +c[col.f3] / 1e4, y: +c[col.f4] / 1e4 });
            } else if (code === 'TN' && c[col.f3] === '0' && c[col.x]) {
                out.push({ code, t, map: Number(c[col.map]), x: +c[col.x] / 1e4, y: +c[col.y] / 1e4 });
            }
        }
    }
    return out.sort((a, b) => a.t - b.t);
}

function main() {
    const [addon, tables, ...files] = process.argv.slice(2);
    if (!addon || !tables || !files.length) {
        console.error('usage: node check-rects.mjs <OpenMap folder> <map tables folder> <events.csv>...');
        process.exit(2);
    }

    const table = rects(resolve(here, '..', '..', 'docs', 'map-rects.tsv'));
    const toWorld = (p) => {
        const r = table.get(p.map);
        return r && [(r.u0 + p.x * (r.u1 - r.u0)) * WORLD.width, (r.v0 + p.y * (r.v1 - r.v0)) * WORLD.height];
    };

    const all = positions(files);
    const nodes = flightNodes(resolve(addon), new World(resolve(tables)));
    const places = new Map();
    for (const p of all.filter((q) => q.code !== 'P')) {
        const at = toWorld(p);
        const near = at && nodes.map((n) => ({ n, d: Math.hypot(n.u - at[0], n.v - at[1]) })).sort((a, b) => a.d - b.d)[0];
        if (near && near.d < FLIGHT_MATCH) {
            const key = `${near.n.id}|${p.map}`;
            places.set(key, { name: near.n.name, zone: table.get(p.map).name, d: [...(places.get(key)?.d ?? []), near.d] });
        }
    }

    console.log('flight point\tzone map\tsamples\tmedian error (units)\t(yards)');
    for (const p of places.values()) {
        console.log([p.name, p.zone, p.d.length, median(p.d).toFixed(2), Math.round(median(p.d) * YARDS_PER_UNIT)].join('\t'));
    }

    const pairs = new Map();
    const steps = [];
    let teleports = 0;
    let last = null;
    for (const p of all.filter((q) => q.code === 'P')) {
        const a = last && toWorld(last);
        const b = toWorld(p);
        const dt = last ? p.t - last.t : Infinity;
        if (a && b && dt > 0 && dt < 6000) {
            const d = Math.hypot(a[0] - b[0], a[1] - b[1]);
            if (last.map === p.map) {
                steps.push(d);
            } else if ((d * YARDS_PER_UNIT) / (dt / 1000) > TELEPORT) {
                teleports++;
            } else {
                const key = `${table.get(last.map).name} > ${table.get(p.map).name}`;
                const entry = pairs.get(key) ?? { d: [], dt: [] };
                entry.d.push(d);
                entry.dt.push(dt / 1000);
                pairs.set(key, entry);
            }
        }
        last = p;
    }

    console.log('\nborder crossing\tcrossings\tmedian distance (units)\tmedian seconds apart\timplied yards a second');
    for (const [key, e] of [...pairs].sort((a, b) => median(a[1].d) - median(b[1].d))) {
        const speed = (median(e.d) * YARDS_PER_UNIT) / median(e.dt);
        console.log([key, e.d.length, median(e.d).toFixed(2), median(e.dt).toFixed(1), speed.toFixed(0)].join('\t'));
    }
    const sorted = [...steps].sort((a, b) => a - b);
    console.log(`\n${teleports} pairs left out as teleports; a step inside one map: median ${median(steps).toFixed(2)} units over ${steps.length} pairs, 95th percentile ${sorted[Math.floor(sorted.length * 0.95)].toFixed(2)}`);
}

main();
