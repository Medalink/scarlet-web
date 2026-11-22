// Moving the map: drag, wheel, double click, pinch and the keyboard. Handlers get canvas-relative CSS px. The wheel
// and a double click ask for an eased zoom (`glideAt`, `doubleAt`); a pinch zooms as the fingers move (`zoomAt`). With `pageScrolls` a
// one-finger vertical swipe stays the page's, and so does the wheel until the map is clicked or dragged: from then the
// wheel zooms, until the pointer leaves the map, the map loses focus or Escape is pressed. Ctrl or Command with the
// wheel always zooms.

const WHEEL = 0.0035;
const PINCH_WHEEL = 0.012;
const MOST_PER_EVENT = 300;
const DOUBLE_CLICK = 2;
const STEP = 1.5;
const TAP = 5;

export function attachInput(canvas, handlers, { pageScrolls = false } = {}) {
    const pointers = new Map();
    let moved = 0;
    let pinch = null;
    let engaged = false;

    const local = (event) => {
        const box = canvas.getBoundingClientRect();
        return { x: event.clientX - box.left, y: event.clientY - box.top };
    };

    const gesture = () => {
        const [a, b] = [...pointers.values()];
        return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, distance: Math.hypot(a.x - b.x, a.y - b.y) };
    };

    const down = (event) => {
        handlers.start();
        engaged = true;
        canvas.setPointerCapture(event.pointerId);
        pointers.set(event.pointerId, local(event));
        moved = 0;
        pinch = pointers.size === 2 ? gesture() : null;
        canvas.focus({ preventScroll: true });
    };

    const move = (event) => {
        const at = local(event);
        const before = pointers.get(event.pointerId);
        if (!before) {
            handlers.hover(at.x, at.y);
            return;
        }
        pointers.set(event.pointerId, at);
        if (pointers.size === 1) {
            moved += Math.abs(at.x - before.x) + Math.abs(at.y - before.y);
            handlers.drag(at.x - before.x, at.y - before.y);
            return;
        }
        if (pointers.size === 2 && pinch) {
            const now = gesture();
            moved = TAP + 1;
            handlers.drag(now.x - pinch.x, now.y - pinch.y);
            if (pinch.distance > 0 && now.distance > 0) {
                handlers.zoomAt(now.distance / pinch.distance, now.x, now.y);
            }
            pinch = now;
        }
    };

    const up = (event) => {
        const at = local(event);
        if (pointers.delete(event.pointerId) && pointers.size === 0 && moved < TAP && event.type === 'pointerup') {
            handlers.tap(at.x, at.y);
        }
        pinch = pointers.size === 2 ? gesture() : null;
        handlers.hover(at.x, at.y);
    };

    const wheel = (event) => {
        if (pageScrolls && !engaged && !event.ctrlKey && !event.metaKey) {
            return;
        }
        event.preventDefault();
        const lines = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 400 : 1;
        const delta = Math.max(-MOST_PER_EVENT, Math.min(MOST_PER_EVENT, event.deltaY * lines));
        const at = local(event);
        handlers.glideAt(Math.exp(-delta * (event.ctrlKey ? PINCH_WHEEL : WHEEL)), at.x, at.y);
    };

    const twice = (event) => {
        const at = local(event);
        handlers.doubleAt(event.shiftKey ? 1 / DOUBLE_CLICK : DOUBLE_CLICK, at.x, at.y);
    };

    const key = (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }
        const pan = {
            ArrowLeft: [1, 0],
            ArrowRight: [-1, 0],
            ArrowUp: [0, 1],
            ArrowDown: [0, -1],
        }[event.key];
        if (pan) {
            handlers.start();
            handlers.drag(pan[0] * canvas.clientWidth * 0.15, pan[1] * canvas.clientHeight * 0.15);
        } else if (event.key === '+' || event.key === '=') {
            handlers.zoomCentre(STEP);
        } else if (event.key === '-' || event.key === '_') {
            handlers.zoomCentre(1 / STEP);
        } else if (event.key === '0' || event.key === 'Home') {
            handlers.fitWorld();
        } else if (event.key === 'Escape') {
            engaged = false;
            return;
        } else {
            return;
        }
        event.preventDefault();
    };

    const release = () => {
        engaged = false;
    };
    const leave = () => {
        release();
        handlers.hover(null, null);
    };

    canvas.style.touchAction = pageScrolls ? 'pan-y' : 'none';
    canvas.addEventListener('pointerdown', down);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', up);
    canvas.addEventListener('pointercancel', up);
    canvas.addEventListener('pointerleave', leave);
    canvas.addEventListener('wheel', wheel, { passive: false });
    canvas.addEventListener('dblclick', twice);
    canvas.addEventListener('keydown', key);
    canvas.addEventListener('blur', release);

    return () => {
        canvas.removeEventListener('pointerdown', down);
        canvas.removeEventListener('pointermove', move);
        canvas.removeEventListener('pointerup', up);
        canvas.removeEventListener('pointercancel', up);
        canvas.removeEventListener('pointerleave', leave);
        canvas.removeEventListener('wheel', wheel);
        canvas.removeEventListener('dblclick', twice);
        canvas.removeEventListener('keydown', key);
        canvas.removeEventListener('blur', release);
    };
}

export const ZOOM_STEP = STEP;
