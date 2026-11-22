// The camp scene: Forever's Basic Campfire, Camp Tent and Camp Chair models, drawn from the client's own
// geometry, textures, particle emitters and lights. Smoke, bloom and the ground are added here.
import * as THREE from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';

const SHEET = 0x141416;
const UP = new THREE.Vector3(0, 1, 0);

function mulberry(seed) {
    let s = seed >>> 0;
    return () => ((s = (Math.imul(s ^ (s >>> 15), 2246822507) + 0x9e3779b9) >>> 0) / 4294967296);
}

function sampleTrack(times, values, t) {
    if (values.length === 1) return values[0];
    let i = 1;
    while (i < times.length - 1 && times[i] < t) i++;
    const a = times[i - 1], b = times[i], k = b > a ? Math.min(1, Math.max(0, (t - a) / (b - a))) : 0;
    const va = values[i - 1], vb = values[i];
    return typeof va === 'number' ? va + (vb - va) * k : va.map((v, n) => v + (vb[n] - v) * k);
}

function loadTextures(sources) {
    const loader = new THREE.TextureLoader();
    const out = {};
    const jobs = Object.entries(sources).map(([id, url]) => loader.loadAsync(url).then((t) => {
        t.flipY = false;
        t.colorSpace = THREE.SRGBColorSpace;
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        t.anisotropy = 8;
        out[id] = t;
    }));
    return Promise.all(jobs).then(() => out);
}

function buildModel(model, tex) {
    const group = new THREE.Group();
    const billboards = [];
    for (const b of model.batches) {
        const geo = new THREE.BufferGeometry();
        const pos = new Float32Array(b.position);
        const facing = (b.boneFlags & 0x8) !== 0;
        if (facing) for (let i = 0; i < pos.length; i += 3) { pos[i] -= b.pivot[0]; pos[i + 1] -= b.pivot[1]; pos[i + 2] -= b.pivot[2]; }
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geo.setAttribute('normal', new THREE.Float32BufferAttribute(b.normal, 3));
        geo.setAttribute('uv', new THREE.Float32BufferAttribute(b.uv, 2));
        geo.setIndex(b.index);
        const map = tex[b.textures[0]];
        const unlit = (b.flags & 0x1) !== 0;
        const side = (b.flags & 0x4) !== 0 ? THREE.DoubleSide : THREE.FrontSide;
        let mat;
        if (b.blend >= 3) {
            mat = new THREE.MeshBasicMaterial({ map, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide, fog: false });
        } else if (unlit) {
            mat = new THREE.MeshBasicMaterial({ map, side, alphaTest: b.blend === 1 ? 0.5 : 0 });
        } else {
            mat = new THREE.MeshLambertMaterial({ map, side, alphaTest: b.blend === 1 ? 0.5 : 0 });
        }
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData = { blend: b.blend, unlit };
        if (b.blend < 3) { mesh.castShadow = !unlit; mesh.receiveShadow = !unlit; }
        if (facing) { mesh.position.fromArray(b.pivot); billboards.push(mesh); }
        group.add(mesh);
    }
    return { group, billboards };
}

