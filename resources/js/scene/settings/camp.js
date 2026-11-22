import * as THREE from 'three';
import { sampleTrack } from '../math.js';
import { smoke } from './smoke.js';

const WARM = new THREE.Color(1, 0.78, 0.55);
const WARM_SHARE = 0.38;

function packedEarth(rand, tint) {
    const size = 512, cv = document.createElement('canvas');
    cv.width = cv.height = size;
    const ctx = cv.getContext('2d');
    ctx.fillStyle = tint;
    ctx.fillRect(0, 0, size, size);
    for (let i = 0; i < 26000; i++) {
        const v = 60 + rand() * 90;
        ctx.fillStyle = `rgba(${v + 10},${v + 2},${v - 6},${0.05 + rand() * 0.16})`;
        const r = 0.4 + rand() * 1.9;
        ctx.beginPath();
        ctx.ellipse(rand() * size, rand() * size, r, r * (0.5 + rand() * 0.6), rand() * 3.14, 0, 6.283);
        ctx.fill();
    }
    const map = new THREE.CanvasTexture(cv);
    map.wrapS = map.wrapT = THREE.RepeatWrapping;
    map.repeat.set(11, 11);
    map.colorSpace = THREE.SRGBColorSpace;
    return map;
}

function lightOf(def, o) {
    const colour = new THREE.Color().setRGB(...def.diffuseColor.v[0], THREE.SRGBColorSpace).lerp(WARM, WARM_SHARE);
    const light = new THREE.PointLight(colour, 1, def.attenuationEnd.v[0] * o.reach, o.decay);
    light.position.fromArray(def.position);
    return light;
}

/**
 * Flat ground under a moon, a column of smoke, and a fire the model lights itself: the model's own two point lights
 * (the first steady and casting the shadows, the second following the model's flicker track), the glow of its
 * camera-facing flame batches and its blend 7 core, and the bloom, all moving with that one track.
 * `config` is `{ ground: { tint, radius }, moon: { from, colour, power, sky, floor, fill }, fire: { prop, key: { light, power, base, swing, reach, decay, shake }, ember: { light, power, reach, decay }, bloom: [base, swing], glow: [base, swing], core: [base, swing] } }`.
 */
export function camp(config, { scene, rand, bloom }) {
    const earth = new THREE.Mesh(new THREE.CircleGeometry(config.ground.radius, 64), new THREE.MeshLambertMaterial({ map: packedEarth(rand, config.ground.tint) }));
    earth.rotation.x = -Math.PI / 2;
    earth.receiveShadow = true;
    scene.add(earth);

    scene.add(new THREE.HemisphereLight(config.moon.sky, config.moon.floor, config.moon.fill));
    const moon = new THREE.DirectionalLight(config.moon.colour, config.moon.power);
    moon.position.set(...config.moon.from);
    scene.add(moon);

    const column = smoke(rand);
    scene.add(...column.sprites);

    const fire = config.fire;
    const lit = { key: null, ember: null, track: null, loop: 1000, base: null, billboards: [], cores: [] };
    const basis = new THREE.Matrix4(), right = new THREE.Vector3(), up = new THREE.Vector3(), back = new THREE.Vector3();
    const facing = new THREE.Quaternion(), parent = new THREE.Quaternion();

    return {
        lift: () => 0,
        attach(placed) {
            const { holder, def } = placed[fire.prop];
            const keyDef = def.lights[fire.key.light], emberDef = def.lights[fire.ember.light];
            lit.key = lightOf(keyDef, fire.key);
            lit.key.castShadow = true;
            lit.key.shadow.mapSize.set(1024, 1024);
            lit.key.shadow.bias = -0.0015;
            lit.key.shadow.radius = 5;
            lit.ember = lightOf(emberDef, fire.ember);
            lit.base = keyDef.position;
            lit.track = emberDef.diffuseIntensity;
            lit.loop = def.globalLoops[0];
            holder.add(lit.key, lit.ember);
            holder.traverse((m) => {
                if (m.userData.billboard) lit.billboards.push(m);
                if (m.userData.blend === 7) lit.cores.push(m);
            });
        },
        warm(dt) {
            column.step(dt, 1);
        },
        step(clock, eye, ripple, { camera, dt }) {
            const flick = sampleTrack(lit.track.t, lit.track.v, (clock * 1000) % lit.loop);
            lit.key.intensity = fire.key.power * (fire.key.base + flick * fire.key.swing);
            lit.ember.intensity = fire.ember.power * flick;
            lit.key.position.set(lit.base[0] + Math.sin(clock * 9.1) * fire.key.shake, lit.base[1] + Math.sin(clock * 7.3) * fire.key.shake, lit.base[2]);
            bloom.strength = fire.bloom[0] + flick * fire.bloom[1];

            camera.matrixWorld.extractBasis(right, up, back);
            basis.makeBasis(back, right, up);
            facing.setFromRotationMatrix(basis);
            for (const b of lit.billboards) {
                b.parent.getWorldQuaternion(parent).invert();
                b.quaternion.copy(facing).premultiply(parent);
                b.material.opacity = fire.glow[0] + flick * fire.glow[1];
            }
            for (const m of lit.cores) m.material.opacity = fire.core[0] + flick * fire.core[1];

            column.step(dt, flick);
        },
    };
}
