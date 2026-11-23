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

export function gridLines(canvas, left, right, steps, y, label) {
    for (let step = 0; step <= steps; step++) {
        const value = (label.max / steps) * step;
        canvas.append(svg('rect', { x: left, y: y(value), width: right - left, height: 1, fill: step ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.14)' }));
        canvas.append(text({ x: left - 7, y: y(value) + 4, 'text-anchor': 'end' }, label.format(value, step)));
    }
}
