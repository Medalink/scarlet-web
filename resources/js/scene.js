// The scene of a page's head (docs/DESIGN.md, Art and game layer). This entry is a few lines; three.js and the
// scene load as chunks of their own, only once the head is on screen and the browser has WebGL. When either fails the
// stage keeps its size and shows its callouts as a list (data-state="off").

const stage = document.querySelector('[data-scene]');
const scenes = import.meta.glob('./scene/scenes/*.js');

function hasWebGL() {
    const gl = document.createElement('canvas').getContext('webgl2');
    gl?.getExtension('WEBGL_lose_context')?.loseContext();
    return gl !== null;
}

async function start(stage) {
    const { scene, models, texture } = stage.dataset;
    const off = () => {
        stage.querySelector('canvas')?.remove();
        stage.dataset.state = 'off';
    };
    try {
        const load = scenes[`./scene/scenes/${scene}.js`];
        const [{ mountScene }, { placeCallouts }, { default: spec }, response] = await Promise.all([import('./scene/mount.js'), import('./scene/callouts.js'), load(), fetch(models)]);
        if (!response.ok) throw new Error(`${scene}.json ${response.status}`);
        const data = await response.json();
        data.tex = Object.fromEntries(data.textures.map((id) => [id, texture.replace('{id}', id)]));
        await mountScene(stage, data, spec, { onFrame: placeCallouts([...stage.querySelectorAll('.callout')], stage), onLost: off });
        stage.dataset.state = 'on';
    } catch {
        off();
    }
}

if (stage) {
    if (!hasWebGL()) {
        stage.dataset.state = 'off';
    } else {
        const watch = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) return;
            watch.disconnect();
            start(stage);
        }, { rootMargin: '200px' });
        watch.observe(stage);
    }
}
