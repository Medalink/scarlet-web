import * as THREE from 'three';

const TILE = 3.1;

function dirt(rand, tint) {
    const size = 512, cv = document.createElement('canvas');
    cv.width = cv.height = size;
    const ctx = cv.getContext('2d');
    ctx.fillStyle = tint;
    ctx.fillRect(0, 0, size, size);
    for (let i = 0; i < 26000; i++) {
        const v = 60 + rand() * 90;
        ctx.fillStyle = `rgba(${v + 6},${v + 4},${v - 2},${0.05 + rand() * 0.16})`;
        const r = 0.4 + rand() * 1.9;
        ctx.beginPath();
        ctx.ellipse(rand() * size, rand() * size, r, r * (0.5 + rand() * 0.6), rand() * 3.14, 0, 6.283);
        ctx.fill();
    }
    for (let i = 0; i < 260; i++) {
        const dark = rand() < 0.6, r = 6 + rand() * 30, x = rand() * size, y = rand() * size;
        const g = ctx.createRadialGradient(x, y, 0, x, y, r);
        g.addColorStop(0, dark ? 'rgba(20,18,16,0.3)' : 'rgba(190,180,160,0.2)');
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        for (const dx of [-size, 0, size]) for (const dy of [-size, 0, size]) { ctx.setTransform(1, 0, 0, 1, dx, dy); ctx.fillRect(x - r, y - r, r * 2, r * 2); }
    }
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    const map = new THREE.CanvasTexture(cv);
    map.wrapS = map.wrapT = THREE.RepeatWrapping;
    map.colorSpace = THREE.SRGBColorSpace;
    return map;
}

/**
 * A low dome of ground: the cap of a sphere of `radius` whose top stands `height` above the zero plane at `x`, `z`.
 * `shore` darkens the foot, where water meets it. `lift(x, z)` is the height of its surface there.
 */
export function mound(rand, { x, z, height, radius, tint, shore = false }) {
    const R = radius, top = height;
    const geo = new THREE.SphereGeometry(R, 160, 96, 0, Math.PI * 2, 0, 0.5);
    const pos = geo.attributes.position, uv = geo.attributes.uv, shade = new Float32Array(pos.count * 3);
    for (let i = 0; i < pos.count; i++) {
        const h = pos.getY(i) - (R - top), k = shore ? Math.min(1, Math.max(0, h / 0.3)) : 1, v = 0.3 + 0.7 * k * k * (3 - 2 * k);
        shade.set([v, v, v * (1.08 - 0.08 * k)], i * 3);
        uv.setXY(i, pos.getX(i) / TILE, pos.getZ(i) / TILE);
    }
    geo.setAttribute('color', new THREE.BufferAttribute(shade, 3));
    const mesh = new THREE.Mesh(geo, new THREE.MeshLambertMaterial({ map: dirt(rand, tint), vertexColors: true }));
    mesh.position.set(x, top - R, z);
    mesh.receiveShadow = true;
    const lift = (px, pz) => Math.sqrt(Math.max(0, R * R - (px - x) ** 2 - (pz - z) ** 2)) - (R - top);
    return { mesh, lift };
}
