import './local-time.js';
import './sc/tip.js';
import { controls } from './sc/controls.js';
import { route } from './sc/route.js';
import { area, cols, days, line } from './sc/series.js';
import { strip } from './sc/strip.js';

const DRAWN = [['[data-days]', days], ['[data-area]', area], ['[data-line]', line], ['[data-cols]', cols], ['[data-strip]', strip], ['[data-route]', route]];
const WIDTH_BOUND = DRAWN.map(([selector]) => selector).join(',');

function draw(root = document) {
    for (const [selector, make] of DRAWN) {
        root.querySelectorAll(selector).forEach((element) => {
            try {
                make(element);
            } catch (error) {
                console.error(selector, error);
            }
        });
    }
}

window.SC = { draw };

import './site/tally.js';
import './site/floors.js';
if (document.querySelector('[data-chart]')) {
    import('./charts.js');
}
if (document.querySelector('canvas[data-world-map]')) {
    import('./pages/maps.js');
}

const start = () => {
    controls();
    draw();
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();

let pending;
addEventListener('resize', () => {
    clearTimeout(pending);
    pending = setTimeout(() => {
        document.querySelectorAll(WIDTH_BOUND).forEach((element) => element.replaceChildren());
        draw();
    }, 120);
});
