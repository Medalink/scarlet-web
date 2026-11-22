import * as THREE from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { Emitter } from './emitter.js';
import { mulberry } from './math.js';
import { buildModel, loadTextures } from './model.js';
import { camp } from './settings/camp.js';
import { ground } from './settings/ground.js';
import { water } from './settings/water.js';

const SHEET = 0x141416;
const SETTINGS = { water, ground, camp };
const SHADOWS = { plain: THREE.PCFShadowMap };
const WIDE = 820;

/**
 * Draws one scene of the game's own models into `stage` and returns its handle. `data` is the art tool's output
 * (`models` by key, `tex` as `{ file id: url }`); `spec` is a file of resources/js/scene/scenes.
 *
 * `spec.props[]` is `{ key, model, at: [across, away], on, lift, turn, tilt, scale, emit, bob, anchor }`: `at` is where
 * the prop stands in the opening view, `emit` tunes each of the model's emitters by index (or `all`; `order` sets its
 * render order), `bob` floats it and lets a bite duck it under, `anchor` is the point a callout stands on. `spec.setting`
 * names what the page draws itself (resources/js/scene/settings) and carries its configuration. A setting is
 * `{ lift(point), step(clock, eye, ripple, { camera, dt }), attach?(placed), warm?(dt) }`: `attach` gets the placed props
 * by key as `{ holder, def }` once, and `warm` runs with each step of the warm-up.
 *
 * `opts`: `still`, `offset(width)`, `onFrame(points)` for the callouts, `onLost()` when the browser takes the context.
 */
export async function mountScene(stage, data, spec, opts = {}) {
    const still = opts.still ?? matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rand = mulberry(11);
    const tex = await loadTextures(data.tex);
    const renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = SHADOWS[spec.look.shadow];
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;
    stage.append(renderer.domElement);
    try {
        return assemble(stage, data, spec, opts, { renderer, tex, still, rand });
    } catch (error) {
        renderer.dispose();
        renderer.forceContextLoss();
        throw error;
    }
}