class Emitter {
    constructor(def, texture, loopMs, rand, boost, grow) {
        this.def = def;
        this.loopMs = loopMs;
        this.rand = rand;
        this.boost = boost;
        this.grow = grow;
        this.streak = def.tailLength > 0 && def.rows * def.cols === 1;
        const peak = Math.max(...def.rate.v) + def.rateVary * 0.5;
        this.max = Math.ceil(peak * boost * (def.lifespan.v[0] + def.lifespanVary) * 1.5) + 8;
        this.p = Array.from({ length: this.max }, () => ({ life: 0, age: 1, x: 0, y: 0, z: 0, vx: 0, vy: 0, vz: 0, seed: 0 }));
        this.carry = 0;
        const geo = new THREE.InstancedBufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute([-0.5, -0.5, 0, 0.5, -0.5, 0, 0.5, 0.5, 0, -0.5, 0.5, 0], 3));
        geo.setIndex([0, 1, 2, 2, 3, 0]);
        this.aPos = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        this.aVel = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        this.aCol = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 4), 4);
        this.aSize = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        for (const [n, a] of [['iPos', this.aPos], ['iVel', this.aVel], ['iCol', this.aCol], ['iSize', this.aSize]]) { a.setUsage(THREE.DynamicDrawUsage); geo.setAttribute(n, a); }
        this.mesh = new THREE.Mesh(geo, new THREE.ShaderMaterial({
            uniforms: { map: { value: texture }, grid: { value: new THREE.Vector2(def.cols, def.rows) }, streak: { value: this.streak ? 1 : 0 }, gain: { value: this.streak ? 1.6 : 0.62 } },
            transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
            vertexShader: `
                attribute vec3 iPos; attribute vec3 iVel; attribute vec4 iCol; attribute vec3 iSize;
                uniform vec2 grid; uniform float streak;
                varying vec2 vUv; varying vec4 vCol;
                void main() {
                    vec4 centre = modelViewMatrix * vec4(iPos, 1.0);
                    vec2 corner = position.xy * iSize.xy;
                    if (streak > 0.5) {
                        vec3 v = (modelViewMatrix * vec4(iVel, 0.0)).xyz;
                        vec2 dir = length(v.xy) > 1e-5 ? normalize(v.xy) : vec2(0.0, 1.0);
                        vec2 across = vec2(-dir.y, dir.x);
                        corner = across * position.x * iSize.x + dir * position.y * (iSize.y + length(v) * 0.2);
                    }
                    centre.xy += corner;
                    float cell = floor(iSize.z);
                    vec2 at = vec2(mod(cell, grid.x), floor(cell / grid.x));
                    vUv = (at + vec2(position.x + 0.5, 0.5 - position.y)) / grid;
                    vCol = iCol;
                    gl_Position = projectionMatrix * centre;
                }`,
            fragmentShader: `
                uniform sampler2D map; uniform float gain; varying vec2 vUv; varying vec4 vCol;
                void main() {
                    vec4 t = texture2D(map, vUv);
                    gl_FragColor = vec4(t.rgb * vCol.rgb * t.a * vCol.a * gain, 1.0);
                }`,
        }));
        this.mesh.frustumCulled = false;
    }

    spawn(q) {
        const d = this.def, r = this.rand;
        q.life = Math.max(0.05, d.lifespan.v[0] + (r() * 2 - 1) * d.lifespanVary);
        q.age = 0;
        q.seed = r();
        q.x = (r() - 0.5) * d.areaLength.v[0];
        q.y = (r() - 0.5) * d.areaWidth.v[0];
        q.z = 0;
        const tilt = r() * d.verticalRange.v[0], turn = r() * d.horizontalRange.v[0];
        const speed = d.speed.v[0] * (1 + (r() * 2 - 1) * d.speedVariation.v[0]);
        q.vx = Math.sin(tilt) * Math.cos(turn) * speed;
        q.vy = Math.sin(tilt) * Math.sin(turn) * speed;
        q.vz = Math.cos(tilt) * speed;
    }

    step(dt, now) {
        const d = this.def, r = this.rand;
        const rate = Math.max(0, sampleTrack(d.rate.t, d.rate.v, (now * 1000) % this.loopMs) + (r() - 0.5) * d.rateVary) * this.boost;
        this.carry += rate * dt;
        const cells = d.rows * d.cols;
        let n = 0;
        for (const q of this.p) {
            if (q.age >= 1) {
                if (this.carry < 1) continue;
                this.carry -= 1;
                this.spawn(q);
            }
            const drag = Math.max(0, 1 - d.drag * dt);
            q.vx *= drag; q.vy *= drag; q.vz = q.vz * drag - d.gravity.v[0] * dt;
            q.x += q.vx * dt; q.y += q.vy * dt; q.z += q.vz * dt;
            q.age += dt / q.life;
            if (q.age >= 1) continue;
            const c = sampleTrack(d.color.t, d.color.v, q.age), a = sampleTrack(d.alpha.t, d.alpha.v, q.age), s = sampleTrack(d.scale.t, d.scale.v, q.age);
            const vary = (1 + (q.seed * 2 - 1) * d.scaleVary[0]) * this.grow;
            this.aPos.setXYZ(n, q.x, q.y, q.z);
            this.aVel.setXYZ(n, q.vx, q.vy, q.vz);
            this.aCol.setXYZW(n, c[0] / 255, c[1] / 255, c[2] / 255, a);
            this.aSize.setXYZ(n, s[0] * 2 * vary, s[1] * 2 * vary, Math.min(cells - 1, Math.floor(q.age * cells)));
            n++;
        }
        this.mesh.geometry.instanceCount = n;
        this.aPos.needsUpdate = this.aVel.needsUpdate = this.aCol.needsUpdate = this.aSize.needsUpdate = true;
    }
}

