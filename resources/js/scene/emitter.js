import * as THREE from 'three';
import { sampleTrack } from './math.js';

const FLAT = 0x1000;
const MAX_PARTICLES = 900;
const ADDITIVE_FROM = 4;
const BLOW_UP = 1e3;

/**
 * A particle emitter of a model, simulated on the CPU and drawn as instanced quads in one of three ways: facing the
 * camera, stretched along the velocity, or lying flat in the model's ground plane (flag 0x1000, water rings).
 * `o` is the spec's tuning: `boost` the rate, `grow` the size, `gain` the brightness, `streak` to force the stretch.
 */
export class Emitter {
    constructor(def, texture, loopMs, rand, o = {}) {
        this.def = def;
        this.loopMs = loopMs || 1000;
        this.rand = rand;
        this.boost = o.boost ?? 1;
        this.grow = o.grow ?? 1;
        this.flat = (def.flags & FLAT) !== 0;
        this.streak = !this.flat && (o.streak ?? (def.tailLength > 1 && def.rows * def.cols === 1));
        this.additive = def.blend >= ADDITIVE_FROM;
        const peak = Math.max(1, ...def.rate.v) + def.rateVary * 0.5;
        this.max = Math.min(MAX_PARTICLES, Math.ceil(peak * this.boost * (def.lifespan.v[0] + def.lifespanVary) * 1.5) + 40);
        this.p = Array.from({ length: this.max }, () => ({ life: 0, age: 1, x: 0, y: 0, z: 0, vx: 0, vy: 0, vz: 0, seed: 0 }));
        this.carry = 0;
        this.pending = 0;
        const geo = new THREE.InstancedBufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute([-0.5, -0.5, 0, 0.5, -0.5, 0, 0.5, 0.5, 0, -0.5, 0.5, 0], 3));
        geo.setIndex([0, 1, 2, 2, 3, 0]);
        this.aPos = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        this.aVel = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        this.aCol = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 4), 4);
        this.aSize = new THREE.InstancedBufferAttribute(new Float32Array(this.max * 3), 3);
        for (const [n, a] of [['iPos', this.aPos], ['iVel', this.aVel], ['iCol', this.aCol], ['iSize', this.aSize]]) { a.setUsage(THREE.DynamicDrawUsage); geo.setAttribute(n, a); }
        this.mesh = new THREE.Mesh(geo, new THREE.ShaderMaterial({
            uniforms: { map: { value: texture }, grid: { value: new THREE.Vector2(def.cols, def.rows) }, mode: { value: this.flat ? 2 : this.streak ? 1 : 0 }, gain: { value: o.gain ?? (this.additive ? 0.62 : 1) }, add: { value: this.additive ? 1 : 0 } },
            transparent: true, depthWrite: false, side: THREE.DoubleSide,
            blending: this.additive ? THREE.AdditiveBlending : THREE.NormalBlending,
            vertexShader: `
                attribute vec3 iPos; attribute vec3 iVel; attribute vec4 iCol; attribute vec3 iSize;
                uniform vec2 grid; uniform float mode;
                varying vec2 vUv; varying vec4 vCol;
                void main() {
                    float cell = floor(iSize.z);
                    vec2 at = vec2(mod(cell, grid.x), floor(cell / grid.x));
                    vUv = (at + vec2(position.x + 0.5, 0.5 - position.y)) / grid;
                    vCol = iCol;
                    if (mode > 1.5) {
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(iPos + vec3(position.xy * iSize.xy, 0.0), 1.0);
                        return;
                    }
                    vec4 centre = modelViewMatrix * vec4(iPos, 1.0);
                    vec2 corner = position.xy * iSize.xy;
                    if (mode > 0.5) {
                        vec3 v = (modelViewMatrix * vec4(iVel, 0.0)).xyz;
                        vec2 dir = length(v.xy) > 1e-5 ? normalize(v.xy) : vec2(0.0, 1.0);
                        vec2 across = vec2(-dir.y, dir.x);
                        corner = across * position.x * iSize.x + dir * position.y * (iSize.y + length(v) * 0.2);
                    }
                    centre.xy += corner;
                    gl_Position = projectionMatrix * centre;
                }`,
            fragmentShader: `
                uniform sampler2D map; uniform float gain; uniform float add; varying vec2 vUv; varying vec4 vCol;
                void main() {
                    vec4 t = texture2D(map, vUv);
                    float a = t.a * vCol.a * gain;
                    gl_FragColor = add > 0.5 ? vec4(t.rgb * vCol.rgb * a, 1.0) : vec4(t.rgb * vCol.rgb, a);
                }`,
        }));
        this.mesh.frustumCulled = false;
    }

    /** Spawns `n` particles on the next step, for an emitter the model fires on an event and not by a rate. */
    burst(n) { this.pending += n; }

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
        const base = d.rate.v.length ? sampleTrack(d.rate.t, d.rate.v, (now * 1000) % this.loopMs) : 0;
        this.carry += Math.max(0, base + (r() - 0.5) * d.rateVary) * this.boost * dt + this.pending;
        this.pending = 0;
        const cells = d.rows * d.cols;
        const g = Math.abs(d.gravity.v[0] ?? 0) < BLOW_UP ? d.gravity.v[0] ?? 0 : 9.8;
        let n = 0;
        for (const q of this.p) {
            if (q.age >= 1) {
                if (this.carry < 1) continue;
                this.carry -= 1;
                this.spawn(q);
            }
            const drag = Math.max(0, 1 - d.drag * dt);
            q.vx *= drag; q.vy *= drag; q.vz = q.vz * drag - g * dt;
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
        this.carry = Math.min(this.carry, 4);
        this.mesh.geometry.instanceCount = n;
        this.aPos.needsUpdate = this.aVel.needsUpdate = this.aCol.needsUpdate = this.aSize.needsUpdate = true;
    }
}
