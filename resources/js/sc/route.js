import { svg, text } from './dom.js';
import { DEATH_GLYPH } from './strip.js';

function grid(canvas, W, H) {
    for (let gx = 40; gx < W; gx += 40) canvas.append(svg('rect', { x: gx, y: 0, width: 1, height: H, fill: 'rgba(255,255,255,0.035)' }));
    for (let gy = 40; gy < H; gy += 40) canvas.append(svg('rect', { x: 0, y: gy, width: W, height: 1, fill: 'rgba(255,255,255,0.035)' }));
}

function trail(canvas, spec, { scale, left, top, sheetH }) {
    const cells = spec.cells;
    const count = cells.length / 2;
    const size = Math.max(1.6, spec.cell * scale);
    const chunks = 6;
    const per = Math.ceil(count / chunks);
    for (let c = 0; c < chunks; c++) {
        const group = svg('g', { fill: '#ff2d55', opacity: 0.32 + (c / (chunks - 1)) * 0.68 });
        for (let i = c * per; i < Math.min(count, (c + 1) * per); i++) {
            group.append(svg('rect', { x: left + cells[i * 2] * scale - size / 2, y: top + (sheetH - cells[i * 2 + 1]) * scale - size / 2, width: size, height: size }));
        }
        canvas.append(group);
    }
    for (const [dx, dy] of spec.deaths || []) {
        const px = left + dx * scale;
        const py = top + (sheetH - dy) * scale;
        canvas.append(svg('circle', { cx: px, cy: py, r: 8.5, fill: '#141416', stroke: 'rgba(255,255,255,0.3)' }));
        const glyph = svg('g', { transform: `translate(${px - 6.6} ${py - 6.8}) scale(0.55)`, fill: 'none', stroke: '#f0f1f3', 'stroke-width': 2.2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
        glyph.innerHTML = DEATH_GLYPH;
        canvas.append(glyph);
    }
    for (const [label, px, py] of spec.places || []) {
        canvas.append(text({ x: left + px * scale, y: top + (sheetH - py) * scale, 'font-style': 'italic' }, label));
    }
}

export function route(element) {
    const spec = JSON.parse(element.dataset.route);
    const W = element.clientWidth || 400;
    const H = +element.dataset.h || element.clientHeight || 260;
    const canvas = svg('svg', { viewBox: `0 0 ${W} ${H}`, width: W, height: H, class: 'sc-svg' });
    grid(canvas, W, H);
    const pad = 22;
    const wide = spec.aspect >= 1;
    const sheetW = wide ? 1000 : 1000 * spec.aspect;
    const sheetH = wide ? 1000 / spec.aspect : 1000;
    const scale = Math.min((W - 2 * pad) / sheetW, (H - 2 * pad) / sheetH);
    const left = (W - sheetW * scale) / 2;
    const top = (H - sheetH * scale) / 2;
    if (spec.cells) {
        trail(canvas, spec, { scale, left, top, sheetH });
        element.replaceChildren(canvas);
        return;
    }
    const points = [];
    for (let i = 0; i < spec.points.length; i += 2) points.push([left + spec.points[i] * scale, top + (sheetH - spec.points[i + 1]) * scale]);
    const n = points.length;
    if (n < 2) {
        element.replaceChildren(canvas);
        return;
    }
    const air = spec.air || [];
    const cut = new Set(spec.breaks || []);
    const chunks = Math.min(6, n - 1);
    const per = Math.ceil(n / chunks);
    const stroke = (list, attrs) => canvas.append(svg('polyline', { points: list.map((p) => p.join(',')).join(' '), fill: 'none', 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round', ...attrs }));
    for (let c = 0; c < chunks; c++) {
        let from = Math.max(0, c * per - 1);
        const to = Math.min(n, (c + 1) * per);
        const opacity = 0.38 + (chunks > 1 ? (c / (chunks - 1)) * 0.62 : 0.62);
        while (from < to - 1) {
            if (cut.has(from + 1)) {
                from++;
                continue;
            }
            let end = from + 1;
            const flying = !!air[from + 1];
            while (end < to - 1 && !cut.has(end + 1) && !!air[end + 1] === flying) end++;
            stroke(points.slice(from, end + 1), flying ? { stroke: '#9a7df2', 'stroke-dasharray': '5 5' } : { stroke: '#ff2d55', opacity });
            from = end;
        }
    }
    const at = (f) => points[Math.min(n - 1, Math.max(0, Math.round(f * (n - 1))))];
    const [sx, sy] = points[0];
    canvas.append(svg('circle', { cx: sx, cy: sy, r: 4.5, fill: '#141416', stroke: '#f0f1f3', 'stroke-width': 1.8 }));
    for (const mark of spec.marks || []) {
        const [px, py] = at(mark.f);
        if (mark.k === 'level') {
            canvas.append(svg('rect', { x: px - 4.5, y: py - 4.5, width: 9, height: 9, fill: '#f0f1f3', stroke: '#141416', 'stroke-width': 1.5, transform: `rotate(45 ${px} ${py})` }));
            if (mark.t) canvas.append(text({ x: px + 10, y: py + 4, class: 'strong', 'paint-order': 'stroke', stroke: '#1b1b1f', 'stroke-width': 3 }, mark.t));
        }
        if (mark.k === 'death') {
            canvas.append(svg('circle', { cx: px, cy: py, r: 8.5, fill: '#141416', stroke: 'rgba(255,255,255,0.3)' }));
            const glyph = svg('g', { transform: `translate(${px - 6.6} ${py - 6.8}) scale(0.55)`, fill: 'none', stroke: '#f0f1f3', 'stroke-width': 2.2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
            glyph.innerHTML = DEATH_GLYPH;
            canvas.append(glyph);
        }
    }
    const [ex, ey] = points[n - 1];
    canvas.append(svg('rect', { x: ex - 5.5, y: ey - 5.5, width: 11, height: 11, fill: '#ff2d55', stroke: '#141416', 'stroke-width': 2, transform: `rotate(45 ${ex} ${ey})` }));
    element.replaceChildren(canvas);
}
