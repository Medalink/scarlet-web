import { svg, text } from './dom.js';
import { clock as localClock } from '../local-time.js';
import { tip } from './tip.js';

export const DEATH_GLYPH = '<path d="M12 3.2a7.3 7.3 0 0 0-7.3 7.3c0 2.5 1.2 4.4 2.8 5.6v2.7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-2.7c1.6-1.2 2.8-3.1 2.8-5.6A7.3 7.3 0 0 0 12 3.2z"/><circle cx="9.2" cy="11.2" r="1.5"/><circle cx="14.8" cy="11.2" r="1.5"/>';

const METAL = [null, '#c58a63', '#c3c7cf', '#d9b25f', '#ff2d55', '#ff2d55'];
const clock = (epoch) => localClock(new Date(epoch * 1000));

const PLATES = {
    journey: 'M32 10a22 22 0 1 0 0 44a22 22 0 1 0 0-44Z',
    combat: 'M12 11.5H52V31C52 42.5 43.5 50 32 54.5C20.5 50 12 42.5 12 31Z',
    quests: 'M13.5 10.5H50.5V54L32 45.5L13.5 54Z',
    dungeons: 'M12 53.5V30.5A20 20 0 0 1 52 30.5V53.5Z',
    world: 'M32 8L56 32L32 56L8 32Z',
    trade: 'M44 11.7L55.5 32L44 52.3L20 52.3L8.5 32L20 11.7Z',
    class: 'M32 8.5L38.9 25.1L55.5 32L38.9 38.9L32 55.5L25.1 38.9L8.5 32L25.1 25.1Z',
    role: 'M32 56L8.6 39L17.5 11.5L46.5 11.5L55.4 39Z',
};

export function strip(element) {
    const spec = JSON.parse(element.dataset.strip);
    const W = element.clientWidth || 640;
    const compact = element.dataset.compact != null;
    const x = (m) => (m / spec.mins) * W;
    const zoneHeight = compact ? 15 : 20;
    const paceHeight = compact ? 26 : 46;
    const paceTop = zoneHeight + 8;
    const combatY = paceTop + paceHeight + 4;
    const eventY = combatY + 11;
    const total = eventY + (compact ? 18 : 22) + (compact ? 0 : 16);
    const canvas = svg('svg', { viewBox: `0 0 ${W} ${total}`, width: W, height: total, class: 'sc-svg' });
    let at = 0;
    spec.zones.forEach(([name, mins], i) => {
        const x0 = x(at);
        const width = x(at + mins) - x0;
        canvas.append(svg('rect', { x: x0 + (i ? 1 : 0), y: 0, width: Math.max(1, width - (i ? 1 : 0)), height: zoneHeight, fill: i % 2 ? 'rgba(255,255,255,0.07)' : 'rgba(255,255,255,0.12)' }));
        if (width > name.length * 6.4 + 12) canvas.append(text({ x: x0 + 7, y: zoneHeight / 2 + 4, class: 'strong' }, name));
        at += mins;
    });
    const top = Math.max(...spec.pace, 1);
    const column = W / spec.pace.length;
    spec.pace.forEach((value, i) => {
        const tall = Math.max(1, (value / top) * paceHeight);
        canvas.append(svg('rect', { x: i * column + 1, y: paceTop + paceHeight - tall, width: Math.max(1, column - 2), height: tall, fill: value === top ? '#ff2d55' : 'rgba(255,45,85,0.62)' }));
    });
    canvas.append(svg('rect', { x: 0, y: paceTop + paceHeight, width: W, height: 1, fill: 'rgba(255,255,255,0.13)' }));
    for (const [from, to] of spec.combat) canvas.append(svg('rect', { x: x(from), y: combatY, width: Math.max(1.5, x(to) - x(from)), height: 3, fill: '#c0102e' }));
    for (const event of spec.events) drawEvent(canvas, event, Math.min(W - 8, Math.max(8, x(event.m))), eventY, W);
    if (!compact) drawClock(canvas, spec, x, total);
    const cross = svg('rect', { x: -9, y: 0, width: 1, height: eventY + 20, fill: '#f0f1f3', opacity: 0.5 });
    canvas.append(cross);
    canvas.addEventListener('mousemove', (event) => {
        const box = canvas.getBoundingClientRect();
        const m = Math.max(0, Math.min(spec.mins, ((event.clientX - box.left) / box.width) * spec.mins));
        cross.setAttribute('x', x(m));
        let zone = spec.zones[0][0];
        let start = 0;
        for (const [name, mins] of spec.zones) {
            if (m >= start) zone = name;
            start += mins;
        }
        const near = spec.events.find((e) => Math.abs(e.m - m) < spec.mins / 90 && e.tip);
        const bucket = spec.pace[Math.min(spec.pace.length - 1, Math.floor(m / (spec.mins / spec.pace.length)))];
        tip(event, `${clock(spec.t0 + m * 60)} · ${zone}|${near ? near.tip : `${Math.round(bucket).toLocaleString('en-US')} XP in five minutes`}`);
    });
    canvas.addEventListener('mouseleave', () => {
        cross.setAttribute('x', -9);
        tip();
    });
    element.replaceChildren(canvas);
}

function drawEvent(canvas, event, ex, ey, W) {
    if (event.k === 'level') {
        canvas.append(svg('rect', { x: ex - 0.5, y: 0, width: 1, height: ey + 4, fill: 'rgba(240,241,243,0.45)' }));
        canvas.append(svg('rect', { x: ex - 5, y: ey + 3, width: 10, height: 10, fill: '#f0f1f3', transform: `rotate(45 ${ex} ${ey + 8})` }));
        const room = W - ex - 11 > event.t.length * 6.4;
        canvas.append(text({ x: room ? ex + 11 : ex - 11, y: ey + 12, class: 'strong', 'text-anchor': room ? 'start' : 'end' }, event.t));
        return;
    }
    if (event.k === 'death') {
        const mark = svg('g', { transform: `translate(${ex - 7} ${ey + 1}) scale(0.6)`, fill: 'none', stroke: '#f0f1f3', 'stroke-width': 2.2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
        mark.innerHTML = DEATH_GLYPH;
        canvas.append(mark);
        return;
    }
    if (event.k === 'feat') {
        const plate = svg('g', { transform: `translate(${ex - 8} ${ey}) scale(0.25)` });
        plate.append(svg('path', { d: PLATES[event.family] || PLATES.combat, fill: METAL[event.tier || 2] }));
        canvas.append(plate);
        return;
    }
    canvas.append(svg('rect', { x: ex - 1.5, y: ey + 6.5, width: 3, height: 3, fill: '#8a8a93' }));
}

function drawClock(canvas, spec, x, total) {
    const label = (m, content, anchor) => canvas.append(text({ x: x(m), y: total - 2, 'text-anchor': anchor || 'middle' }, content));
    label(0, clock(spec.t0), 'start');
    label(spec.mins, clock(spec.t0 + spec.mins * 60), 'end');
    const first = new Date(spec.t0 * 1000);
    const startMin = first.getHours() * 60 + first.getMinutes();
    for (let hm = Math.ceil(startMin / 60) * 60; hm < startMin + spec.mins - 8; hm += 60) {
        if (hm - startMin > 14) label(hm - startMin, localClock(new Date(first.getTime() + (hm - startMin) * 60000)));
    }
}
