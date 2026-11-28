import { fmt, gridLines, svg, text } from './dom.js';
import { tip } from './tip.js';

export function days(element) {
    const spec = JSON.parse(element.dataset.days);
    const width = element.clientWidth || 560;
    const height = +element.dataset.h || 200;
    const left = 34;
    const bottom = height - 22;
    const top = 8;
    const max = spec.ymax || Math.max(...spec.rows.map((row) => row[1].reduce((sum, value) => sum + value, 0)));
    const bar = (width - left - 4) / spec.rows.length;
    const y = (value) => bottom - (value / max) * (bottom - top);
    const canvas = svg('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'sc-svg' });
    gridLines(canvas, left, width, 2, y, { max, format: (value, top) => Math.round(value * 10) / 10 + (top ? spec.unit || '' : '') });
    spec.rows.forEach(([label, values], i) => {
        const x0 = left + i * bar + 1.5;
        let base = 0;
        values.forEach((value, s) => {
            if (!value) return;
            const rect = svg('rect', { x: x0, y: y(base + value), width: bar - 3, height: Math.max(1, y(base) - y(base + value) - (base ? 2 : 0)) });
            rect.style.fill = `var(${spec.series[s][1]})`;
            rect.dataset.tip = `${label}|${spec.series[s][0]} ${value}${spec.unit || ''}`;
            canvas.append(rect);
            base += value;
        });
        const last = i === spec.rows.length - 1;
        if ((i % (spec.every || 7) === 0 && i < spec.rows.length - 4) || last) {
            canvas.append(text({ x: x0 + (bar - 3) / 2, y: height - 5, 'text-anchor': last ? 'end' : 'middle' }, label));
        }
    });
    element.replaceChildren(canvas);
}

export function area(element) {
    const spec = JSON.parse(element.dataset.area);
    const width = element.clientWidth || 560;
    const height = +element.dataset.h || 200;
    const left = 38;
    const right = width - 10;
    const top = 10;
    const bottom = height - 22;
    const n = spec.points.length;
    const max = spec.ymax || Math.max(...spec.points);
    const x = (i) => left + (i / (n - 1)) * (right - left);
    const y = (value) => bottom - (value / max) * (bottom - top);
    const color = spec.color || '#d9b25f';
    const canvas = svg('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'sc-svg' });
    gridLines(canvas, left, right, 2, y, { max, format: (value, top) => fmt(value) + (top ? spec.unit || '' : '') });
    for (const [i, label] of spec.xlabels || []) {
        canvas.append(text({ x: x(i), y: height - 5, 'text-anchor': i === n - 1 ? 'end' : i ? 'middle' : 'start' }, label));
    }
    const points = spec.points.map((value, i) => `${x(i).toFixed(1)} ${y(value).toFixed(1)}`);
    canvas.append(svg('path', { d: `M${points.join('L')}L${x(n - 1)} ${bottom}L${x(0)} ${bottom}Z`, fill: color, opacity: 0.12 }));
    canvas.append(svg('path', { d: `M${points.join('L')}`, fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linejoin': 'round' }));
    canvas.append(svg('circle', { cx: x(n - 1), cy: y(spec.points[n - 1]), r: 4, fill: color, stroke: '#141416', 'stroke-width': 2 }));
    spec.points.forEach((value, i) => {
        const hit = svg('rect', { x: x(i) - (right - left) / (n - 1) / 2, y: top, width: (right - left) / (n - 1), height: bottom - top, fill: 'transparent' });
        hit.dataset.tip = `${spec.days ? spec.days[i] : `Day ${i + 1}`}|${spec.name || ''} ${fmt(value)}${spec.unit || ''}`;
        canvas.append(hit);
    });
    element.replaceChildren(canvas);
}

export function line(element) {
    const spec = JSON.parse(element.dataset.line);
    const W = element.clientWidth || 560;
    const H = +element.dataset.h || 220;
    const left = 38;
    const right = W - 12;
    const top = 10;
    const bottom = H - 24;
    const n = spec.median.length;
    const max = spec.ymax || Math.max(...spec.hi);
    const x = (i) => left + (i / (n - 1)) * (right - left);
    const y = (value) => bottom - (value / max) * (bottom - top);
    const canvas = svg('svg', { viewBox: `0 0 ${W} ${H}`, width: W, height: H, class: 'sc-svg' });
    gridLines(canvas, left, right, 4, y, { max, format: (value, top) => fmt(value) + (top ? spec.unit || '' : '') });
    for (const [i, label] of spec.xlabels) canvas.append(text({ x: x(i), y: H - 4, 'text-anchor': 'middle' }, label));
    const path = (values) => values.map((value, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)} ${y(value).toFixed(1)}`).join('');
    const band = spec.hi.map((value, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)} ${y(value).toFixed(1)}`).join('') + spec.lo.map((_, i) => `L${x(n - 1 - i).toFixed(1)} ${y(spec.lo[n - 1 - i]).toFixed(1)}`).join('') + 'Z';
    canvas.append(svg('path', { d: band, fill: 'rgba(255,255,255,0.07)' }));
    canvas.append(svg('path', { d: path(spec.median), fill: 'none', stroke: spec.medianColor || '#8a8a93', 'stroke-width': spec.medianColor ? 2.2 : 1.5, 'stroke-linejoin': 'round' }));
    canvas.append(svg('path', { d: path(spec.you), fill: 'none', stroke: spec.youColor || '#ff2d55', 'stroke-width': 2.2, 'stroke-linejoin': 'round' }));
    const last = spec.you.length - 1;
    canvas.append(svg('circle', { cx: x(last), cy: y(spec.you[last]), r: 4.5, fill: spec.youColor || '#ff2d55', stroke: '#141416', 'stroke-width': 2 }));
    if (spec.youLabel) canvas.append(text({ x: x(last) + 9, y: y(spec.you[last]) + 14, class: 'strong' }, spec.youLabel));
    if (spec.medianLabel !== '') canvas.append(text({ x: x(n - 1) - 4, y: y(spec.median[n - 1]) - 8, 'text-anchor': 'end' }, spec.medianLabel || 'Median'));
    const cross = svg('rect', { x: -9, y: top, width: 1, height: bottom - top, fill: '#f0f1f3', opacity: 0.45 });
    canvas.append(cross);
    canvas.addEventListener('mousemove', (event) => {
        const box = canvas.getBoundingClientRect();
        const i = Math.max(0, Math.min(n - 1, Math.round((((event.clientX - box.left) / box.width) * W - left) / ((right - left) / (n - 1)))));
        cross.setAttribute('x', x(i));
        const mine = spec.you[i] != null ? `${spec.youName || 'You'} ${spec.you[i]}${spec.unit || ''} · ` : '';
        tip(event, `${spec.xname || 'Level'} ${i + (spec.x0 || 1)}|${mine}median ${spec.median[i]}${spec.unit || ''}`);
    });
    canvas.addEventListener('mouseleave', () => {
        cross.setAttribute('x', -9);
        tip();
    });
    element.replaceChildren(canvas);
}

export function cols(element) {
    const spec = JSON.parse(element.dataset.cols);
    const W = element.clientWidth || 560;
    const H = +element.dataset.h || 170;
    const left = 34;
    const bottom = H - 22;
    const top = 8;
    const max = spec.ymax || Math.max(...spec.rows.map((row) => Math.max(row[1], row[2] || 0)));
    const width = (W - left - 8) / spec.rows.length;
    const y = (value) => bottom - (value / max) * (bottom - top);
    const canvas = svg('svg', { viewBox: `0 0 ${W} ${H}`, width: W, height: H, class: 'sc-svg' });
    gridLines(canvas, left, W, 2, y, { max, format: (value) => (spec.fmt ? spec.fmt.replace('#', Math.round(value * 10) / 10) : Math.round(value * 10) / 10) });
    spec.rows.forEach(([label, value, median, color], i) => {
        const x0 = left + i * width + Math.min(3, width * 0.15);
        const w = width - Math.min(6, width * 0.3);
        const bar = svg('rect', { x: x0, y: y(value), width: w, height: Math.max(1, bottom - y(value)), fill: color || '#ff2d55' });
        bar.dataset.tip = `${spec.xname || ''} ${label}|${spec.name || 'You'} ${value}${spec.unit || ''}${median != null ? ` · median ${median}${spec.unit || ''}` : ''}`;
        canvas.append(bar);
        if (median != null) canvas.append(svg('rect', { x: x0 - 2, y: y(median) - 1, width: w + 4, height: 2, fill: '#cdced4' }));
        if (spec.rows.length <= 16 || +label % (spec.every || 5) === 0) canvas.append(text({ x: x0 + w / 2, y: H - 5, 'text-anchor': 'middle' }, label));
    });
    element.replaceChildren(canvas);
}
