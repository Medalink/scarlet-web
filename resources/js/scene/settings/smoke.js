import * as THREE from 'three';

const PUFFS = 46;
const LIFE = 7.5;

/**
 * A column of smoke over a fire: 46 soft puffs that rise, sway, grow and fade from ember orange to grey. `step(dt, heat)`
 * moves them, and `heat` (0 to 1) warms the colour. Add `sprites` to the scene.
 */
export function smoke(rand) {
    const size = 128, cv = document.createElement('canvas');
    cv.width = cv.height = size;
    const ctx = cv.getContext('2d'), img = ctx.createImageData(size, size);
    const blobs = Array.from({ length: 9 }, () => [0.25 + rand() * 0.5, 0.25 + rand() * 0.5, 0.12 + rand() * 0.2]);
    for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
        const u = x / size, v = y / size;
        let d = 0;
        for (const [bx, by, br] of blobs) d += Math.exp(-((u - bx) ** 2 + (v - by) ** 2) / (br * br));
        const edge = Math.max(0, 1 - Math.hypot(u - 0.5, v - 0.5) * 2);
        const a = Math.min(1, d * 0.28) * edge * edge;
        img.data.set([255, 255, 255, a * 255], (y * size + x) * 4);
    }
    ctx.putImageData(img, 0, 0);
    const map = new THREE.CanvasTexture(cv);
    const puffs = Array.from({ length: PUFFS }, (_, i) => ({ age: i / PUFFS, seed: rand(), turn: rand() * 6.283 }));
    const sprites = puffs.map(() => {
        const s = new THREE.Sprite(new THREE.SpriteMaterial({ map, transparent: true, depthWrite: false, opacity: 0 }));
        s.renderOrder = 1;
        return s;
    });
    const hot = new THREE.Color(0xff8a3c), cold = new THREE.Color(0x6f7684);
    const step = (dt, heat) => puffs.forEach((p, i) => {
        p.age += dt / LIFE;
        if (p.age >= 1) { p.age -= 1; p.seed = rand(); p.turn = rand() * 6.283; }
        const k = p.age, s = sprites[i];
        const sway = Math.sin(k * 5 + p.turn) * 0.3 * k + (p.seed - 0.5) * 1.5 * k;
        s.position.set(Math.cos(p.turn) * 0.12 + sway + k * k * 2.2, 0.8 + k * 6.6, Math.sin(p.turn) * 0.12 + sway * 0.6);
        s.scale.setScalar(0.5 + k * 3.4);
        s.material.rotation = p.turn + k * (p.seed - 0.5) * 2.2;
        s.material.opacity = Math.sin(Math.min(1, k * 4) * 1.5708) * (1 - k) ** 1.5 * 0.3;
        s.material.color.copy(hot).lerp(cold, Math.min(1, k * 3.2)).multiplyScalar(0.55 + heat * 0.25);
    });
    return { sprites, step };
}