function assemble(stage, data, spec, opts, { renderer, tex, still, rand }) {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(SHEET);
    scene.fog = new THREE.Fog(SHEET, ...spec.look.fog);
    const camera = new THREE.PerspectiveCamera(30, 2, 0.1, spec.look.far);
    const world = new THREE.Group();
    world.rotation.x = -Math.PI / 2;
    scene.add(world);

    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    const [strength, radius, threshold] = spec.look.bloom;
    const bloom = new UnrealBloomPass(new THREE.Vector2(1, 1), strength, radius, threshold);
    composer.addPass(bloom);

    const yaw0 = spec.view.yaw;
    const spot = ([across, away]) => ({ x: Math.cos(yaw0) * across - Math.sin(yaw0) * away, z: -Math.sin(yaw0) * across - Math.cos(yaw0) * away });
    const setting = SETTINGS[spec.setting.name](spec.setting, { scene, rand, spot, sheet: SHEET, bloom });

    const emitters = [], anchors = [], bobbers = [], placed = {};
    for (const p of spec.props) {
        const def = data.models[p.model];
        const at = spot(p.at);
        const lift = setting.lift({ on: p.on, x: at.x, z: at.z }) + (p.lift ?? 0);
        const holder = new THREE.Group();
        holder.position.set(at.x, -at.z, lift);
        holder.rotation.z = (p.turn ?? 0) + yaw0;
        if (p.tilt) holder.rotation.y = p.tilt;
        holder.scale.setScalar(p.scale ?? 1);
        holder.add(buildModel(def, tex));
        world.add(holder);
        placed[p.key] = { holder, def };
        const mine = [];
        if (p.emit) def.particles.forEach((e, i) => {
            const o = p.emit[i] ?? p.emit.all;
            if (!o) return;
            const em = new Emitter(e, tex[def.textures[e.textures[0]].fdid], def.globalLoops[0], rand, o);
            em.mesh.position.fromArray(def.bones[e.bone]?.pivot ?? e.position);
            em.mesh.position.z += o.lift ?? 0;
            em.mesh.renderOrder = o.order ?? (e.position[2] < -0.5 ? 0 : 2);
            holder.add(em.mesh);
            emitters.push(em);
            mine.push(em);
        });
        if (p.bob) bobbers.push({ holder, base: lift, at, emit: mine, next: 4 + rand() * 3, dip: 0, now: false });
        if (p.anchor) anchors.push({ key: p.key, at: new THREE.Vector3(at.x + (p.anchor[0] ?? 0), lift + p.anchor[1], at.z + (p.anchor[2] ?? 0)) });
    }
    setting.attach?.(placed);

    let redraw = () => {};
    let stageW = 0, stageH = 0;
    const offset = opts.offset ?? ((width) => (width > WIDE ? spec.view.offset.wide : spec.view.offset.narrow));
    const view = { yaw: yaw0, turned: 0, pitch: spec.view.pitch, dist: spec.view.dist, shift: 0, target: new THREE.Vector3(), drag: null };
    const size = () => {
        const w = stage.clientWidth, h = stage.clientHeight;
        if (w <= 0 || h <= 0) return;
        stageW = w;
        stageH = h;
        renderer.setSize(w, h, false);
        composer.setSize(w, h);
        camera.aspect = w / h;
        view.dist = spec.view.dist * Math.max(1, 1.28 / camera.aspect);
        const half = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * view.dist * camera.aspect;
        view.shift = offset(w) * half;
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

    const projected = new THREE.Vector3();
    let visible = true, running = false, lost = false, last = performance.now(), clock = 0;

    const tick = (dt) => {
        clock += dt;
        let ripple = null;
        for (const b of bobbers) {
            b.next -= dt;
            if (b.next <= 0 || b.now) {
                b.now = false;
                b.next = 7 + rand() * 5;
                b.dip = 1;
                for (const e of b.emit) if (!e.def.rate.v.length) e.burst(e.flat ? 3 : 26);
            }
            b.dip = Math.max(0, b.dip - dt * 1.6);
            const duck = Math.sin(Math.min(1, b.dip) * Math.PI) * 0.16;
            b.holder.position.z = b.base + Math.sin(clock * 2.1) * 0.022 + Math.sin(clock * 3.4) * 0.01 - duck;
            b.holder.rotation.x = Math.sin(clock * 1.3) * 0.07 + b.dip * 0.25;
            b.holder.rotation.y = Math.sin(clock * 1.7 + 1) * 0.06;
            ripple = { x: b.at.x, z: b.at.z, strength: 0.35 + b.dip * 2.2 };
        }
        for (const e of emitters) e.step(dt, clock);
        return ripple;
    };

    const shot = { camera, dt: 0 };
    const draw = (dt) => {
        const ripple = tick(dt);
        view.yaw = yaw0 + view.turned + (still ? 0 : Math.sin(clock * 0.11) * spec.view.sway);
        view.target.set(-Math.cos(view.yaw) * view.shift, spec.view.targetY, Math.sin(view.yaw) * view.shift);
        camera.position.set(view.target.x + Math.sin(view.yaw) * Math.cos(view.pitch) * view.dist, view.target.y + Math.sin(view.pitch) * view.dist, view.target.z + Math.cos(view.yaw) * Math.cos(view.pitch) * view.dist);
        camera.lookAt(view.target);
        camera.updateMatrixWorld();
        shot.dt = dt;
        setting.step(clock, camera.position, ripple, shot);
        composer.render();

        if (opts.onFrame && stageW > 0 && stageH > 0) {
            opts.onFrame(anchors.map((a) => {
                projected.copy(a.at).project(camera);
                return { key: a.key, x: (projected.x * 0.5 + 0.5) * stageW, y: (-projected.y * 0.5 + 0.5) * stageH, shown: projected.z < 1 };
            }), stageW, stageH);
        }
    };
    const frame = (now) => {
        if (!visible || document.hidden) { running = false; return; }
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        draw(dt);
        requestAnimationFrame(frame);
    };
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

    for (let i = 0; i < spec.warmup; i++) {
        tick(1 / 30);
        setting.warm?.(1 / 30);
    }
    if (still) draw(0); else start();
    return { scene, camera, renderer, view, bite: () => bobbers.forEach((b) => { b.now = true; }) };
}
