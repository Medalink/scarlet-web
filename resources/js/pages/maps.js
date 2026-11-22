// Page glue for the world map viewer: mounts every canvas that carries a `data-world-map` spec, wires the pointer
// readout, the layer buttons (which read the public API), the zone picker and the list that opens a zone's sheet
// (`a[data-map-sheet]`). The drawing is resources/js/map.
// Text the readout shows comes in the spec already formatted, so nothing is formatted here.

import { mountMap } from '../map/index.js';
import { bindReplay, replayPlan } from '../replay.js';

const MANIFEST = '/map-tiles/manifest.json';
const SHEETS = '/map-sheets/manifest.json';

const maps = new Map();

function readout(config) {
    const out = config.readout ? document.getElementById(config.readout) : null;
    if (!out) {
        return () => {};
    }
    return (zone, resting) => {
        const bold = document.createElement('b');
        bold.textContent = zone ? zone.name : resting;
        out.replaceChildren(bold, document.createTextNode(zone ? zone.say : ''));
    };
}

function mount(canvas) {
    const config = JSON.parse(canvas.dataset.worldMap);
    const paint = readout(config);
    let under = null;
    let resting = 'World';
    const plan = config.replay && !document.hidden && !matchMedia('(prefers-reduced-motion: reduce)').matches ? replayPlan(config) : null;
    const map = mountMap(canvas, {
        reveal: plan?.at(0),
        manifestUrl: MANIFEST,
        sheets: SHEETS,
        onSheet: (id) => {
            resting = config.zones.find((z) => z.id === id)?.name ?? 'World';
            paint(under, resting);
            showSheet(canvas.id, config, id);
        },
        onZoneClick: config.sheetList ? (zone) => map.openSheet(zone.id) || (zone.href && window.location.assign(zone.href)) : undefined,
        pageScrolls: 'behindHead' in canvas.dataset,
        zones: config.zones,
        layers: config.layers,
        fit: config.fit ?? undefined,
        look: config.look ?? undefined,
        lookSwitch: config.lookSwitch ? document.getElementById(config.lookSwitch) : undefined,
        onPointer: (pointer) => {
            under = pointer ? pointer.zone : null;
            paint(under, resting);
        },
    });
    maps.set(canvas.id, { map, config });
    paint(under, resting);
    if (plan) {
        bindReplay(map, plan, document.querySelector('[data-count]'), document.querySelector('.ch-replay'));
    }
}

function showSheet(canvasId, config, id) {
    const links = [...document.querySelectorAll(`a[data-map-sheet][data-map-for="${canvasId}"]`)];
    if (links.length === 0) {
        return;
    }
    links.forEach((link) => (Number(link.dataset.mapSheet) === id ? link.setAttribute('aria-current', 'true') : link.removeAttribute('aria-current')));
    const zone = config.zones.find((z) => z.id === id && z.href);
    const page = document.getElementById(config.zonePage);
    if (page) {
        page.hidden = !zone;
        page.href = zone?.href ?? '';
        page.textContent = zone ? `${zone.name} zone page` : '';
    }
    const world = links.find((link) => link.dataset.mapWorld !== undefined);
    const withZone = (address) => {
        const url = new URL(address, window.location.href);
        Number(world?.dataset.mapSheet) === id ? url.searchParams.delete('zone') : url.searchParams.set('zone', id);
        return url.pathname + url.search + url.hash;
    };
    window.history.replaceState(null, '', withZone(window.location.href));
    document.querySelectorAll('.world-ctl a[href]').forEach((link) => link.setAttribute('href', withZone(link.getAttribute('href'))));
    document.querySelectorAll('.world-ctl select[data-nav] option[value]').forEach((option) => (option.value = withZone(option.value)));
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

document.querySelectorAll('a[data-map-sheet]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (maps.get(link.dataset.mapFor)?.map.openSheet(Number(link.dataset.mapSheet))) {
            event.preventDefault();
        }
    });
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
