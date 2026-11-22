// The camp scene of /camps (docs/DESIGN.md, Art and game layer). This entry is a few lines; three.js and the scene
// load as their own chunk, only once the head is on screen and the browser has WebGL. When either fails the stage
// keeps its size and shows the three callouts as a list (data-state="off").

const stage = document.querySelector('[data-camp]');

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

function place(callouts, stage) {
    const byKey = Object.fromEntries(callouts.map((node) => [node.dataset.key, node]));
    return (points) => {
        const placed = [];
        for (const point of points) {
            const node = byKey[point.key];
            node.hidden = !point.shown;
            const width = node.offsetWidth + 8;
            if (node.dataset.side === 'left') node.classList.toggle('left', point.x >= width);
            const left = node.classList.contains('left');
            node.style.setProperty('--x', Math.min(Math.max(point.x, left ? width : 8), left ? stage.clientWidth - 8 : stage.clientWidth - width).toFixed(1) + 'px');
            node.style.setProperty('--y', Math.max(point.y, node.offsetHeight + 12).toFixed(1) + 'px');
            node.style.setProperty('--lift', '0px');
            const box = point.shown ? textBox(node) : null;
            if (box === null) continue;
            let lift = 0;
            for (let pass = 0; pass < placed.length; pass++) {
                for (const other of placed) {
                    const apart = box.right <= other.left || box.left >= other.right || box.top - lift >= other.bottom + GAP || box.bottom - lift <= other.top - GAP;
                    if (!apart) lift = box.bottom - other.top + GAP;
                }
            }
            if (box.top - lift < stage.getBoundingClientRect().top + GAP) lift = 0;
            node.style.setProperty('--lift', lift.toFixed(1) + 'px');
            placed.push({ left: box.left, right: box.right, top: box.top - lift, bottom: box.bottom - lift });
        }
    };
}

async function start(stage) {
    const { models, texture } = JSON.parse(stage.dataset.camp);
    const off = () => {
        stage.querySelector('canvas')?.remove();
        stage.dataset.state = 'off';
    };
    try {
        const [{ mountCamp }, response] = await Promise.all([import('./camps/scene.js'), fetch(models)]);
        if (!response.ok) throw new Error(`camp.json ${response.status}`);
        const data = await response.json();
        data.tex = Object.fromEntries(data.textures.map((id) => [id, texture.replace('{id}', id)]));
        const onFrame = place([...stage.querySelectorAll('.callout')], stage);
        await mountCamp(stage, data, {
            offset: (width) => (width > 820 ? 0.26 : 0),
            onFrame,
            onLost: off,
        });
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
