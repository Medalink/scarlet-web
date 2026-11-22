// Page glue for the world map viewer: mounts every canvas that carries a `data-world-map` spec, wires the pointer
// readout, the layer buttons (which read the public API) and the zone picker. The drawing is resources/js/map.
// Text the readout shows comes in the spec already formatted, so nothing is formatted here.

import { mountMap } from '../map/index.js';
import { bindReplay, replayPlan } from '../replay.js';

const MANIFEST = '/map-tiles/manifest.json';

const maps = new Map();

function readout(config, fallback) {
    const out = config.readout ? document.getElementById(config.readout) : null;
    if (!out) {
        return () => {};
    }
    return (zone) => {
        const bold = document.createElement('b');
        bold.textContent = zone ? zone.name : fallback;
        out.replaceChildren(bold, document.createTextNode(zone ? zone.say : ''));
    };
}

function mount(canvas) {
    const config = JSON.parse(canvas.dataset.worldMap);
    const paint = readout(config, 'World');
    const plan = config.replay && !document.hidden && !matchMedia('(prefers-reduced-motion: reduce)').matches ? replayPlan(config) : null;
    const map = mountMap(canvas, {
        reveal: plan?.at(0),
        manifestUrl: MANIFEST,
        pageScrolls: !('fillsWindow' in canvas.dataset),
        zones: config.zones,
        layers: config.layers,
        fit: config.fit ?? undefined,
        look: config.look ?? undefined,
        lookSwitch: config.lookSwitch ? document.getElementById(config.lookSwitch) : undefined,
        onPointer: (pointer) => paint(pointer ? pointer.zone : null),
    });
    maps.set(canvas.id, { map, config });
    paint(null);
    if (plan) {
        bindReplay(map, plan, document.querySelector('[data-count]'), document.querySelector('.ch-replay'));
    }
}

function showLayer(canvasId, layer, button) {
    const entry = maps.get(canvasId);
    if (!entry) {
        return;
    }
    entry.map.setLayers({ heat: { source: { layer, levels: entry.config.layerLevels[layer], ruleset: entry.config.ruleset } } });
    button.parentElement.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
}

document.querySelectorAll('canvas[data-world-map]').forEach(mount);

document.querySelectorAll('button[data-map-layer]').forEach((button) => {
    button.addEventListener('click', () => showLayer(button.dataset.mapFor, button.dataset.mapLayer, button));
});

document.querySelectorAll('select[data-fit-map]').forEach((select) => {
    select.addEventListener('change', () => {
        const entry = maps.get(select.dataset.fitMap);
        if (!entry) {
            return;
        }
        select.value === '' ? entry.map.fitWorld() : entry.map.fit(Number(select.value));
    });
});

document.querySelectorAll('select[data-nav]').forEach((select) => {
    select.addEventListener('change', () => window.location.assign(select.value));
});
