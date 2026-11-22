import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, statSync, unlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const WORKERS = 8;
const FETCH_MS = 30000;
const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

/**
 * Reads two columns of a client table exported as CSV: which icon file each id uses. The first row of an id that has an
 * icon wins, and a row whose icon is 0 or whose line holds a quoted cell is left out.
 *
 * @returns {Map<number, number>} id to icon file id
 */
export function readIcons(csvPath, idColumn, iconColumn) {
    const [head, ...lines] = readFileSync(csvPath, 'utf8').split(/\r?\n/).filter(Boolean);
    const columns = head.split(',');
    const idAt = columns.indexOf(idColumn);
    const iconAt = columns.indexOf(iconColumn);
    if (idAt < 0 || iconAt < 0) {
        console.error(`the CSV needs the columns ${idColumn} and ${iconColumn}`);
        process.exit(2);
    }
    const iconOf = new Map();
    for (const line of lines) {
        if (line.includes('"')) continue;
        const cells = line.split(',');
        const id = Number(cells[idAt]);
        const icon = Number(cells[iconAt]);
        if (Number.isInteger(id) && Number.isInteger(icon) && icon > 0 && !iconOf.has(id)) iconOf.set(id, icon);
    }
    return iconOf;
}

/**
 * Writes `icons/<file id>.png` once for every icon file of `iconOf` and `<mapName>` (id to icon file id) into `out`. An
 * icon file already there is not fetched again; one the server cannot give is skipped and counted, and the ids that use it
 * are left out of the map. A run that changes nothing leaves the map alone, so the addresses the site hands out stay the same.
 */
export async function storeIcons(iconOf, out, mapName, noun, server) {
    const folder = join(out, 'icons');
    mkdirSync(folder, { recursive: true });
    for (const name of readdirSync(folder)) {
        if (name.endsWith('.part')) unlinkSync(join(folder, name));
    }
    const files = [...new Set(iconOf.values())];
    const have = new Set();
    let fetched = 0;
    let kept = 0;
    let skipped = 0;
    let bytes = 0;
    const queue = [...files];

    const fetchIcon = async (id) => {
        const r = await fetch(`${server}/casc/blp2png?fileDataID=${id}`, { signal: AbortSignal.timeout(FETCH_MS) });
        if (!r.ok) throw new Error(String(r.status));
        const png = Buffer.from(await r.arrayBuffer());
        if (!png.subarray(0, PNG.length).equals(PNG)) throw new Error('not a PNG');
        return png;
    };

    const work = async () => {
        for (let id = queue.pop(); id !== undefined; id = queue.pop()) {
            const path = join(folder, `${id}.png`);
            if (existsSync(path) && statSync(path).size > 0) {
                have.add(id);
                bytes += statSync(path).size;
                kept++;
                continue;
            }
            try {
                const png = await fetchIcon(id);
                writeFileSync(`${path}.part`, png);
                renameSync(`${path}.part`, path);
                have.add(id);
                bytes += png.length;
                fetched++;
            } catch (e) {
                skipped++;
                console.warn(`icon file ${id}: ${e.message}`);
            }
        }
    };
    await Promise.all(Array.from({ length: WORKERS }, work));

    const map = {};
    for (const id of [...iconOf.keys()].sort((a, b) => a - b)) {
        if (have.has(iconOf.get(id))) map[id] = iconOf.get(id);
    }
    const json = JSON.stringify(map);
    const mapPath = join(out, mapName);
    if (!existsSync(mapPath) || readFileSync(mapPath, 'utf8') !== json) {
        writeFileSync(`${mapPath}.part`, json);
        renameSync(`${mapPath}.part`, mapPath);
    }
    console.log(`${Object.keys(map).length} ${noun}, ${have.size} icon files (${fetched} fetched, ${kept} already there, ${skipped} skipped), ${(bytes / 1048576).toFixed(1)} MiB -> ${out}`);
}
