import * as THREE from 'three';
import { mound } from './land.js';

/**
 * Open ground under one low light: a wide dome of earth that falls away into the sheet's colour, a sun or moon that
 * casts the shadows, and any number of small warm lights (a forge, a lantern) that flicker.
 * `config` is `{ ground: { tint, radius }, sun: { from, colour, power, sky, floor, fill }, glows: [{ at, lift, colour, power, reach, flicker }] }`.
 */
export function ground(config, { scene, rand, spot }) {
    const land = mound(rand, { x: 0, z: 0, height: 0, radius: config.ground.radius, tint: config.ground.tint });
    scene.add(land.mesh);

    scene.add(new THREE.HemisphereLight(config.sun.sky, config.sun.floor, config.sun.fill));
    const sun = new THREE.DirectionalLight(config.sun.colour, config.sun.power);
    sun.position.set(...config.sun.from).multiplyScalar(24);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.bias = -0.0012;
    Object.assign(sun.shadow.camera, { left: -13, right: 13, top: 13, bottom: -13, near: 1, far: 60 });
    sun.shadow.camera.updateProjectionMatrix();
    scene.add(sun, sun.target);

    const glows = (config.glows ?? []).map((glow) => {
        const at = spot(glow.at);
        const light = new THREE.PointLight(glow.colour, glow.power, glow.reach, 1.3);
        light.position.set(at.x, land.lift(at.x, at.z) + glow.lift, at.z);
        scene.add(light);
        return { light, power: glow.power, flicker: glow.flicker ?? 0, phase: rand() * 6.283 };
    });

    return {
        lift: (p) => land.lift(p.x, p.z),
        step(clock) {
            for (const glow of glows) {
                const wave = Math.sin(clock * 9.1 + glow.phase) * 0.5 + Math.sin(clock * 23.7 + glow.phase * 2) * 0.3 + Math.sin(clock * 3.3) * 0.2;
                glow.light.intensity = glow.power * (1 + wave * glow.flicker);
            }
        },
    };
}
