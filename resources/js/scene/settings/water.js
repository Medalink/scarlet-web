import * as THREE from 'three';

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
    map.repeat.set(14, 7);
    map.colorSpace = THREE.SRGBColorSpace;
    return map;
}

function buildBank(rand, bank) {
    const R = bank.radius, top = bank.height;
    const geo = new THREE.SphereGeometry(R, 160, 96, 0, Math.PI * 2, 0, 0.5);
    const pos = geo.attributes.position, shade = new Float32Array(pos.count * 3);
    for (let i = 0; i < pos.count; i++) {
        const h = pos.getY(i) - (R - top), k = Math.min(1, Math.max(0, h / 0.3)), v = 0.3 + 0.7 * k * k * (3 - 2 * k);
        shade.set([v, v, v * (1.08 - 0.08 * k)], i * 3);
    }
    geo.setAttribute('color', new THREE.BufferAttribute(shade, 3));
    const mesh = new THREE.Mesh(geo, new THREE.MeshLambertMaterial({ map: dirt(rand, '#6a6252'), vertexColors: true }));
    mesh.position.set(bank.x, top - R, bank.z);
    mesh.receiveShadow = true;
    const lift = (x, z) => Math.sqrt(Math.max(0, R * R - (x - bank.x) ** 2 - (z - bank.z) ** 2)) - (R - top);
    return { mesh, lift };
}

function buildWater(moonDir, sheet) {
    const mat = new THREE.ShaderMaterial({
        transparent: true, depthWrite: false,
        uniforms: { time: { value: 0 }, eye: { value: new THREE.Vector3() }, moon: { value: moonDir.clone().normalize() }, sheet: { value: new THREE.Color(sheet) }, bob: { value: new THREE.Vector3() } },
        vertexShader: `varying vec3 vWorld; void main() { vec4 w = modelMatrix * vec4(position, 1.0); vWorld = w.xyz; gl_Position = projectionMatrix * viewMatrix * w; }`,
        fragmentShader: `
            uniform float time; uniform vec3 eye; uniform vec3 moon; uniform vec3 sheet; uniform vec3 bob;
            varying vec3 vWorld;
            vec2 swell(vec2 p, vec2 dir, float freq, float speed, float amp) {
                float ph = dot(p, dir) * freq + time * speed;
                return dir * cos(ph) * amp * freq;
            }
            void main() {
                vec2 p = vWorld.xz;
                vec2 g = swell(p, vec2(0.8, 0.6), 1.7, 0.9, 0.022) + swell(p, vec2(-0.5, 0.86), 2.9, 1.3, 0.014)
                    + swell(p, vec2(0.2, -0.98), 5.3, 1.9, 0.007) + swell(p, vec2(-0.93, -0.36), 9.1, 2.6, 0.004)
                    + swell(p, vec2(0.6, 0.8), 17.0, 3.4, 0.0016);
                float d = distance(p, bob.xy);
                g += normalize(p - bob.xy + 1e-4) * cos(d * 9.0 - time * 5.0) * 0.05 * bob.z * exp(-d * 0.9);
                vec3 n = normalize(vec3(-g.x, 1.0, -g.y));
                vec3 v = normalize(eye - vWorld);
                float fres = pow(1.0 - max(dot(n, v), 0.0), 4.0);
                vec3 h = normalize(moon + v);
                float glint = pow(max(dot(n, h), 0.0), 520.0) * 3.2 + pow(max(dot(n, h), 0.0), 60.0) * 0.16;
                vec3 deep = vec3(0.016, 0.034, 0.058), sky = vec3(0.12, 0.17, 0.27);
                vec3 col = mix(deep, sky, fres) + vec3(0.74, 0.84, 1.0) * glint;
                float far = smoothstep(13.0, 33.0, distance(eye, vWorld));
                gl_FragColor = vec4(mix(col, sheet, far), mix(0.86, 1.0, max(fres, far)));
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }`,
    });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(160, 160), mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.renderOrder = 1;
    return { mesh, mat };
}

/**
 * Still water under the moon with a low bank standing out of it: a few crossing swells, the moon's glitter on them, a
 * ring spreading from the float, the sheet's own colour far out. Props with `on: 'bank'` stand on the bank.
 * `config` is `{ moon: { from, power, fill }, bank: { at, height, radius } }`.
 */
export function water(config, { scene, rand, spot, sheet }) {
    const moonDir = new THREE.Vector3(...config.moon.from);
    const bankAt = spot(config.bank.at);
    const bank = buildBank(rand, { x: bankAt.x, z: bankAt.z, height: config.bank.height, radius: config.bank.radius });
    const sea = buildWater(moonDir, sheet);
    scene.add(bank.mesh, sea.mesh);

    scene.add(new THREE.HemisphereLight(0x7088c0, 0x15171c, config.moon.fill));
    const moon = new THREE.DirectionalLight(0x9db4e6, config.moon.power);
    moon.position.copy(moonDir).multiplyScalar(24);
    moon.target.position.set(bankAt.x * 0.5, 0, bankAt.z * 0.5);
    moon.castShadow = true;
    moon.shadow.mapSize.set(2048, 2048);
    moon.shadow.bias = -0.0012;
    moon.shadow.radius = 4;
    Object.assign(moon.shadow.camera, { left: -13, right: 13, top: 13, bottom: -13, near: 1, far: 60 });
    moon.shadow.camera.updateProjectionMatrix();
    scene.add(moon, moon.target);

    return {
        lift: (p) => (p.on === 'bank' ? bank.lift(p.x, p.z) : 0),
        step(clock, eye, ripple) {
            sea.mat.uniforms.time.value = clock;
            sea.mat.uniforms.eye.value.copy(eye);
            if (ripple) sea.mat.uniforms.bob.value.set(ripple.x, ripple.z, ripple.strength);
        },
    };
}
