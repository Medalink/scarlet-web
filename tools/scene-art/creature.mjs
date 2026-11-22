import { readModel } from './m2.mjs';

const BUILD = '1.60.1.71037';
const SKIN_TYPES = [11, 12, 13];

/**
 * A creature's model with the skin of one creature display. The model file leaves its skin textures (texture types 11 to
 * 13) to the display row, whose `TextureVariationFileDataID[0..2]` fill them in that order; each is written into the
 * model's texture ids before the model is read.
 *
 * @param {number} fdid the model's file id
 * @param {number} display the `CreatureDisplayInfo` row whose skin to use
 * @param {(id: number) => Promise<Buffer>} get reads a client file by id
 * @param {(table: string, column: string, value: number) => Promise<Record<string, string>>} peek reads one row of a client table
 */
export async function readCreature(fdid, display, get, peek) {
    const row = await peek('CreatureDisplayInfo', 'ID', display);
    const data = await peek('CreatureModelData', 'ID', row.ModelID);
    if (Number(data.FileDataID) !== fdid) throw new Error(`display ${display} is model file ${data.FileDataID}, not ${fdid}`);
    const skins = SKIN_TYPES.map((_, i) => Number(row[`TextureVariationFileDataID[${i}]`]));
    const bare = await readModel(fdid, get);
    const skinned = async (id) => {
        const file = Buffer.from(await get(id));
        if (id !== fdid) return file;
        for (let o = 0; o + 8 <= file.length;) {
            const size = file.readUInt32LE(o + 4);
            if (file.toString('latin1', o, o + 4) === 'TXID') {
                bare.textures.forEach((t, i) => {
                    const skin = skins[SKIN_TYPES.indexOf(t.type)];
                    if (skin) file.writeUInt32LE(skin, o + 8 + i * 4);
                });
            }
            o += 8 + size;
        }
        return file;
    };
    return { ...(await readModel(fdid, skinned)), display };
}

/** Reads one row of a client table through the local file server: the row of `table` whose `column` is `value`. */
export function rowReader(server) {
    return async (table, column, value) => {
        const r = await fetch(`${server}/dbc/peek/${table}?build=${BUILD}&col=${column}&val=${value}`);
        if (!r.ok) throw new Error(`${table} ${column}=${value}: ${r.status}`);
        const { values } = await r.json();
        if (!values) throw new Error(`${table} has no row with ${column}=${value}`);
        return values;
    };
}
