// Reads the BLP2 files OpenMap bakes its world tiles into (DXT1, DXT3 or DXT5 with mip maps) and returns
// the first mip as straight RGBA. Written from the BLP2 layout: a 148 byte header, then the mips.

const ENCODING_DXT = 2;

export function decodeBlp(buf) {
    if (buf.toString('latin1', 0, 4) !== 'BLP2') {
        throw new Error('not a BLP2 file');
    }
    const encoding = buf[8];
    const alphaDepth = buf[9];
    const alphaType = buf[10];
    const width = buf.readUInt32LE(12);
    const height = buf.readUInt32LE(16);
    const offset = buf.readUInt32LE(20);
    const size = buf.readUInt32LE(84);

    if (encoding !== ENCODING_DXT) {
        throw new Error(`BLP encoding ${encoding} is not DXT`);
    }

    const dxt = alphaDepth === 0 ? 1 : alphaType === 1 ? 3 : 5;
    const data = buf.subarray(offset, offset + size);
    const out = Buffer.alloc(width * height * 4);
    const blockBytes = dxt === 1 ? 8 : 16;
    const palette = new Float32Array(16);
    const alphas = new Float32Array(8);
    let at = 0;

    for (let by = 0; by < height; by += 4) {
        for (let bx = 0; bx < width; bx += 4) {
            let alphaBits = null;
            let alphaHi = 0;
            let alphaLo = 0;

            if (dxt === 3) {
                alphaBits = data.subarray(at, at + 8);
            }

            if (dxt === 5) {
                const a0 = data[at];
                const a1 = data[at + 1];
                alphas[0] = a0;
                alphas[1] = a1;
                if (a0 > a1) {
                    for (let i = 1; i < 7; i++) {
                        alphas[i + 1] = ((7 - i) * a0 + i * a1 + 3) / 7;
                    }
                } else {
                    for (let i = 1; i < 5; i++) {
                        alphas[i + 1] = ((5 - i) * a0 + i * a1 + 2) / 5;
                    }
                    alphas[6] = 0;
                    alphas[7] = 255;
                }
                alphaLo = data[at + 2] | (data[at + 3] << 8) | (data[at + 4] << 16);
                alphaHi = data[at + 5] | (data[at + 6] << 8) | (data[at + 7] << 16);
            }

            const c = at + (dxt === 1 ? 0 : 8);
            const c0 = data[c] | (data[c + 1] << 8);
            const c1 = data[c + 2] | (data[c + 3] << 8);
            const r0 = ((c0 >> 11) & 31) * 255 / 31;
            const g0 = ((c0 >> 5) & 63) * 255 / 63;
            const b0 = (c0 & 31) * 255 / 31;
            const r1 = ((c1 >> 11) & 31) * 255 / 31;
            const g1 = ((c1 >> 5) & 63) * 255 / 63;
            const b1 = (c1 & 31) * 255 / 31;

            palette.set([r0, g0, b0, 255, r1, g1, b1, 255]);
            if (dxt !== 1 || c0 > c1) {
                palette.set([(2 * r0 + r1) / 3, (2 * g0 + g1) / 3, (2 * b0 + b1) / 3, 255], 8);
                palette.set([(r0 + 2 * r1) / 3, (g0 + 2 * g1) / 3, (b0 + 2 * b1) / 3, 255], 12);
            } else {
                palette.set([(r0 + r1) / 2, (g0 + g1) / 2, (b0 + b1) / 2, 255], 8);
                palette.set([0, 0, 0, 0], 12);
            }

            const bits = data[c + 4] | (data[c + 5] << 8) | (data[c + 6] << 16) | (data[c + 7] << 24);

            for (let py = 0; py < 4; py++) {
                for (let px = 0; px < 4; px++) {
                    const i = py * 4 + px;
                    const p = ((bits >>> (i * 2)) & 3) * 4;
                    const o = ((by + py) * width + bx + px) * 4;
                    out[o] = palette[p] + 0.5;
                    out[o + 1] = palette[p + 1] + 0.5;
                    out[o + 2] = palette[p + 2] + 0.5;

                    if (dxt === 1) {
                        out[o + 3] = palette[p + 3];
                    } else if (dxt === 3) {
                        const nibble = (alphaBits[i >> 1] >> ((i & 1) * 4)) & 15;
                        out[o + 3] = nibble * 17;
                    } else {
                        const idx = i < 8 ? (alphaLo >>> (i * 3)) & 7 : (alphaHi >>> ((i - 8) * 3)) & 7;
                        out[o + 3] = alphas[idx] + 0.5;
                    }
                }
            }

            at += blockBytes;
        }
    }

    return { width, height, rgba: out };
}
