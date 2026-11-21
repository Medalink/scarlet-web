// The tile pyramid tools/map-tiles wrote: the manifest says which tiles exist, and only those are ever requested.

const PARALLEL = 4;
const RETRIES = 6;
const RETRY_MS = 300;
const KEEP = 400;

export async function loadManifest(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`The map manifest answered ${response.status}.`);
    }
    return response.json();
}

export class TileSource {
    constructor(manifest, { tileBase, onLoad }) {
        this.manifest = manifest;
        this.base = `${tileBase}/${manifest.version}`;
        this.onLoad = onLoad;
        this.present = new Map();
        this.images = new Map();
        this.loading = new Map();
        this.queue = [];
        this.failed = new Map();

        for (const level of manifest.levels) {
            const bits = new Uint8Array(level.cols * level.rows);
            manifest.tiles[level.z].forEach((runs, y) => {
                for (let i = 0; i < runs.length; i += 2) {
                    bits.fill(1, y * level.cols + runs[i], y * level.cols + runs[i] + runs[i + 1]);
                }
            });
            this.present.set(level.z, { bits, cols: level.cols, rows: level.rows });
        }
    }

    // Whether the manifest lists the tile. A tile that is not listed does not exist: it is never requested.
    has(z, x, y) {
        const level = this.present.get(z);
        return Boolean(level) && x >= 0 && y >= 0 && x < level.cols && y < level.rows && level.bits[y * level.cols + x] === 1;
    }

    key(look, z, x, y) {
        return `${look}/${z}/${x}/${y}`;
    }

    // The decoded tile, or null until it has arrived.
    image(look, z, x, y) {
        const key = this.key(look, z, x, y);
        const image = this.images.get(key);
        if (!image) {
            return null;
        }
        this.images.delete(key);
        this.images.set(key, image);
        return image;
    }

    // The tiles the current frame needs, nearest the centre first. Anything queued or loading that is not in the list
    // any more is dropped, so a fast drag does not leave a queue of tiles that went by.
    want(look, tiles) {
        const keys = new Set(tiles.map((t) => this.key(look, t.z, t.x, t.y)));
        this.queue = this.queue.filter((t) => keys.has(t.key));
        for (const [key, job] of this.loading) {
            if (!keys.has(key)) {
                job.abort.abort();
                this.loading.delete(key);
            }
        }
        for (const t of tiles) {
            const key = this.key(look, t.z, t.x, t.y);
            if (!this.images.has(key) && !this.loading.has(key) && !this.queue.some((q) => q.key === key) && !this.waiting(key)) {
                this.queue.push({ ...t, look, key });
            }
        }
        this.queue.sort((a, b) => a.d - b.d);
        this.pump();
    }

    // A tile that failed is asked for again a little later, a few times: a server under load drops requests.
    waiting(key) {
        const failure = this.failed.get(key);
        return Boolean(failure) && failure.until > performance.now();
    }

    failure(key, error) {
        if (error?.name === 'AbortError') {
            return;
        }
        const tries = (this.failed.get(key)?.tries ?? 0) + 1;
        const wait = tries > RETRIES ? Infinity : RETRY_MS * tries;
        this.failed.set(key, { tries, until: performance.now() + wait });
        if (wait !== Infinity) {
            setTimeout(this.onLoad, wait + 20);
        }
    }

    pump() {
        while (this.loading.size < PARALLEL && this.queue.length) {
            const job = this.queue.shift();
            const abort = new AbortController();
            this.loading.set(job.key, { abort });
            fetch(`${this.base}/${job.look}/${job.z}/${job.x}/${job.y}.webp`, { signal: abort.signal })
                .then((response) => (response.ok ? response.blob() : Promise.reject(new Error(String(response.status)))))
                .then((blob) => createImageBitmap(blob))
                .then((bitmap) => {
                    this.images.set(job.key, bitmap);
                    this.failed.delete(job.key);
                    this.evict();
                    this.onLoad();
                })
                .catch((error) => this.failure(job.key, error))
                .finally(() => {
                    if (this.loading.get(job.key)?.abort === abort) {
                        this.loading.delete(job.key);
                    }
                    this.pump();
                });
        }
    }

    // Tiles asked for and not here yet: queued, loading or waiting to be asked again.
    pending() {
        return this.queue.length + this.loading.size + [...this.failed.values()].filter((f) => f.until > performance.now() && f.until !== Infinity).length;
    }

    evict() {
        while (this.images.size > KEEP) {
            const [key, bitmap] = this.images.entries().next().value;
            bitmap.close();
            this.images.delete(key);
        }
    }

    destroy() {
        this.queue = [];
        for (const job of this.loading.values()) {
            job.abort.abort();
        }
        this.loading.clear();
        for (const bitmap of this.images.values()) {
            bitmap.close();
        }
        this.images.clear();
    }
}
