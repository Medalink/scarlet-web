// What is drawn over the art besides heat: creature dots, paths, marks and zone names. All of it is data given to the viewer, in world u, v.

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

export function drawHead(ctx, view, at) {
    if (!at) {
        return;
    }
    const [x, y] = view.toScreen(at[0], at[1]);
    ctx.save();
    ctx.fillStyle = 'rgba(255,45,85,0.28)';
    ctx.beginPath();
    ctx.arc(x, y, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = FG;
    ctx.beginPath();
    ctx.arc(x, y, 3, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

export function drawDots(ctx, view, dots) {
    ctx.save();
    for (const [u, v] of dots) {
        const [x, y] = view.toScreen(u, v);
        if (x < -8 || y < -8 || x > view.width + 8 || y > view.height + 8) {
            continue;
        }
        ctx.beginPath();
        ctx.arc(x, y, 5.5, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(20,20,22,0.9)';
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, y, 3.6, 0, Math.PI * 2);
        ctx.fillStyle = ACCENT;
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, y, 1.3, 0, Math.PI * 2);
        ctx.fillStyle = FG;
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

const NAME_FONT = '"Sofia Sans", "Bahnschrift", sans-serif';
const NAME_ORDER = { town: 0, spot: 1, flight: 1, area: 2, zone: 2 };

/**
 * The place names of a sheet, as the Scarlet app letters them: names is [[name, kind, u, v], ...]. Towns first, then
 * spots and flight points, then areas. A town or a spot is a dot with its name beside it, an area is spaced capitals on
 * its middle. A name that would run over one already drawn (`taken`: the boxes of the zone names) or off the map tries
 * its other sides and is left out when none is free. Without `every` only the towns are lettered, for a sheet drawn small.
 */
export function drawPlaceNames(ctx, view, names, taken, every) {
    const boxes = taken.map((b) => [b.x, b.y, b.x + b.w, b.y + b.h]);
    const zoneNames = new Set(taken.map((b) => b.zone.name));
    const hit = (b) => boxes.some((t) => b[0] < t[2] && b[2] > t[0] && b[1] < t[3] && b[3] > t[1]);
    const off = (b) => b[0] < 0 || b[1] < 0 || b[2] > view.width || b[3] > view.height;
    ctx.save();
    ctx.lineJoin = 'round';
    ctx.textBaseline = 'alphabetic';
    const spaced = 'letterSpacing' in ctx;
    for (const [name, kind, u, v] of [...names].sort((a, b) => NAME_ORDER[a[1]] - NAME_ORDER[b[1]])) {
        const [x, y] = view.toScreen(u, v);
        if ((!every && kind !== 'town') || zoneNames.has(name) || x < -60 || y < -20 || x > view.width + 60 || y > view.height + 20) {
            continue;
        }
        if (kind === 'area' || kind === 'zone') {
            const text = name.toUpperCase();
            ctx.font = `600 10px ${NAME_FONT}`;
            if (spaced) {
                ctx.letterSpacing = '1.6px';
            }
            const width = ctx.measureText(text).width;
            for (const dy of [3.5, -9, 16]) {
                const box = [x - width / 2 - 4, y + dy - 10, x + width / 2 + 4, y + dy + 4];
                if (hit(box) || off(box)) {
                    continue;
                }
                boxes.push(box);
                ctx.textAlign = 'center';
                ctx.strokeStyle = 'rgba(20,20,22,0.7)';
                ctx.lineWidth = 3;
                ctx.strokeText(text, x, y + dy);
                ctx.fillStyle = 'rgba(240,241,243,0.42)';
                ctx.fillText(text, x, y + dy);
                break;
            }
            if (spaced) {
                ctx.letterSpacing = '0px';
            }
            continue;
        }
        const town = kind === 'town';
        ctx.font = `${town ? 600 : 500} ${town ? 12 : 11.5}px ${NAME_FONT}`;
        const width = ctx.measureText(name).width;
        const dot = [x - 5, y - 5, x + 5, y + 5];
        if (hit(dot)) {
            continue;
        }
        for (const [tx, ty, align] of [[x + 8, y + 4, 'left'], [x - 8, y + 4, 'right'], [x, y - 8, 'center'], [x, y + 16, 'center']]) {
            const left = align === 'left' ? tx : align === 'right' ? tx - width : tx - width / 2;
            const box = [left - 3, ty - 11, left + width + 3, ty + 4];
            if (hit(box) || off(box)) {
                continue;
            }
            boxes.push(box, dot);
            ctx.textAlign = align;
            ctx.strokeStyle = 'rgba(20,20,22,0.82)';
            ctx.lineWidth = 3.5;
            ctx.strokeText(name, tx, ty);
            ctx.strokeRect(x - 2, y - 2, 4, 4);
            ctx.fillStyle = `rgba(240,241,243,${town ? 0.9 : 0.6})`;
            ctx.fillRect(x - 2, y - 2, 4, 4);
            ctx.fillText(name, tx, ty);
            break;
        }
    }
    ctx.restore();
}
