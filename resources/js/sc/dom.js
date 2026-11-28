const SVG = 'http://www.w3.org/2000/svg';

export function svg(tag, attrs = {}, kids = []) {
    const element = document.createElementNS(SVG, tag);
    for (const key in attrs) if (attrs[key] != null) element.setAttribute(key, attrs[key]);
    for (const kid of [].concat(kids)) if (kid) element.append(kid);
    return element;
}

export function html(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text != null) element.textContent = text;
    return element;
}

export const fmt = (n) => Math.round(n).toLocaleString('en-US');

export function text(attrs, content) {
    const element = svg('text', attrs);
    element.textContent = content;
    return element;
}

export function niceTicks(max, target = 4) {
    const raw = max / target;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const unit = raw / magnitude;
    const step = (unit <= 1 ? 1 : unit <= 2 ? 2 : unit <= 5 ? 5 : 10) * magnitude;
    const ticks = [];
    for (let value = 0; value <= max + 1e-9; value += step) ticks.push(Math.round(value * 1e6) / 1e6);
    return ticks;
}

export function gridLines(canvas, left, right, steps, y, label) {
    const ticks = label.max > 0 ? niceTicks(label.max, steps) : [0];
    ticks.forEach((value, i) => {
        canvas.append(svg('rect', { x: left, y: y(value), width: right - left, height: 1, fill: i ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.14)' }));
        canvas.append(text({ x: left - 7, y: y(value) + 4, 'text-anchor': 'end' }, label.format(value, i === ticks.length - 1)));
    });
}
