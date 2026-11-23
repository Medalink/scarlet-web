import './sc/tip.js';
import { area, days } from './sc/series.js';

const DRAWN = [['[data-days]', days], ['[data-area]', area]];
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

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => draw());
else draw();

let pending;
addEventListener('resize', () => {
    clearTimeout(pending);
    pending = setTimeout(() => {
        document.querySelectorAll(WIDTH_BOUND).forEach((element) => element.replaceChildren());
        draw();
    }, 120);
});
