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

const GAP = 8;

function textBox(node) {
    const range = document.createRange();
    let box = null;
    for (const part of node.children) {
        range.selectNodeContents(part);
        const rect = range.getBoundingClientRect();
        if (rect.width === 0) continue;
        box = box === null ? rect.toJSON() : { left: Math.min(box.left, rect.left), right: Math.max(box.right, rect.right), top: Math.min(box.top, rect.top), bottom: Math.max(box.bottom, rect.bottom) };
    }
    return box;
}

function measureCallouts(callouts) {
    const metrics = new Map();
    for (const node of callouts) {
        const wasHidden = node.hidden;
        node.hidden = false;
        node.style.setProperty('--lift', '0px');
        const box = textBox(node);
        const rect = node.getBoundingClientRect();
        metrics.set(node.dataset.key, {
            w: node.offsetWidth,
            h: node.offsetHeight,
            textH: box ? box.bottom - rect.top : node.offsetHeight,
        });
        node.hidden = wasHidden;
    }
    return metrics;
}

function place(callouts, stage) {
    const byKey = Object.fromEntries(callouts.map((node) => [node.dataset.key, node]));
    let metrics = measureCallouts(callouts);
    const remeasure = () => {
        metrics = measureCallouts(callouts);
    };
    new ResizeObserver(remeasure).observe(stage);
    document.fonts?.ready.then(remeasure);

    return (points, stageW = stage.clientWidth) => {
        const placed = [];
        const writes = [];
        for (const point of points) {
            const node = byKey[point.key];
            const m = metrics.get(point.key);
            if (!node || !m) continue;
            const width = m.w + 8;
            const isLeft = node.dataset.side === 'left' ? point.x >= width : node.classList.contains('left');
            const cx = Math.min(Math.max(point.x, isLeft ? width : 8), isLeft ? stageW - 8 : stageW - width);
            const cy = Math.max(point.y, m.h + 12);
            let lift = 0;
            if (point.shown) {
                const left = isLeft ? cx - m.w : cx;
                const right = left + m.w;
                const top = cy - m.h;
                const bottom = top + m.textH;
                for (let pass = 0; pass < placed.length; pass++) {
                    for (const other of placed) {
                        const apart = right <= other.left || left >= other.right || top - lift >= other.bottom + GAP || bottom - lift <= other.top - GAP;
                        if (!apart) lift = bottom - other.top + GAP;
                    }
                }
                if (top - lift < GAP) lift = 0;
                placed.push({ left, right, top: top - lift, bottom: bottom - lift });
            }
            writes.push([node, !point.shown, isLeft, cx, cy, lift]);
        }
        for (const [node, hide, isLeft, cx, cy, lift] of writes) {
            if (node.hidden !== hide) node.hidden = hide;
            if (node.dataset.side === 'left') node.classList.toggle('left', isLeft);
            node.style.setProperty('--x', cx.toFixed(1) + 'px');
            node.style.setProperty('--y', cy.toFixed(1) + 'px');
            node.style.setProperty('--lift', lift.toFixed(1) + 'px');
        }
    };
}

async function start(stage) {
    const { scene, models, texture } = stage.dataset;
    const off = () => {
        stage.querySelector('canvas')?.remove();
        stage.dataset.state = 'off';
    };
    try {
        const load = scenes[`./scene/scenes/${scene}.js`];
        const [{ mountScene }, { default: spec }, response] = await Promise.all([import('./scene/mount.js'), load(), fetch(models)]);
        if (!response.ok) throw new Error(`${scene}.json ${response.status}`);
        const data = await response.json();
        data.tex = Object.fromEntries(data.textures.map((id) => [id, texture.replace('{id}', id)]));
        await mountScene(stage, data, spec, { onFrame: place([...stage.querySelectorAll('.callout')], stage), onLost: off });
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
