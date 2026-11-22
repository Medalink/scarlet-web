// Page glue for the world map viewer: mounts every canvas that carries a `data-world-map` spec, wires the pointer
// readout, the layer buttons (which read the public API), the zone picker and the list that moves the map to a zone
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
        onZone: (zone) => {
            resting = zone?.name ?? 'World';
            paint(under, resting);
            showZone(canvas.id, config, zone);
        },
        onZoneClick: config.sheetList ? (zone) => map.fit(zone.id) : undefined,
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

function showZone(canvasId, config, zone) {
    const links = [...document.querySelectorAll(`a[data-map-sheet][data-map-for="${canvasId}"]`)];
    if (links.length === 0) {
        return;
    }
    const on = (link) => (zone ? Number(link.dataset.mapSheet) === zone.id : link.dataset.mapWorld !== undefined);
    links.forEach((link) => (on(link) ? link.setAttribute('aria-current', 'true') : link.removeAttribute('aria-current')));
    const page = document.getElementById(config.zonePage);
    if (page) {
        page.hidden = !zone?.href;
        page.href = zone?.href ?? '';
        page.textContent = zone?.href ? `${zone.name} zone page` : '';
    }
    const withZone = (address) => {
        const url = new URL(address, window.location.href);
        zone ? url.searchParams.set('zone', zone.id) : url.searchParams.delete('zone');
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
        const entry = maps.get(link.dataset.mapFor);
        const id = Number(link.dataset.mapSheet);
        if (entry && link.dataset.mapWorld !== undefined) {
            event.preventDefault();
            entry.map.fitWorld();
        } else if (entry?.config.zones.some((z) => z.id === id)) {
            event.preventDefault();
            entry.map.fit(id);
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
