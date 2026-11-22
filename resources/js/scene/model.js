import * as THREE from 'three';

const UNLIT = 0x1;
const TWO_SIDED = 0x4;
const BILLBOARD = 0x8;

/** Loads every texture of a scene from `{ file id: url }`. */
export function loadTextures(sources) {
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

/**
 * The batches of a model from the art tool as meshes, with the game's blend and material flags. A batch on a bone that
 * faces the camera is moved to its pivot and tagged `userData.billboard`; the setting turns it each frame.
 */
export function buildModel(model, tex) {
    const group = new THREE.Group();
    for (const b of model.batches) {
        const geo = new THREE.BufferGeometry();
        const facing = (b.boneFlags & BILLBOARD) !== 0;
        const position = new Float32Array(b.position);
        if (facing) for (let i = 0; i < position.length; i += 3) for (let k = 0; k < 3; k++) position[i + k] -= b.pivot[k];
        geo.setAttribute('position', new THREE.BufferAttribute(position, 3));
        geo.setAttribute('normal', new THREE.Float32BufferAttribute(b.normal, 3));
        geo.setAttribute('uv', new THREE.Float32BufferAttribute(b.uv, 2));
        geo.setIndex(b.index);
        const map = tex[b.textures[0]];
        const unlit = (b.flags & UNLIT) !== 0;
        const side = (b.flags & TWO_SIDED) !== 0 ? THREE.DoubleSide : THREE.FrontSide;
        const alphaTest = b.blend === 1 ? 0.5 : 0;
        let mat;
        if (b.blend >= 3) mat = new THREE.MeshBasicMaterial({ map, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide, fog: false });
        else if (b.blend === 2) mat = new THREE.MeshLambertMaterial({ map, side, transparent: true, depthWrite: false });
        else if (unlit) mat = new THREE.MeshBasicMaterial({ map, side, alphaTest });
        else mat = new THREE.MeshLambertMaterial({ map, side, alphaTest });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData.blend = b.blend;
        if (facing) {
            mesh.position.fromArray(b.pivot);
            mesh.userData.billboard = true;
        }
        if (b.blend < 2) { mesh.castShadow = !unlit; mesh.receiveShadow = !unlit; }
        group.add(mesh);
    }
    return group;
}
