// Reads a WoW M2 model and its first skin into plain data three.js can draw: batches of triangles with their
// material, the bones they hang on, the lights and the particle emitters with their tracks.
// Layouts follow the M2 format of client version 272 (MD21 chunks, 48-byte vertices, 492-byte emitters).

/**
 * @param {number} fdid the model's file id
 * @param {(id: number) => Promise<Buffer>} get reads a client file by id
 */
export async function readModel(fdid, get) {
    const file = await get(fdid);
    const chunks = {};
    for (let o = 0; o + 8 <= file.length;) {
        const id = file.toString('latin1', o, o + 4);
        const size = file.readUInt32LE(o + 4);
        chunks[id] = file.subarray(o + 8, o + 8 + size);
        o += 8 + size;
    }
    const md = chunks.MD21;
    if (!md) throw new Error('no MD21 chunk');
    const u32s = (b) => (b ? Array.from({ length: b.length / 4 }, (_, i) => b.readUInt32LE(i * 4)) : []);
    const txid = u32s(chunks.TXID);
    const sfid = u32s(chunks.SFID);

    const u8 = (o) => md.readUInt8(o), u16 = (o) => md.readUInt16LE(o), i16 = (o) => md.readInt16LE(o);
    const u32 = (o) => md.readUInt32LE(o), f32 = (o) => md.readFloatLE(o);
    const r4 = (v) => Math.round(v * 1e4) / 1e4;
    const vec = (o, n) => Array.from({ length: n }, (_, i) => r4(f32(o + i * 4)));
    const arr = (o) => ({ n: u32(o), o: u32(o + 4) });
    const READ = {
        f: [4, (o) => r4(f32(o))], v2: [8, (o) => vec(o, 2)], v3: [12, (o) => vec(o, 3)], q: [8, (o) => [0, 1, 2, 3].map((i) => i16(o + i * 2))],
        u8: [1, u8], u16: [2, u16], fx: [2, (o) => r4(i16(o) / 32767)],
    };
    const list = (a, kind) => Array.from({ length: a.n }, (_, i) => READ[kind][1](a.o + i * READ[kind][0]));
    const track = (o, kind) => {
        const times = arr(o + 4), values = arr(o + 12);
        const t = times.n ? list(arr(times.o), 'u32') : [];
        const v = values.n ? list(arr(values.o), kind) : [];
        return { interp: u16(o), seq: i16(o + 2), t, v };
    };
    READ.u32 = [4, u32];
    const fblock = (o, kind) => ({ t: list(arr(o), 'fx'), v: list(arr(o + 8), kind) });

    const H = {
        globalLoops: arr(0x14), bones: arr(0x2c), vertices: arr(0x3c), textures: arr(0x50), materials: arr(0x70),
        boneCombos: arr(0x78), textureCombos: arr(0x80), lights: arr(0x108), particles: arr(0x128),
    };

    const vertices = Array.from({ length: H.vertices.n }, (_, i) => {
        const o = H.vertices.o + i * 48;
        return { p: vec(o, 3), w: [0, 1, 2, 3].map((k) => u8(o + 12 + k)), b: [0, 1, 2, 3].map((k) => u8(o + 16 + k)), n: vec(o + 20, 3), uv: vec(o + 32, 2) };
    });
    const bones = Array.from({ length: H.bones.n }, (_, i) => {
        const o = H.bones.o + i * 88;
        return { flags: u32(o + 4), parent: i16(o + 8), pivot: vec(o + 76, 3), translation: track(o + 16, 'v3'), scale: track(o + 56, 'v3') };
    });
    const materials = Array.from({ length: H.materials.n }, (_, i) => ({ flags: u16(H.materials.o + i * 4), blend: u16(H.materials.o + i * 4 + 2) }));
    const textures = Array.from({ length: H.textures.n }, (_, i) => ({ type: u32(H.textures.o + i * 16), flags: u32(H.textures.o + i * 16 + 4), fdid: txid[i] ?? 0 }));
    const textureCombos = list(H.textureCombos, 'u16');
    const boneCombos = list(H.boneCombos, 'u16');

    const lights = Array.from({ length: H.lights.n }, (_, i) => {
        const o = H.lights.o + i * 156;
        return {
            type: u16(o), bone: i16(o + 2), position: vec(o + 4, 3),
            ambientColor: track(o + 16, 'v3'), ambientIntensity: track(o + 36, 'f'), diffuseColor: track(o + 56, 'v3'),
            diffuseIntensity: track(o + 76, 'f'), attenuationStart: track(o + 96, 'f'), attenuationEnd: track(o + 116, 'f'),
        };
    });

    const PSIZE = 492;
    const particles = Array.from({ length: H.particles.n }, (_, i) => {
        const o = H.particles.o + i * PSIZE;
        const flags = u32(o + 4), packed = u16(o + 0x16);
        const multi = (flags & 0x10000000) !== 0;
        return {
            flags, position: vec(o + 8, 3), bone: u16(o + 0x14),
            textures: multi ? [packed & 31, (packed >> 5) & 31, (packed >> 10) & 31] : [packed],
            blend: u8(o + 0x28), emitterType: u8(o + 0x29), tileRotation: i16(o + 0x2e), rows: u16(o + 0x30), cols: u16(o + 0x32),
            speed: track(o + 0x34, 'f'), speedVariation: track(o + 0x48, 'f'), verticalRange: track(o + 0x5c, 'f'),
            horizontalRange: track(o + 0x70, 'f'), gravity: track(o + 0x84, 'f'), lifespan: track(o + 0x98, 'f'), lifespanVary: r4(f32(o + 0xac)),
            rate: track(o + 0xb0, 'f'), rateVary: r4(f32(o + 0xc4)), areaLength: track(o + 0xc8, 'f'), areaWidth: track(o + 0xdc, 'f'),
            zSource: track(o + 0xf0, 'f'), color: fblock(o + 0x104, 'v3'), alpha: fblock(o + 0x114, 'fx'), scale: fblock(o + 0x124, 'v2'),
            scaleVary: vec(o + 0x134, 2), headCells: fblock(o + 0x13c, 'u16'), tailCells: fblock(o + 0x14c, 'u16'), tailLength: r4(f32(o + 0x15c)),
            twinkleSpeed: r4(f32(o + 0x160)), twinklePercent: r4(f32(o + 0x164)), twinkleScale: vec(o + 0x168, 2), burst: r4(f32(o + 0x170)),
            drag: r4(f32(o + 0x174)), baseSpin: r4(f32(o + 0x178)), baseSpinVary: r4(f32(o + 0x17c)), spin: r4(f32(o + 0x180)), spinVary: r4(f32(o + 0x184)),
            wind: vec(o + 0x1a0, 3), windTime: r4(f32(o + 0x1ac)),
        };
    });

    const skin = await get(sfid[0]);
    const sArr = (o) => ({ n: skin.readUInt32LE(o), o: skin.readUInt32LE(o + 4) });
    const S = { lookup: sArr(4), indices: sArr(12), sections: sArr(28), batches: sArr(36) };
    const lookup = Array.from({ length: S.lookup.n }, (_, i) => skin.readUInt16LE(S.lookup.o + i * 2));
    const indices = Array.from({ length: S.indices.n }, (_, i) => skin.readUInt16LE(S.indices.o + i * 2));
    const sections = Array.from({ length: S.sections.n }, (_, i) => {
        const o = S.sections.o + i * 48;
        return { vStart: skin.readUInt16LE(o + 4), vCount: skin.readUInt16LE(o + 6), iStart: skin.readUInt16LE(o + 8) + (skin.readUInt16LE(o + 2) << 16), iCount: skin.readUInt16LE(o + 10), boneCombo: skin.readUInt16LE(o + 14) };
    });
    const batches = Array.from({ length: S.batches.n }, (_, i) => {
        const o = S.batches.o + i * 24;
        const section = sections[skin.readUInt16LE(o + 4)];
        const material = materials[skin.readUInt16LE(o + 10)];
        const count = skin.readUInt16LE(o + 14), combo = skin.readUInt16LE(o + 16);
        const tri = indices.slice(section.iStart, section.iStart + section.iCount).map((k) => lookup[k]);
        const used = [...new Set(tri)];
        const at = new Map(used.map((v, k) => [v, k]));
        const bone = bones.length ? vertices[used[0]].b[0] : 0;
        return {
            shader: skin.readUInt16LE(o + 2), priority: skin.readInt8(o + 1), layer: skin.readUInt16LE(o + 12), blend: material.blend, flags: material.flags,
            textures: Array.from({ length: count }, (_, k) => textures[textureCombos[combo + k]]?.fdid ?? 0),
            bone, boneFlags: bones[bone]?.flags ?? 0, pivot: bones[bone]?.pivot ?? [0, 0, 0],
            position: used.flatMap((v) => vertices[v].p), normal: used.flatMap((v) => vertices[v].n), uv: used.flatMap((v) => vertices[v].uv),
            index: tri.map((v) => at.get(v)),
        };
    });

    return {
        fileDataID: Number(fdid), globalLoops: list(H.globalLoops, 'u32'), textures, boneCombos,
        bones: bones.map((b) => ({ flags: b.flags, parent: b.parent, pivot: b.pivot })), lights, particles, batches,
    };
}
