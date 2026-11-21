// Chart and map module. Every chart reads a JSON spec from its data-chart attribute, which the page's
// Blade component writes, and draws the marks docs/comps/assets/comp.js draws from invented numbers.
// All text a chart shows (axis labels, readout lines, cell titles) comes in the spec already formatted
// by App\View\Format, so numbers are formatted in one place. No chart library.
import './site/map.js';

const NS = 'http://www.w3.org/2000/svg';
const MAP_CELL = 83;
const HEAT = ['#4b1626', '#861a34', '#cf1f45', '#ff5d7e'];

const svgEl = (name, attrs, text) => {
    const node = document.createElementNS(NS, name);
    for (const key in attrs) node.setAttribute(key, attrs[key]);
    if (text != null) node.textContent = text;
    return node;
};
const htmlEl = (name, className, text) => {
    const node = document.createElement(name);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
};
const clamp = (value, lo, hi) => Math.max(lo, Math.min(hi, value));

// A readout line: a bold lead and the rest, set as text so no markup from data is ever parsed.
function say(spec, index) {
    const out = spec.out ? document.getElementById(spec.out) : null;
    const line = spec.say?.[index];
    if (!out || !line) return;
    out.replaceChildren(htmlEl('b', '', line[0]), document.createTextNode(line[1] ?? ''));
}

// Pointer and keyboard reading, the way static-site/assets/character.js reads the XP bars.
// count: how many readable steps; at(event): the step under the pointer; show(index, live): paint it.
function reader(target, spec, { count, at, show }, signal) {
    let pinned = spec.rest ?? count - 1;
    let shown = pinned;
    const read = (index, live) => {
        shown = index;
        show(index, live);
        say(spec, index);
    };
    target.addEventListener('pointermove', (e) => { if (e.pointerType === 'mouse') read(at(e), true); }, { signal });
    target.addEventListener('pointerleave', () => read(pinned, false), { signal });
    target.addEventListener('click', (e) => { pinned = at(e); read(pinned, true); }, { signal });
    target.addEventListener('blur', () => read(pinned, false), { signal });
    target.addEventListener('keydown', (e) => {
        const step = e.key === 'ArrowLeft' ? -1 : e.key === 'ArrowRight' ? 1 : 0;
        if (!step) return;
        e.preventDefault();
        pinned = clamp(shown + step, 0, count - 1);
        read(pinned, true);
    }, { signal });
    read(pinned, false);
}

// The plot frame. Wide containers keep the design size the comps draw at, so a page matches its comp.
// Narrow ones draw in their own pixels, so axis labels stay readable on a phone.
function plotBox(node, spec) {
    const design = { w: spec.w || 760, h: spec.h || 250 };
    const width = node.getBoundingClientRect().width || design.w;
    if (width / design.w >= 0.85) return design;
    return { w: Math.round(width), h: Math.max(170, Math.round(width * (design.h / design.w) * 1.3)) };
}

function frame(svg, spec) {
    const { w: W, h: H } = plotBox(svg, spec);
    const L = 42, B = 22, T = 8, R = 6;
    svg.replaceChildren();
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    const x = (v) => L + ((v - spec.x0) / (spec.x1 - spec.x0)) * (W - L - R);
    const y = (v) => H - B - (v / spec.y1) * (H - B - T);
    for (const [t, label] of spec.yt) {
        svg.append(svgEl('line', { class: t ? 'grid' : 'axis', x1: L, x2: W - R, y1: y(t), y2: y(t) }));
        svg.append(svgEl('text', { x: L - 8, y: y(t) + 3.5, 'text-anchor': 'end' }, label));
    }
    for (const [t, label] of spec.xt) svg.append(svgEl('text', { x: x(t), y: H - 5, 'text-anchor': 'middle' }, label));
    return { x, y, W, H, L, R, T, B };
}

// Makes a plot readable: focusable, with one step per readable x position.
function readable(svg, spec, f, count, xOf, show, signal) {
    // Only a plot with a readout line can be stepped through; the others show a tooltip on their bars.
    if (!spec.say || !spec.out) return;
    svg.setAttribute('tabindex', '0');
    const at = (e) => {
        const r = svg.getBoundingClientRect();
        const ux = ((e.clientX - r.left) / r.width) * f.W;
        let best = 0;
        for (let i = 1; i < count; i++) if (Math.abs(xOf(i) - ux) < Math.abs(xOf(best) - ux)) best = i;
        return best;
    };
    reader(svg, spec, { count, at, show }, signal);
}

