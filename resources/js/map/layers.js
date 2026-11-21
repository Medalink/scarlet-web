// What is drawn over the art: heat cells on the 4096 world grid, paths, marks and zone names. All of it is data given
// to the viewer, in world u, v.

export const HEAT = ['#4b1626', '#861a34', '#cf1f45', '#ff5d7e'];
export const GRID = 4096;

const ACCENT = '#ff2d55';
const SOFT = '#ff6b8e';
const FG = '#f0f1f3';
const SHEET = '#141416';

// Path styles by mode: run, mount and swim are scarlet lines, taxi a dashed one, ghost a dotted one.
const PATH = {
    run: { width: 2.25, color: ACCENT, halo: true },
    mount: { width: 2.25, color: ACCENT, halo: true },
    swim: { width: 2.25, color: ACCENT, halo: true },
    taxi: { width: 1.5, color: SOFT, dash: [7, 6] },
    ghost: { width: 1.5, color: 'rgba(240,241,243,0.6)', dash: [1.5, 5] },
};

// cells: [[i, j, level], ...] or a flat [i, j, level, i, j, level, ...]; level 1 to 4. Returns a flat Uint16Array.
export function flatCells(cells) {
    if (!cells || cells.length === 0) {
        return new Uint16Array(0);
    }
    if (typeof cells[0] === 'number') {
        return Uint16Array.from(cells);
    }
    const flat = new Uint16Array(cells.length * 3);
    cells.forEach((cell, n) => flat.set(cell, n * 3));
    return flat;
}

export function drawHeat(ctx, view, flat, alpha = 0.9) {
    if (flat.length === 0) {
        return;
    }
    const cw = (view.W / GRID) * view.s;
    const ch = (view.H / GRID) * view.s;
    const [x0, y0] = view.toScreen(0, 0);
    ctx.save();
    ctx.globalAlpha = alpha;
    for (let level = 1; level <= HEAT.length; level++) {
        ctx.fillStyle = HEAT[level - 1];
        ctx.beginPath();
        for (let n = 0; n < flat.length; n += 3) {
            if (flat[n + 2] !== level) {
                continue;
            }
            const x = x0 + flat[n] * cw;
            const y = y0 + flat[n + 1] * ch;
            if (x + cw < 0 || y + ch < 0 || x > view.width || y > view.height) {
                continue;
            }
            ctx.rect(x, y, Math.max(cw, 0.9), Math.max(ch, 0.9));
        }
        ctx.fill();
    }
    ctx.restore();
}

// paths: [{ points: [[u, v], ...], mode }]. Lines are drawn with a round join, the way the landing page draws them.
export function drawPaths(ctx, view, paths) {
    ctx.save();
    ctx.lineJoin = ctx.lineCap = 'round';
    for (const path of paths) {
        const style = PATH[path.mode] ?? PATH.run;
        const trace = () => {
            ctx.beginPath();
            path.points.forEach(([u, v], n) => {
                const [x, y] = view.toScreen(u, v);
                if (n === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
        };
        ctx.setLineDash(style.dash ?? []);
        if (style.halo) {
            trace();
            ctx.strokeStyle = 'rgba(255,45,85,0.16)';
            ctx.lineWidth = 7;
            ctx.stroke();
        }
        trace();
        ctx.strokeStyle = style.color;
        ctx.lineWidth = style.width;
        ctx.stroke();
    }
    ctx.restore();
}

// One mark vocabulary, as static-site/assets/map.js draws it: kill, death, quest and level.
export function drawMark(ctx, kind, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const ring = (shape) => {
        ctx.strokeStyle = SHEET;
        ctx.lineWidth = 4.5;
        shape();
        ctx.stroke();
        ctx.strokeStyle = FG;
        ctx.lineWidth = 1.6;
        shape();
        ctx.stroke();
    };
    if (kind === 'kill') {
        ctx.fillStyle = SHEET;
        ctx.beginPath();
        ctx.arc(0, 0, 3.4, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = FG;
        ctx.lineWidth = 1.5;
        ctx.stroke();
    } else if (kind === 'death') {
        ring(() => {
            ctx.beginPath();
            ctx.moveTo(-4.5, -4.5);
            ctx.lineTo(4.5, 4.5);
            ctx.moveTo(4.5, -4.5);
            ctx.lineTo(-4.5, 4.5);
        });
    } else if (kind === 'quest') {
        ctx.beginPath();
        ctx.moveTo(0, -5.5);
        ctx.lineTo(5.5, 0);
        ctx.lineTo(0, 5.5);
        ctx.lineTo(-5.5, 0);
        ctx.closePath();
        ctx.strokeStyle = SHEET;
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.fillStyle = FG;
        ctx.fill();
    } else if (kind === 'level') {
        ring(() => {
            ctx.beginPath();
            ctx.moveTo(-5, 1);
            ctx.lineTo(0, -4);
            ctx.lineTo(5, 1);
            ctx.moveTo(-5, 6);
            ctx.lineTo(0, 1);
            ctx.lineTo(5, 6);
        });
    }
    ctx.restore();
}

export function drawMarks(ctx, view, marks) {
    for (const mark of marks) {
        const [x, y] = view.toScreen(mark.u, mark.v);
        if (x > -8 && y > -8 && x < view.width + 8 && y < view.height + 8) {
            drawMark(ctx, mark.kind, x, y);
        }
    }
}

// Zone names, centred on the zone's rectangle, where they fit inside it. The biggest zones are placed first and a name
// that would sit on one already placed is left out. Returns the boxes drawn, for hit tests.
export function drawZoneNames(ctx, view, zones) {
    const boxes = [];
    ctx.save();
    ctx.font = '600 11px "Sofia Sans", "Bahnschrift", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.lineJoin = 'round';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '2px';
    }
    const sorted = [...zones].sort((a, b) => (b.u1 - b.u0) * (b.v1 - b.v0) - (a.u1 - a.u0) * (a.v1 - a.v0));
    for (const zone of sorted) {
        const [x0, y0] = view.toScreen(zone.u0, zone.v0);
        const [x1, y1] = view.toScreen(zone.u1, zone.v1);
        const w = x1 - x0;
        if (w > view.width * 0.7 || x1 < 0 || y1 < 0 || x0 > view.width || y0 > view.height) {
            continue;
        }
        const text = zone.name.toUpperCase();
        const tw = ctx.measureText(text).width;
        const cx = (x0 + x1) / 2;
        const cy = (y0 + y1) / 2;
        const box = { x: cx - tw / 2 - 3, y: cy - 8, w: tw + 6, h: 16, zone };
        if (tw > w * 0.92 || boxes.some((b) => box.x < b.x + b.w && box.x + box.w > b.x && box.y < b.y + b.h && box.y + box.h > b.y)) {
            continue;
        }
        ctx.strokeStyle = 'rgba(20,20,22,0.85)';
        ctx.lineWidth = 3.5;
        ctx.strokeText(text, cx, cy);
        ctx.fillStyle = zone.href ? 'rgba(240,241,243,0.8)' : 'rgba(240,241,243,0.55)';
        ctx.fillText(text, cx, cy);
        boxes.push(box);
    }
    ctx.restore();
    return boxes;
}
