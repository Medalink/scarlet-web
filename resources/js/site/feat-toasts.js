// The owner's feat toasts (resources/views/components/feat-toasts.blade.php) play one at a time. Showing a toast
// tells the server which tier it showed (FeatWall::seen), so a reload never replays it. A visitor's page has no
// stack, so this module returns before it does anything.
const START = 600;
const DWELL = 4200;
const GAP = 300;
const TICK = 50;
const OPEN = ':popover-open';

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const native = Object.hasOwn(HTMLElement.prototype, 'popover');

const isOpen = (toast) => (native ? toast.matches(':popover-open') : toast.classList.contains(OPEN));
const show = (toast) => (native ? toast.showPopover() : toast.classList.add(OPEN));
const hide = (toast) => (native ? toast.hidePopover() : toast.classList.remove(OPEN));

let displayTransition;
function canTransitionDisplay() {
    if (displayTransition !== undefined) return displayTransition;
    if (!window.CSS?.supports?.('transition-behavior', 'allow-discrete') || !document.body) return false;
    const probe = document.createElement('div');
    probe.style.cssText = 'transition: display 1s allow-discrete !important; display: block;';
    document.body.appendChild(probe);
    getComputedStyle(probe).display;
    probe.style.display = 'none';
    displayTransition = getComputedStyle(probe).display === 'block';
    probe.remove();
    return displayTransition;
}

const supportsTopLayerExit = () => window.CSS?.supports?.('overlay', 'auto') && canTransitionDisplay();

async function settle(toast) {
    const animations = toast.getAnimations({ subtree: true });
    if (animations.length === 0) return;
    await Promise.race([Promise.allSettled(animations.map((animation) => animation.finished)), wait(2000)]);
}

async function leave(toast) {
    if (!supportsTopLayerExit()) {
        toast.setAttribute('data-closing', '');
        await settle(toast);
        toast.removeAttribute('data-closing');
    }
    hide(toast);
    await settle(toast);
}

function visible() {
    if (document.visibilityState === 'visible') return Promise.resolve();
    return new Promise((resolve) => {
        const on = () => {
            if (document.visibilityState !== 'visible') return;
            document.removeEventListener('visibilitychange', on);
            resolve();
        };
        document.addEventListener('visibilitychange', on);
    });
}

function loaded() {
    if (document.readyState === 'complete') return Promise.resolve();
    return new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));
}

function dwell(toast) {
    return new Promise((resolve) => {
        let left = DWELL;
        let last = performance.now();
        const done = () => {
            clearInterval(timer);
            toast.removeEventListener('feat-toast-close', done);
            resolve();
        };
        const timer = setInterval(() => {
            const now = performance.now();
            const held = document.hidden || toast.matches(':hover, :focus-within');
            left -= held ? 0 : now - last;
            last = now;
            if (left <= 0) done();
        }, TICK);
        toast.addEventListener('feat-toast-close', done);
    });
}

function announce(stack, toast) {
    const say = toast.querySelector('.feat-toast-say').innerText.trim().replace(/\s*\n+\s*/g, '. ');
    const text = toast.querySelector('[command]') ? `${say}. Actions available` : say;
    if (document.ariaNotify) {
        document.ariaNotify(text);
        return;
    }
    stack.querySelector('[data-live]').textContent = text;
}

async function livewire(stack) {
    const id = stack.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (!window.Livewire) {
        await new Promise((resolve) => document.addEventListener('livewire:init', resolve, { once: true }));
    }
    return id ? window.Livewire.find(id) : null;
}

async function mark(stack, toast) {
    const component = await livewire(stack);
    if (component) await component.seen(JSON.parse(toast.dataset.shown)).catch(() => {});
}

async function play(stack, toast, state) {
    const prior = document.activeElement;
    state.current = toast;
    show(toast);
    announce(stack, toast);
    mark(stack, toast);
    await dwell(toast);
    const focused = toast.contains(document.activeElement);
    await leave(toast);
    state.current = null;
    if (focused && prior?.isConnected && !stack.contains(prior)) prior.focus();
}

function raiseOverDialog(state) {
    const dialog = document.getElementById('feats-all');
    if (!dialog) return;
    new MutationObserver(() => {
        const toast = state.current;
        if (!dialog.open || !toast || !isOpen(toast)) return;
        hide(toast);
        show(toast);
    }).observe(dialog, { attributes: true, attributeFilter: ['open'] });
}

async function start(stack) {
    const toasts = [...stack.querySelectorAll('.feat-toast')];
    const state = { current: null };
    stack.addEventListener('click', (event) => {
        const control = event.target instanceof Element ? event.target.closest('.feat-toast-x, [command]') : null;
        const toast = control?.closest('.feat-toast');
        if (!toast) return;
        if (control.matches('.feat-toast-x')) event.preventDefault();
        toast.dispatchEvent(new Event('feat-toast-close'));
    });
    raiseOverDialog(state);
    await loaded();
    await wait(START);
    for (const toast of toasts) {
        await visible();
        await play(stack, toast, state);
        await wait(GAP);
    }
}

const stack = document.querySelector('.feat-toasts');
if (stack && !stack.dataset.playing) {
    stack.dataset.playing = '';
    start(stack);
}
