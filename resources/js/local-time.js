// Dates in the viewer's time zone. Public pages are cached per URL, so the server renders every date in UTC and marks
// what this module rewrites in the browser: a <time datetime data-when="moment|day|weekday|clock|clock-seconds|daytime">
// is rewritten into the date contract's form in local time (App\View\Dates writes them), gets the exact local value as
// its title and, for a moment, is refreshed while the page is open. [data-week-hour] holds an hour of the week (0 to
// 167, Monday 00:00 UTC first) and [data-local-label] holds the local wording of a label the server wrote in UTC.
// Rewriting from attributes makes it safe to run again after every Livewire morph.

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
export const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WORDS = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];
const WEEK = 168;
const DAY_MS = 86400000;
const NEXT_SECOND = 1000;
const NEXT_HALF_MINUTE = 30000;

export const pad = (n) => String(n).padStart(2, '0');

const wholeHoursEastOfUtc = Math.floor(-new Date().getTimezoneOffset() / 60);
const wrap = (i) => ((i % WEEK) + WEEK) % WEEK;

/** The viewer's hour of the week for a UTC one. A half-hour zone files a UTC hour under the local hour it starts in. */
export const localWeekHour = (i) => wrap(i + wholeHoursEastOfUtc);

const hourOfDay = (h) => `${h % 12 || 12}`;

/** "3 PM" for an hour of the day. */
export const hourMark = (h) => `${hourOfDay(h)} ${h < 12 ? 'AM' : 'PM'}`;

/** "Sat 7:00 PM" for an hour of the week, Monday 00:00 first. */
export const weekHourLabel = (i) => `${WEEKDAYS[Math.floor(i / 24)]} ${hourOfDay(i % 24)}:00 ${i % 24 < 12 ? 'AM' : 'PM'}`;

/** App\View\Format::duration. */
export function duration(seconds) {
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h${minutes % 60 ? ' ' + pad(minutes % 60) + 'm' : ''}`;
    return `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
}

const weekdayOf = (d) => WEEKDAYS[(d.getDay() + 6) % 7];
const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
const plural = (n, unit) => `${n} ${unit}${n === 1 ? '' : 's'}`;

const dayGap = (date, now) => Math.round((startOfDay(now) - startOfDay(date)) / DAY_MS);

/** "Nov 22", and "Nov 22 2025" in another year. */
export const shortDay = (date, now = new Date()) => `${MONTHS[date.getMonth()]} ${date.getDate()}${date.getFullYear() === now.getFullYear() ? '' : ' ' + date.getFullYear()}`;

/** "Sat Nov 22", and "Sat Nov 22 2025" in another year. */
export const weekdayDay = (date, now = new Date()) => `${weekdayOf(date)} ${shortDay(date, now)}`;

/** "8:19 PM", or "8:19:47 PM" with seconds. */
export const clock = (date, seconds = false) => `${date.getHours() % 12 || 12}:${pad(date.getMinutes())}${seconds ? ':' + pad(date.getSeconds()) : ''} ${date.getHours() < 12 ? 'AM' : 'PM'}`;

/** "Sat Nov 22 2025 8:19:47 PM", or "Sat Nov 22 2025" for a day. */
export const exact = (date, dayOnly = false) => `${weekdayOf(date)} ${MONTHS[date.getMonth()]} ${date.getDate()} ${date.getFullYear()}${dayOnly ? '' : ' ' + clock(date, true)}`;

/** A moment as the contract words it: "just now", "5 minutes ago", "yesterday", "3 days ago", "Nov 22", "in 2 hours". */
export function moment(date, now = new Date()) {
    const seconds = Math.round((now - date) / 1000);
    const away = Math.abs(seconds);
    const phrase = (n, unit) => (seconds < 0 ? `in ${plural(n, unit)}` : `${plural(n, unit)} ago`);
    if (seconds >= 0 && away < 10) return 'just now';
    if (away < 60) return phrase(away, 'second');
    if (away < 3600) return phrase(Math.floor(away / 60), 'minute');
    if (away < 86400) return phrase(Math.floor(away / 3600), 'hour');
    const days = dayGap(date, now);
    if (days === 1) return 'yesterday';
    if (days >= 2 && days <= 6) return `${days} days ago`;
    if (days <= -1 && days >= -6) return `in ${plural(-days, 'day')}`;
    return shortDay(date, now);
}

const FORMS = {
    moment: (date, now) => moment(date, now),
    day: (date, now) => shortDay(date, now),
    weekday: (date, now) => weekdayDay(date, now),
    clock: (date) => clock(date),
    'clock-seconds': (date) => clock(date, true),
    daytime: (date, now) => (dayGap(date, now) === 0 ? clock(date) : `${shortDay(date, now)} ${clock(date)}`),
};

function parse(value) {
    const key = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');
    if (key) return { date: new Date(Number(key[1]), Number(key[2]) - 1, Number(key[3])), dayOnly: true };
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : { date, dayOnly: false };
}

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

/** Rewrites every dated element under `root`; true when a moment under a minute old is on screen. */
export function localize(root = document) {
    const now = new Date();
    let young = false;
    for (const node of root.querySelectorAll('time[datetime][data-when]')) {
        const parsed = parse(node.getAttribute('datetime'));
        const form = FORMS[node.getAttribute('data-when')];
        if (parsed === null || form === undefined) continue;
        const text = form(parsed.date, now);
        if (node.textContent !== text) node.textContent = text;
        const title = exact(parsed.date, parsed.dayOnly);
        if (node.title !== title) node.title = title;
        if (node.getAttribute('data-when') === 'moment' && !parsed.dayOnly && Math.abs(now - parsed.date) < 60000) young = true;
    }
    for (const node of root.querySelectorAll('[data-week-hour]')) {
        node.textContent = weekHourLabel(localWeekHour(Number(node.getAttribute('data-week-hour'))));
    }
    for (const node of root.querySelectorAll('[data-local-label]')) {
        node.textContent = node.getAttribute('data-local-label');
    }
    return young;
}

let timer = null;

function refresh() {
    window.clearTimeout(timer);
    timer = null;
    if (document.hidden) return;
    const young = localize();
    timer = window.setTimeout(refresh, young ? NEXT_SECOND : NEXT_HALF_MINUTE);
}

const watchLivewire = () => window.Livewire.hook('morphed', ({ el }) => localize(el ?? document));

if (window.Livewire) watchLivewire();
else document.addEventListener('livewire:init', watchLivewire, { once: true });

document.addEventListener('visibilitychange', refresh);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh, { once: true });
else refresh();
