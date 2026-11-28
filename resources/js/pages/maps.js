// Page glue for the world map viewer: mounts every canvas that carries a `data-world-map` spec, wires the pointer
// readout, the layer buttons (which read /map/density), the zone picker, the list that moves the map to a zone
// (`a[data-map-sheet]`) and, on a personal map, the travel and flight path switches over the trails its spec names.
// The drawing is resources/js/map.
// Text the readout shows comes in the spec already formatted, so nothing is formatted here.

import { mountMap } from '../map/index.js';
import { bindReplay, replayPlan } from '../replay.js';

const MANIFEST = '/map-tiles/manifest.json';
const SHEETS = '/map-sheets/manifest.json';

const maps = new Map();

const CELL = 5;
const TRAVEL = { all: [2, 3, 4], foot: [2], mounted: [3] };
const ROUTE = 5;

function carry(name, value) {
    const set = (address) => {
        const url = new URL(address, window.location.href);
        value === null ? url.searchParams.delete(name) : url.searchParams.set(name, value);
        return url.pathname + url.search + url.hash;
    };
    window.history.replaceState(null, '', set(window.location.href));
    document.querySelectorAll('.world-ctl a[href]').forEach((link) => link.setAttribute('href', set(link.getAttribute('href'))));
    document.querySelectorAll('.world-ctl select[data-nav] option[value]').forEach((option) => (option.value = set(option.value)));
}

function trailChoice() {
    const query = new URL(window.location.href).searchParams;
    const travel = query.get('travel');
    return { travel: Object.hasOwn(TRAVEL, travel ?? '') ? travel : 'all', flights: query.get('flights') !== 'off' };
}

function drawTrails(entry) {
    const { data, choice } = entry.trails;
    document.querySelectorAll(`button[data-map-travel][data-map-for="${entry.canvasId}"]`).forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mapTravel === choice.travel)));
    document.querySelectorAll(`button[data-map-flights][data-map-for="${entry.canvasId}"]`).forEach((button) => button.setAttribute('aria-pressed', String(choice.flights)));
    if (!data) {
        return;
    }
    const keep = TRAVEL[choice.travel];
    const cells = [];
    for (let n = 0; n < data.cells.length; n += CELL) {
        const points = keep.reduce((sum, k) => sum + data.cells[n + k], 0);
        if (points > 0) {
            cells.push(data.cells[n], data.cells[n + 1], points);
        }
    }
    const paths = [];
    for (let n = 0; choice.flights && n < data.flights.length; n += ROUTE) {
        paths.push({ points: [[data.flights[n], data.flights[n + 1]], [data.flights[n + 2], data.flights[n + 3]]], mode: 'flight' });
    }
    entry.map.setLayers({ heat: cells.length > 0 ? { grid: data.grid, cells } : null, paths });
}

function loadTrails(entry) {
    entry.trails = { data: null, choice: trailChoice() };
    drawTrails(entry);
    fetch(entry.config.trails, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`trails ${response.status}`))))
        .then(({ data }) => {
            entry.trails.data = data;
            drawTrails(entry);
        })
        .catch((error) => console.error(error));
}

function chooseTrails(canvasId, change) {
    const entry = ensureMounted(canvasId);
    if (!entry?.trails) {
        return;
    }
    Object.assign(entry.trails.choice, change);
    carry('travel', entry.trails.choice.travel === 'all' ? null : entry.trails.choice.travel);
    carry('flights', entry.trails.choice.flights ? null : 'off');
    drawTrails(entry);
}

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
    const entry = { map, config, canvasId: canvas.id };
    maps.set(canvas.id, entry);
    paint(under, resting);
    if (config.trails) {
        loadTrails(entry);
    }
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
    carry('zone', zone ? String(zone.id) : null);
}

function showLayer(canvasId, layer, button) {
    const entry = ensureMounted(canvasId);
    if (!entry) {
        return;
    }
    entry.map.setLayers({ heat: { source: { layer, levels: entry.config.layerLevels[layer], ruleset: entry.config.ruleset } } });
    button.parentElement.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
    const url = new URL(window.location.href);
    layer === 'paths' ? url.searchParams.delete('layer') : url.searchParams.set('layer', layer);
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
}

const pendingCanvases = new Map();

function ensureMounted(canvasId) {
    const existing = maps.get(canvasId);
    if (existing) {
        return existing;
    }
    const canvas = pendingCanvases.get(canvasId);
    if (!canvas) {
        return null;
    }
    pendingCanvases.delete(canvasId);
    mount(canvas);
    return maps.get(canvasId) ?? null;
}

const lazyObserver = new IntersectionObserver((entries) => {
    for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        lazyObserver.unobserve(entry.target);
        ensureMounted(entry.target.id);
    }
}, { rootMargin: '300px' });

for (const canvas of document.querySelectorAll('canvas[data-world-map]')) {
    const rect = canvas.getBoundingClientRect();
    if (rect.top < window.innerHeight + 300) {
        mount(canvas);
    } else {
        pendingCanvases.set(canvas.id, canvas);
        lazyObserver.observe(canvas);
    }
}

document.querySelectorAll('button[data-map-layer]').forEach((button) => {
    button.addEventListener('click', () => showLayer(button.dataset.mapFor, button.dataset.mapLayer, button));
});

document.querySelectorAll('button[data-map-travel]').forEach((button) => {
    button.addEventListener('click', () => chooseTrails(button.dataset.mapFor, { travel: button.dataset.mapTravel }));
});

document.querySelectorAll('button[data-map-flights]').forEach((button) => {
    button.addEventListener('click', () => chooseTrails(button.dataset.mapFor, { flights: button.getAttribute('aria-pressed') !== 'true' }));
});

document.querySelectorAll('a[data-map-sheet]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const entry = ensureMounted(link.dataset.mapFor);
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
        const entry = ensureMounted(select.dataset.fitMap);
        if (!entry) {
            return;
        }
        select.value === '' ? entry.map.fitWorld() : entry.map.fit(Number(select.value));
    });
});

document.querySelectorAll('select[data-nav]').forEach((select) => {
    select.addEventListener('change', () => window.location.assign(select.value));
});
