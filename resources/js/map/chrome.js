// The small parts of the page that belong to the map: the zoom buttons, the look switch and the credit line.
// Classes are the comp's (docs/comps/world.html): .world-zoom, .iconbtn, .seg, .world-credit.

export const CREDIT_HREF = 'https://github.com/openmap-world/openmap';
export const LOOKS = [
    { look: 'survey', label: 'Survey' },
    { look: 'game', label: 'Game' },
];

function element(tag, attributes = {}, text = '') {
    const el = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => el.setAttribute(name, value));
    el.textContent = text;
    return el;
}

// The + and - buttons and the credit, inside `host` (the canvas's parent, which is positioned).
export function addZoomAndCredit(host, { zoomIn, zoomOut }) {
    const zoom = element('div', { class: 'world-zoom', role: 'group', 'aria-label': 'Zoom' });
    const plus = element('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Zoom in' }, '+');
    const minus = element('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Zoom out' }, '−');
    plus.addEventListener('click', zoomIn);
    minus.addEventListener('click', zoomOut);
    zoom.append(plus, minus);

    const credit = element('p', { class: 'world-credit' }, 'Map art © Blizzard Entertainment. Map by ');
    credit.append(element('a', { href: CREDIT_HREF, rel: 'noopener' }, 'OpenMap'), '.');

    host.append(zoom, credit);
    return () => {
        zoom.remove();
        credit.remove();
    };
}

// The Survey / Game switch, filled into `container`; returns a function that marks the active look.
export function addLookSwitch(container, choose) {
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', 'Look');
    const buttons = LOOKS.map(({ look, label }) => {
        const button = element('button', { type: 'button', 'aria-pressed': 'false' }, label);
        button.addEventListener('click', () => choose(look));
        container.append(button);
        return { look, button };
    });
    return (active) => buttons.forEach(({ look, button }) => button.setAttribute('aria-pressed', String(look === active)));
}
