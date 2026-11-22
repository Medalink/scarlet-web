// The dark survey sheets of public/map-sheets (tools/map-tiles/sheets.mjs): one drawn sheet per zone map, each with its
// rectangle on the world, the mask of its own land and its place names. The viewer shows one sheet at a time, as the
// Scarlet app does, and moves between the world, a zone and a city as the zoom passes a sheet's limits.

const CONTINENT_AREA = 0.25;
const NESTED_RATIO = 3;
const SLACK = 0.004;

const area = (s) => (s.u1 - s.u0) * (s.v1 - s.v0);
const holds = (s, u, v) => u >= s.u0 && u <= s.u1 && v >= s.v0 && v <= s.v1;
const within = (outer, inner) => inner.u0 >= outer.u0 - SLACK && inner.v0 >= outer.v0 - SLACK && inner.u1 <= outer.u1 + SLACK && inner.v1 <= outer.v1 + SLACK;

export class Sheets {
    constructor(manifest, base, onLoad) {
        this.base = base;
        this.version = manifest.version;
        this.onLoad = onLoad;
        this.images = new Map();
        this.list = Object.entries(manifest.sheets).map(([id, sheet]) => {
            const [u0, v0, u1, v1] = sheet.rect;
            return { id: Number(id), u0, v0, u1, v1, width: sheet.width, hasLand: sheet.mask, names: sheet.names };
        });
        this.world = this.get(manifest.world);
        const wide = (s) => s === this.world || area(s) > CONTINENT_AREA;
        const zones = this.list.filter((s) => !wide(s));
        for (const sheet of this.list) {
            sheet.zone = wide(sheet) ? null : zones.filter((z) => z !== sheet && within(z, sheet) && area(z) >= area(sheet) * NESTED_RATIO).sort((x, y) => area(x) - area(y))[0] ?? null;
        }
        for (const sheet of this.list) {
            sheet.steps = zones.filter((z) => z.zone === (wide(sheet) ? null : sheet));
        }
    }

    get(id) {
        return this.list.find((s) => s.id === Number(id)) ?? null;
    }

    /** The smallest sheet that shows the whole rectangle; the world when none does. */
    containing(rect) {
        return this.list.filter((s) => within(s, rect)).sort((a, b) => area(a) - area(b))[0] ?? this.world;
    }

    /** The sheet one step in from `sheet` at a world point: a zone from the world or a continent, a city from its zone. */
    inner(sheet, u, v) {
        const central = (s) => Math.min(u - s.u0, s.u1 - u, v - s.v0, s.v1 - v) / Math.sqrt(area(s));
        return sheet.steps.filter((s) => holds(s, u, v)).sort((a, b) => central(b) - central(a))[0] ?? null;
    }

    /** The sheet one step out: a city's zone, else the world. */
    outer(sheet) {
        return sheet === this.world ? null : sheet.zone ?? this.world;
    }

    load(key, file) {
        if (!this.images.has(key)) {
            const image = new Image();
            this.images.set(key, null);
            image.src = `${this.base}/${file}?v=${this.version}`;
            image.decode().then(() => this.images.set(key, image), () => this.images.set(key, false)).then(this.onLoad);
        }
        return this.images.get(key);
    }

    /** The sheet's picture: null until it has arrived, false when it cannot be read. */
    image(sheet) {
        return this.load(`sheet:${sheet.id}`, `${sheet.id}.webp`);
    }

    /** The mask of the sheet's own land: null until it has arrived, false when the sheet has none or it cannot be read. */
    land(sheet) {
        return sheet.hasLand && this.load(`land:${sheet.id}`, `${sheet.id}.mask.png`);
    }

    /** Whether something the current sheet needs is still loading. */
    pending(sheet) {
        return this.images.get(`sheet:${sheet.id}`) === null || (sheet.hasLand && this.images.get(`land:${sheet.id}`) === null) ? 1 : 0;
    }
}

export async function loadSheets(url, onLoad) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`map sheets ${response.status}`);
    }
    return new Sheets(await response.json(), url.replace(/\/[^/]*$/, ''), onLoad);
}
