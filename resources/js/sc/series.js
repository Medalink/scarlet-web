import { fmt, gridLines, svg, text } from './dom.js';

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
    gridLines(canvas, left, width, 2, y, { max, format: (value, step) => Math.round(value * 10) / 10 + (step === 2 ? spec.unit || '' : '') });
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
    gridLines(canvas, left, right, 2, y, { max, format: (value, step) => fmt(value) + (step === 2 ? spec.unit || '' : '') });
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