const draw = {
    line(svg, spec, signal) {
        const f = frame(svg, spec);
        for (const s of spec.series) {
            if (s.band) {
                const up = s.band.map(([a, , hi]) => `${f.x(a)},${f.y(hi)}`);
                const dn = s.band.map(([a, lo]) => `${f.x(a)},${f.y(lo)}`).reverse();
                svg.append(svgEl('polygon', { class: 'band', points: up.concat(dn).join(' ') }));
            }
            svg.append(svgEl('polyline', { class: 'ln ' + s.cls, points: s.pts.map(([a, b]) => `${f.x(a)},${f.y(b)}`).join(' ') }));
            if (s.mark != null) {
                const p = s.pts[s.mark];
                svg.append(svgEl('circle', { class: 'dot', cx: f.x(p[0]), cy: f.y(p[1]), r: 4.5 }));
            }
        }
        const lead = spec.series[spec.lead ?? 0].pts;
        const cursor = svgEl('line', { class: 'cursor', y1: f.T, y2: f.H - f.B, visibility: 'hidden' });
        const follow = spec.series.map(() => svgEl('circle', { class: 'dot', r: 4, visibility: 'hidden' }));
        svg.append(cursor, ...follow);
        readable(svg, spec, f, lead.length, (i) => f.x(lead[i][0]), (i, live) => {
            const shown = live ? 'visible' : 'hidden';
            cursor.setAttribute('visibility', shown);
            cursor.setAttribute('x1', f.x(lead[i][0]));
            cursor.setAttribute('x2', f.x(lead[i][0]));
            follow.forEach((dot, k) => {
                const p = spec.series[k].pts.find((q) => q[0] === lead[i][0]);
                dot.setAttribute('visibility', p ? shown : 'hidden');
                if (p) { dot.setAttribute('cx', f.x(p[0])); dot.setAttribute('cy', f.y(p[1])); }
            });
        }, signal);
    },

    bars(svg, spec, signal) {
        const f = frame(svg, spec);
        const bw = ((f.W - f.L - 6) / (spec.x1 - spec.x0)) * 0.72;
        const rects = [];
        spec.data.forEach(([a, v, lo, hi, mark], i) => {
            const rect = svgEl('rect', { class: 'bar ' + (spec.cls || ''), x: f.x(a) - bw / 2, width: bw, y: f.y(v), height: Math.max(0, f.y(0) - f.y(v)), rx: 1.5 });
            const line = spec.say?.[i];
            if (line) rect.append(svgEl('title', {}, line[0] + (line[1] ?? '')));
            svg.append(rect);
            rects.push(rect);
            if (hi != null) svg.append(svgEl('line', { class: 'whisk', x1: f.x(a), x2: f.x(a), y1: f.y(lo), y2: f.y(hi) }));
            if (mark != null) svg.append(svgEl('line', { class: 'tick', x1: f.x(a) - bw / 2 - 1.5, x2: f.x(a) + bw / 2 + 1.5, y1: f.y(mark), y2: f.y(mark) }));
        });
        readable(svg, spec, f, spec.data.length, (i) => f.x(spec.data[i][0]), (i, live) => {
            rects.forEach((rect, k) => rect.classList.toggle('dim', live && k !== i));
        }, signal);
    },

    stack(svg, spec, signal) {
        const f = frame(svg, spec);
        const bw = ((f.W - f.L - 6) / (spec.x1 - spec.x0)) * 0.72;
        const groups = spec.data.map(([a, parts], i) => {
            const group = svgEl('g');
            let base = 0;
            parts.forEach((value, k) => {
                if (value <= 0) return;
                group.append(svgEl('rect', { class: 'stk ' + spec.cls[k], x: f.x(a) - bw / 2, width: bw, y: f.y(base + value), height: Math.max(0, f.y(base) - f.y(base + value)) }));
                base += value;
            });
            const line = spec.say?.[i];
            if (line) group.append(svgEl('title', {}, line[0] + (line[1] ?? '')));
            svg.append(group);
            return group;
        });
        readable(svg, spec, f, spec.data.length, (i) => f.x(spec.data[i][0]), (i, live) => {
            groups.forEach((group, k) => group.classList.toggle('dim', live && k !== i));
        }, signal);
    },

    // Stacked share bars in the page's own .xp-bars markup. rows: [kill, dungeon, quest, explore, other] as shares.
    shares(box, spec, signal) {
        const cls = ['c-kill', 'c-dungeon', 'c-quest', 'c-explore', 'c-other'];
        box.replaceChildren();
        for (const row of spec.rows) {
            const bar = htmlEl('div', 'xp-bar');
            row.forEach((share, i) => {
                const part = htmlEl('i', cls[i]);
                part.style.height = share * 100 + '%';
                bar.append(part);
            });
            box.append(bar);
        }
        if (!spec.say) return;
        const at = (e) => {
            const r = box.getBoundingClientRect();
            return clamp(Math.floor(((e.clientX - r.left) / r.width) * spec.rows.length), 0, spec.rows.length - 1);
        };
        reader(box, spec, {
            count: spec.rows.length,
            at,
            show: (i, live) => {
                [...box.children].forEach((bar, k) => bar.classList.toggle('on', k === i));
                box.classList.toggle('has-on', live);
            },
        }, signal);
    },

    // Hour by weekday grid. levels[day][hour] is 0 to 4.
    hours(box, spec, signal) {
        box.replaceChildren(htmlEl('span'));
        for (let h = 0; h < 24; h++) box.append(htmlEl('span', 'h', h % 3 ? '' : String(h)));
        spec.days.forEach((name, d) => {
            box.append(htmlEl('span', '', name));
            for (let h = 0; h < 24; h++) {
                const cell = htmlEl('i');
                cell.dataset.l = spec.levels[d][h];
                const line = spec.say?.[d * 24 + h];
                if (line) cell.title = line[0] + (line[1] ?? '');
                box.append(cell);
            }
        });
        if (!spec.say) return;
        const cells = [...box.querySelectorAll('i')];
        box.addEventListener('pointerover', (e) => {
            const i = cells.indexOf(e.target.closest('i'));
            if (i >= 0) say(spec, i);
        }, { signal });
    },

    // Play time by day: a column per week from Monday. days: [[level 0 to 4, hidden]], months: one label per week.
    days(box, spec, signal) {
        const head = htmlEl('div', 'heat-months');
        head.setAttribute('aria-hidden', 'true');
        head.append(htmlEl('span'));
        for (const m of spec.months) head.append(htmlEl('span', '', m));
        const grid = htmlEl('div', 'heat-cells');
        for (const name of ['Mon', '', 'Wed', '', 'Fri', '', '']) grid.append(htmlEl('span', '', name));
        spec.days.forEach(([level, off], i) => {
            const cell = htmlEl('i', off ? 'off' : '');
            cell.dataset.i = i;
            cell.dataset.l = level;
            grid.append(cell);
        });
        box.replaceChildren(head, grid);
        if (!spec.say) return;
        const pick = (e) => {
            const cell = e.target.closest('i[data-i]');
            if (!cell) return;
            box.querySelector('i.on')?.classList.remove('on');
            cell.classList.add('on');
            say(spec, Number(cell.dataset.i));
        };
        box.addEventListener('pointerover', pick, { signal });
        box.addEventListener('click', pick, { signal });
    },

    // The survey map with heat cells and death crosses. cells: [[cell x, cell y, level 1 to 4]], deaths: [[x, y]].
    map(canvas, spec) {
        const M = window.ScarletMap;
        const r = canvas.getBoundingClientRect();
        if (!r.width || !r.height) return;
        const view = M.fit(spec.box || { x0: 1500, y0: 500, x1: 9800, y1: 6100 }, { x: 0, y: 0, w: r.width, h: r.height });
        M.terrain(canvas, view, r.width, r.height, spec.places || []);
        const ctx = canvas.getContext('2d');
        const size = spec.cell || MAP_CELL;
        ctx.globalAlpha = 0.9;
        for (const [cx, cy, level] of spec.cells || []) {
            const x = cx * size, y = cy * size;
            ctx.fillStyle = HEAT[clamp(level, 1, 4) - 1];
            ctx.fillRect(M.px(view, x) + 0.5, M.py(view, y) + 0.5, M.px(view, x + size) - M.px(view, x) - 1, M.py(view, y + size) - M.py(view, y) - 1);
        }
        ctx.globalAlpha = 1;
        for (const [x, y] of spec.deaths || []) M.mark(ctx, 'death', M.px(view, x), M.py(view, y));
    },
};

function mount(node) {
    let spec;
    try {
        spec = JSON.parse(node.dataset.chart);
    } catch {
        return;
    }
    const paint = draw[spec.kind];
    if (!paint) return;
    let listeners = new AbortController();
    const render = () => {
        listeners.abort();
        listeners = new AbortController();
        paint(node, spec, listeners.signal);
    };
    render();
    if (spec.kind === 'map') document.fonts?.ready.then(render);
    if (spec.kind !== 'line' && spec.kind !== 'bars' && spec.kind !== 'stack' && spec.kind !== 'map') return;
    // Svg plots and maps are laid out for their width, so they draw again when it changes.
    let width = node.getBoundingClientRect().width;
    new ResizeObserver(() => {
        const next = node.getBoundingClientRect().width;
        if (Math.abs(next - width) < 1) return;
        width = next;
        render();
    }).observe(node);
}

document.querySelectorAll('[data-chart]').forEach(mount);
