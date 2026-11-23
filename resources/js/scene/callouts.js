const EDGE = 12;
const GAP = 8;
const STEP = 4;
const PASSES = 4;
const SHIFT_COST = 400;
const OFF_STAGE = 1e6;
const SOLID_PAD = 4;
const POLE_PAD = 3;
const SOFT = 0.0001;
const LIFT_COST = 0.001;
const SIDE_COST = 0.5;
const WIDTH = 60;
const BUDGET = 3000;

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

function measure(callouts) {
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
            flip: node.dataset.side !== undefined,
            side: node.dataset.side ?? (node.classList.contains('left') ? 'left' : 'right'),
        });
        node.hidden = wasHidden;
    }
    return metrics;
}

function overlap(a, b, pad) {
    const w = Math.min(a.right, b.right + pad) - Math.max(a.left, b.left - pad);
    const h = Math.min(a.bottom, b.bottom + pad) - Math.max(a.top, b.top - pad);
    return w > 0 && h > 0 ? w * h : 0;
}

function shape(item, side, lift, stageW) {
    const low = side === 'left' ? item.w + EDGE : EDGE;
    const high = side === 'left' ? stageW - EDGE : stageW - EDGE - item.w;
    const cx = Math.max(low, Math.min(high, item.x));
    const left = side === 'left' ? cx - item.w : cx;
    const top = item.cy - item.h - lift;
    const text = { left, right: left + item.w, top, bottom: top + item.textH };
    return { side, lift, cx, shift: Math.abs(cx - item.x), text, pole: { left: cx - 1, right: cx + 1, top: text.bottom, bottom: item.cy } };
}

function own(item, at, solids) {
    let hard = at.shift * SHIFT_COST;
    if (at.text.top < EDGE) hard += OFF_STAGE + (EDGE - at.text.top);
    let soft = 0;
    for (const solid of solids) {
        hard += overlap(at.text, solid, SOLID_PAD);
        if (solid.key !== item.key) soft += overlap(at.pole, solid, POLE_PAD);
    }
    return { hard, soft };
}

function between(a, b) {
    return overlap(a.text, b.text, GAP) + overlap(a.text, b.pole, POLE_PAD) + overlap(a.pole, b.text, POLE_PAD);
}

function cost(item, at, solids, others) {
    const { hard, soft } = own(item, at, solids);
    return others.reduce((sum, other) => sum + between(at, other), hard + soft * SOFT * 100);
}

function candidates(item, solids, stageW, from) {
    const top = Math.max(0, item.cy - item.h - EDGE);
    const sides = item.flip ? ['left', 'right'] : [item.side];
    const list = [];
    for (let lift = 0; lift <= top; lift += STEP) {
        for (const side of sides) {
            const at = shape(item, side, lift, stageW);
            Object.assign(at, own(item, at, solids));
            at.score = at.soft * SOFT + Math.abs(lift - from.lift) * LIFT_COST + (side === from.side ? 0 : SIDE_COST) + (side === item.side ? 0 : 0.01);
            list.push(at);
        }
    }
    return list.sort((a, b) => Number(a.hard > 0) - Number(b.hard > 0) || (a.hard > 0 ? a.hard - b.hard : a.score - b.score));
}

function solve(items, solids, stageW, from) {
    const domains = items.map((item) => candidates(item, solids, stageW, from.get(item.key)).filter((at) => at.hard === 0).slice(0, WIDTH));
    if (domains.some((domain) => domain.length === 0)) return null;
    const picked = new Map();
    let nodes = 0;
    const walk = (open) => {
        if (open.size === 0) return true;
        const [index] = [...open.keys()].sort((a, b) => open.get(a).length - open.get(b).length);
        const rest = new Map(open);
        rest.delete(index);
        for (const at of open.get(index)) {
            if (++nodes > BUDGET) return false;
            const next = new Map([...rest].map(([other, domain]) => [other, domain.filter((c) => between(c, at) === 0)]));
            if ([...next.values()].some((domain) => domain.length === 0)) continue;
            picked.set(index, at);
            if (walk(next)) return true;
        }
        picked.delete(index);
        return false;
    };
    if (!walk(new Map(domains.map((domain, i) => [i, domain])))) return null;
    return new Map([...picked].map(([index, at]) => [items[index].key, at]));
}