function buildSmoke(rand) {
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
    const max = 46;
    const puffs = Array.from({ length: max }, (_, i) => ({ age: i / max, seed: rand(), turn: rand() * 6.283 }));
    const sprites = puffs.map(() => {
        const s = new THREE.Sprite(new THREE.SpriteMaterial({ map, transparent: true, depthWrite: false, opacity: 0 }));
        s.renderOrder = 1;
        return s;
    });
    const hot = new THREE.Color(0xff8a3c), cold = new THREE.Color(0x6f7684);
    const step = (dt, heat) => puffs.forEach((p, i) => {
        p.age += dt / 7.5;
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

function buildGround(rand) {
    const size = 512, cv = document.createElement('canvas');
    cv.width = cv.height = size;
    const ctx = cv.getContext('2d');
    ctx.fillStyle = '#6b6258';
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
    const mesh = new THREE.Mesh(new THREE.CircleGeometry(60, 64), new THREE.MeshLambertMaterial({ map }));
    mesh.rotation.x = -Math.PI / 2;
    mesh.receiveShadow = true;
    return mesh;
}

export async function mountCamp(stage, data, opts = {}) {
    const still = opts.still ?? matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rand = mulberry(11);
    const tex = await loadTextures(data.tex);
    const renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;
    stage.append(renderer.domElement);

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(SHEET);
    scene.fog = new THREE.Fog(SHEET, 9, 30);
    const camera = new THREE.PerspectiveCamera(30, 2, 0.1, 120);

    const world = new THREE.Group();
    world.rotation.x = -Math.PI / 2;
    scene.add(world, buildGround(rand));

    const fire = buildModel(data.models.campfire, tex);
    world.add(fire.group);
    const place = (key, x, y, turn, scale = 1) => {
        const m = buildModel(data.models[key], tex);
        m.group.position.set(x, y, 0);
        m.group.rotation.z = turn;
        m.group.scale.setScalar(scale);
        world.add(m.group);
        return m.group;
    };
    const yaw0 = opts.yaw ?? 0.62;
    const beside = (across, away) => {
        const x = Math.cos(yaw0) * across - Math.sin(yaw0) * away, z = -Math.sin(yaw0) * across - Math.cos(yaw0) * away;
        return { x, y: -z, turn: Math.atan2(z, -x) };
    };
    const spots = { tent: beside(6.0, 6.8), chair: beside(-2.7, 0.9) };
    const tent = place('tent', spots.tent.x, spots.tent.y, spots.tent.turn);
    const chair = place('chair', spots.chair.x, spots.chair.y, spots.chair.turn);

    const def = data.models.campfire;
    const flames = new Emitter(def.particles[0], tex[def.textures[def.particles[0].textures[0]].fdid], def.globalLoops[0], rand, 2.4, 2.3);
    const sparks = new Emitter(def.particles[1], tex[def.textures[def.particles[1].textures[0]].fdid], def.globalLoops[0], rand, 1.8, 2.2);
    const emitters = [flames, sparks];
    flames.mesh.position.fromArray(def.bones[def.particles[0].bone].pivot);
    sparks.mesh.position.fromArray(def.bones[def.particles[1].bone].pivot);
    flames.mesh.position.z += 0.18;
    sparks.mesh.position.z += 0.3;
    world.add(flames.mesh, sparks.mesh);

    const [steady, low] = def.lights;
    const colour = (l) => new THREE.Color().setRGB(...l.diffuseColor.v[0], THREE.SRGBColorSpace).lerp(new THREE.Color(1, 0.78, 0.55), 0.38);
    const key = new THREE.PointLight(colour(steady), 1, steady.attenuationEnd.v[0] * 3.2, 1.25);
    key.position.fromArray(steady.position);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    key.shadow.bias = -0.0015;
    key.shadow.radius = 5;
    const ember = new THREE.PointLight(colour(low), 1, low.attenuationEnd.v[0] * 2.6, 1.3);
    ember.position.fromArray(low.position);
    world.add(key, ember);
    scene.add(new THREE.HemisphereLight(0x6177ad, 0x1c1714, 0.8));
    const moon = new THREE.DirectionalLight(0x7f95c8, 0.55);
    moon.position.set(-6, 9, -4);
    scene.add(moon);

    const smoke = buildSmoke(rand);
    scene.add(...smoke.sprites);

    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    const bloom = new UnrealBloomPass(new THREE.Vector2(1, 1), 0.5, 0.75, 0.8);
    composer.addPass(bloom);

    let redraw = () => {};
    const view = { yaw: yaw0, turned: 0, pitch: 0.15, dist: 11.8, shift: 0, target: new THREE.Vector3(), drag: null };
    const size = () => {
        const w = stage.clientWidth, h = stage.clientHeight;
        renderer.setSize(w, h, false);
        composer.setSize(w, h);
        camera.aspect = w / h;
        view.dist = 11.8 * Math.max(1, 1.28 / camera.aspect);
        const half = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * view.dist * camera.aspect;
        view.shift = (opts.offset ? opts.offset(w) : 0) * half;
        camera.updateProjectionMatrix();
        redraw();
    };
    new ResizeObserver(size).observe(stage);
    size();

    const el = renderer.domElement;
    el.style.touchAction = 'pan-y';
    el.addEventListener('pointerdown', (e) => { view.drag = { x: e.clientX, turned: view.turned }; el.setPointerCapture(e.pointerId); });
    el.addEventListener('pointermove', (e) => { if (view.drag) { view.turned = view.drag.turned - (e.clientX - view.drag.x) * 0.006; redraw(); } });
    for (const end of ['pointerup', 'pointercancel']) el.addEventListener(end, () => { view.drag = null; });

    const anchors = [
        { key: 'fire', at: new THREE.Vector3(0, 1.9, 0) },
        { key: 'tent', at: new THREE.Vector3(spots.tent.x, 4.45, -spots.tent.y) },
        { key: 'chair', at: new THREE.Vector3(spots.chair.x, 1.75, -spots.chair.y) },
    ];
    const basis = new THREE.Matrix4(), right = new THREE.Vector3(), up = new THREE.Vector3(), back = new THREE.Vector3(), q = new THREE.Quaternion(), inv = new THREE.Quaternion();
    const projected = new THREE.Vector3();
    let visible = true, running = false, last = performance.now(), clock = 0;

    const draw = (dt) => {
        clock += dt;
        view.yaw = yaw0 + view.turned + (still ? 0 : Math.sin(clock * 0.11) * 0.2);
        view.target.set(-Math.cos(view.yaw) * view.shift, 1.75, Math.sin(view.yaw) * view.shift);
        camera.position.set(view.target.x + Math.sin(view.yaw) * Math.cos(view.pitch) * view.dist, view.target.y + Math.sin(view.pitch) * view.dist, view.target.z + Math.cos(view.yaw) * Math.cos(view.pitch) * view.dist);
        camera.lookAt(view.target);
        camera.updateMatrixWorld();

        const flick = sampleTrack(low.diffuseIntensity.t, low.diffuseIntensity.v, (clock * 1000) % def.globalLoops[0]);
        key.intensity = 30 * (0.8 + flick * 0.2);
        ember.intensity = 22 * flick;
        key.position.set(steady.position[0] + Math.sin(clock * 9.1) * 0.035, steady.position[1] + Math.sin(clock * 7.3) * 0.035, steady.position[2]);
        bloom.strength = 0.42 + flick * 0.16;

        camera.matrixWorld.extractBasis(right, up, back);
        world.getWorldQuaternion(inv).invert();
        basis.makeBasis(back, right, up);
        q.setFromRotationMatrix(basis).premultiply(inv);
        for (const b of fire.billboards) {
            b.quaternion.copy(q);
            b.material.opacity = 0.22 + flick * 0.14;
        }
        for (const m of fire.group.children) if (m.userData.blend === 7) m.material.opacity = 0.4 + flick * 0.25;

        for (const e of emitters) e.step(dt, clock);
        smoke.step(dt, flick);
        composer.render();

        if (opts.onFrame) {
            const w = stage.clientWidth, h = stage.clientHeight;
            opts.onFrame(anchors.map((a) => {
                projected.copy(a.at).project(camera);
                return { key: a.key, x: (projected.x * 0.5 + 0.5) * w, y: (-projected.y * 0.5 + 0.5) * h, shown: projected.z < 1 };
            }));
        }
    };
    const frame = (now) => {
        if (!visible || document.hidden) { running = false; return; }
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        draw(dt);
        requestAnimationFrame(frame);
    };
    let lost = false;
    const start = () => {
        if (lost || still || running || !visible || document.hidden) return;
        running = true;
        last = performance.now();
        requestAnimationFrame(frame);
    };
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; start(); }).observe(stage);
    document.addEventListener('visibilitychange', start);
    el.addEventListener('webglcontextlost', () => { lost = true; running = false; opts.onLost?.(); });
    redraw = () => { if (still) draw(0); };

    for (let i = 0; i < 90; i++) { clock += 1 / 30; for (const e of emitters) e.step(1 / 30, clock); smoke.step(1 / 30, 1); }
    if (still) draw(0); else start();
    return { scene, camera, renderer, view, tent, chair };
}
