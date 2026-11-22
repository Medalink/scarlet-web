// Times in the viewer's time zone. Public pages are cached per URL, so the server renders every time in UTC and marks
// what this module rewrites in the browser: a <time datetime data-local="..."> formats its datetime in local time with
// the PHP date tokens of data-local, [data-week-hour] holds an hour of the week (0 to 167, Monday 00:00 UTC first) and
// [data-local-label] holds the local wording of a label the server wrote in UTC. Rewriting from attributes makes it
// safe to run again after every Livewire morph.

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
export const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WORDS = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];
const WEEK = 168;

export const pad = (n) => String(n).padStart(2, '0');

const wholeHoursEastOfUtc = Math.floor(-new Date().getTimezoneOffset() / 60);
const wrap = (i) => ((i % WEEK) + WEEK) % WEEK;

/** The viewer's hour of the week for a UTC one. A half-hour zone files a UTC hour under the local hour it starts in. */
export const localWeekHour = (i) => wrap(i + wholeHoursEastOfUtc);

/** "Sat 19:00" for an hour of the week, Monday 00:00 first. */
export const weekHourLabel = (i) => `${WEEKDAYS[Math.floor(i / 24)]} ${pad(i % 24)}:00`;

/** App\View\Format::duration. */
export function duration(seconds) {
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h${minutes % 60 ? ' ' + pad(minutes % 60) + 'm' : ''}`;
    return `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
}

const TOKENS = {
    D: (d) => WEEKDAYS[(d.getDay() + 6) % 7],
    j: (d) => String(d.getDate()),
    M: (d) => MONTHS[d.getMonth()],
    Y: (d) => String(d.getFullYear()),
    H: (d) => pad(d.getHours()),
    i: (d) => pad(d.getMinutes()),
};

const format = (date, tokens) => tokens.replace(/[DjMYHi]/g, (token) => TOKENS[token](date));

/** The 168 cells of a week summed in UTC, Monday 00:00 UTC first, moved to the viewer's hours of the week. */
export const localWeek = (utcCells) => Array.from({ length: WEEK }, (_, n) => utcCells[wrap(n - wholeHoursEastOfUtc)] ?? 0);

/**
 * What draw.hours needs for a week of played seconds already in the viewer's hours, Monday 00:00 first: four levels
 * from the busiest cell, the reader's lines and the summary, in App\View\Pages\StatsPage's wording. `chosen` is the
 * busiest cell when the page already picked one, so a tie names the same hour as the page's tile. `count` names what the
 * cells count when it is not played seconds ("visits").
 */
export function weekGrid(cells, chosen = null, count = null) {
    const top = Math.max(...cells);
    const busiest = chosen ?? (top > 0 ? cells.indexOf(top) : null);
    const levels = WEEKDAYS.map(() => []);
    const say = cells.map((value, n) => {
        const level = value <= 0 ? 0 : Math.max(1, Math.ceil((value / top) * 4));
        levels[Math.floor(n / 24)].push(level);
        const amount = count === null ? duration(value) + ' played' : `${value.toLocaleString('en-US')} ${count}${value === 1 ? '' : 's'}`;
        return [weekHourLabel(n), `, ${WORDS[level]}${value > 0 ? ', ' + amount : ''}`];
    });

    return {
        days: WEEKDAYS,
        levels,
        say,
        rest: busiest,
        summary: busiest === null ? null : ['Busiest hour', `: ${weekHourLabel(busiest)}`],
    };
}

export function localize(root = document) {
    for (const node of root.querySelectorAll('time[datetime][data-local]')) {
        const date = new Date(node.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) node.textContent = format(date, node.getAttribute('data-local'));
    }
    for (const node of root.querySelectorAll('[data-week-hour]')) {
        node.textContent = weekHourLabel(localWeekHour(Number(node.getAttribute('data-week-hour'))));
    }
    for (const node of root.querySelectorAll('[data-local-label]')) {
        node.textContent = node.getAttribute('data-local-label');
    }
}

const watchLivewire = () => window.Livewire.hook('morphed', ({ el }) => localize(el ?? document));

if (window.Livewire) watchLivewire();
else document.addEventListener('livewire:init', watchLivewire, { once: true });

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => localize(), { once: true });
else localize();