function relax(items, chosen, solids, stageW, from) {
    for (let pass = 0; pass < PASSES; pass++) {
        let moved = false;
        for (const item of items) {
            const now = chosen.get(item.key);
            const others = items.filter((other) => other !== item).map((other) => chosen.get(other.key));
            if (cost(item, now, solids, others) < 1) continue;
            const best = candidates(item, solids, stageW, from.get(item.key)).reduce((pick, at) => {
                const total = others.reduce((sum, other) => sum + between(at, other), at.hard + at.soft * SOFT * 100);
                return pick === null || total < pick.total ? { at, total } : pick;
            }, null);
            if (best.at.side !== now.side || best.at.lift !== now.lift) moved = true;
            chosen.set(item.key, best.at);
        }
        if (!moved) break;
    }
    return chosen;
}

function settled(items, chosen, solids) {
    return items.every((item) => {
        const at = chosen.get(item.key);
        return own(item, at, solids).hard === 0 && items.every((other) => other === item || between(at, chosen.get(other.key)) === 0);
    });
}

/**
 * Arranges the callouts of a scene: each stands on its anchor and takes the side and the pole length at which its text
 * touches no model, no other callout and no edge of the stage, and its pole crosses as few models as it can. A layout
 * stays while it holds, and when it stops holding the callouts move together to the nearest one that does. With no free
 * layout, each callout takes the place with the least overlap. `solids` are the screen rectangles of the scene's models,
 * `{ key, left, top, right, bottom }`.
 */
export function placeCallouts(callouts, stage) {
    const byKey = Object.fromEntries(callouts.map((node) => [node.dataset.key, node]));
    let metrics = measure(callouts);
    const remeasure = () => {
        metrics = measure(callouts);
    };
    new ResizeObserver(remeasure).observe(stage);
    document.fonts?.ready.then(remeasure);
    const previous = new Map();
    let arranged = false;

    return (points, stageW = stage.clientWidth, solids = []) => {
        const items = [];
        for (const point of points) {
            const m = metrics.get(point.key);
            if (!byKey[point.key] || !m) continue;
            items.push({ key: point.key, node: byKey[point.key], shown: point.shown, x: point.x, cy: Math.max(point.y, m.h + EDGE), ...m });
        }
        const shown = items.filter((item) => item.shown);
        const from = new Map(shown.map((item) => [item.key, previous.get(item.key) ?? { side: item.side, lift: 0 }]));
        let chosen = new Map(shown.map((item) => {
            const { side, lift } = from.get(item.key);
            return [item.key, shape(item, side, Math.min(lift, Math.max(0, item.cy - item.h - EDGE)), stageW)];
        }));
        if (!arranged || !settled(shown, chosen, solids)) {
            chosen = solve(shown, solids, stageW, from) ?? relax(shown, chosen, solids, stageW, from);
            arranged = shown.length > 0;
        }
        for (const item of items) {
            const at = chosen.get(item.key);
            if (at) previous.set(item.key, { side: at.side, lift: at.lift });
            const hide = at === undefined;
            if (item.node.hidden !== hide) item.node.hidden = hide;
            if (hide) continue;
            if (item.flip) item.node.classList.toggle('left', at.side === 'left');
            item.node.style.setProperty('--x', at.cx.toFixed(1) + 'px');
            item.node.style.setProperty('--y', item.cy.toFixed(1) + 'px');
            item.node.style.setProperty('--lift', at.lift.toFixed(1) + 'px');
        }    };
}
