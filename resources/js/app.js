// Livewire 4 loads Alpine itself.
import './local-time.js';
import './site/tally.js';
import './site/search.js';
import './site/dialog.js';
import './site/feat-toasts.js';
import './site/floors.js';
import './site/nav.js';

if (document.querySelector('[data-chart]')) {
    import('./charts.js');
}
if (document.querySelector('canvas[data-world-map]')) {
    import('./pages/maps.js');
}
