// The map viewer on its own, for /design/map-viewer (local environment only), with invented layer data.
// Nothing here is recorded data: the heat, the marks and the path are made up to check how the layers read.

import { GRID } from './layers.js';
import { mountMap } from './index.js';

const page = document.getElementById('map-viewer');
const zones = JSON.parse(page.dataset.zones);

// A blob of heat cells on the world grid around (u, v): level 4 in the middle, 1 at the edge.
function blob(u, v, radius, seed) {
    const cells = [];
    const ci = Math.round(u * GRID);
    const cj = Math.round(v * GRID);
    let state = seed;
    const random = () => {
        state = (state * 1664525 + 1013904223) >>> 0;
        return state / 4294967296;
    };
    for (let di = -radius; di <= radius; di++) {
        for (let dj = -radius; dj <= radius; dj++) {
            const d = Math.hypot(di * 1.5, dj) / radius;
            if (d < 1 && random() < 1 - d * 0.6) {
                cells.push([ci + di, cj + dj, Math.max(1, 4 - Math.floor(d * 4 + random() * 0.8))]);
            }
        }
    }
    return cells;
}

const layers = {
    heat: { cells: [...blob(0.3, 0.49, 70, 1), ...blob(0.285, 0.435, 38, 2), ...blob(0.73, 0.62, 55, 3), ...blob(0.75, 0.52, 40, 4)] },
    paths: [
        {
            points: [[0.292, 0.436], [0.298, 0.452], [0.305, 0.47], [0.29, 0.5], [0.27, 0.525], [0.255, 0.56], [0.25, 0.6]],
            mode: 'run',
        },
        { points: [[0.292, 0.436], [0.24, 0.5], [0.2, 0.58], [0.2, 0.75]], mode: 'taxi' },
    ],
    marks: [
        { kind: 'death', u: 0.298, v: 0.452 },
        { kind: 'death', u: 0.262, v: 0.54 },
        { kind: 'kill', u: 0.31, v: 0.49 },
        { kind: 'kill', u: 0.315, v: 0.5 },
        { kind: 'quest', u: 0.282, v: 0.47 },
        { kind: 'level', u: 0.292, v: 0.436 },
        { kind: 'death', u: 0.74, v: 0.64 },
        { kind: 'kill', u: 0.72, v: 0.61 },
    ],
};

const readout = document.getElementById('readout');
const map = mountMap(document.getElementById('map'), {
    manifestUrl: '/map-tiles/manifest.json',
    layers,
    zones,
    fit: Number(new URLSearchParams(window.location.search).get('fit')) || undefined,
    lookSwitch: document.getElementById('looks'),
    onPointer: (at) => {
        readout.textContent = at ? `u ${at.u.toFixed(4)}  v ${at.v.toFixed(4)}  ${at.zone?.name ?? ''}` : '';
    },
});

document.querySelectorAll('[data-fit]').forEach((button) => {
    button.addEventListener('click', () => (button.dataset.fit === 'world' ? map.fitWorld() : map.fit(Number(button.dataset.fit))));
});

window.scarletMap = map;
