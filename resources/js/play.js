// The play time band of the character page in the viewer's time zone. The page draws the band in UTC from the same hours
// (App\View\Pages\CharacterPlay); this draws it again from them, with the same wording, so a viewer in UTC sees no change.

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WORDS = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];
const HOUR_MS = 3600000;

const pad = (n) => String(n).padStart(2, '0');
const dayKey = (d) => d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate();
const dayName = (d) => `${WEEKDAYS[(d.getDay() + 6) % 7]} ${d.getDate()} ${MONTHS[d.getMonth()]}`;
const short = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]}`;

function duration(seconds) {
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h${minutes % 60 ? ' ' + pad(minutes % 60) + 'm' : ''}`;
    return `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
}

function firstMax(values) {
    const top = Math.max(...values);
    return top > 0 ? values.indexOf(top) : null;
}

function dayColumns(hours, count) {
    const today = new Date();
    const days = Array.from({ length: count }, (_, k) => new Date(today.getFullYear(), today.getMonth(), today.getDate() - (count - 1 - k)));
    const index = new Map(days.map((d, k) => [dayKey(d), k]));
    const seconds = new Array(count).fill(0);
    for (let n = 0; n < hours.length; n += 2) {
        const k = index.get(dayKey(new Date(hours[n] * HOUR_MS)));
        if (k !== undefined) seconds[k] += hours[n + 1];
    }
    return { days, seconds };
}

function weekCells(hours) {
    const cells = new Array(168).fill(0);
    for (let n = 0; n < hours.length; n += 2) {
        const d = new Date(hours[n] * HOUR_MS);
        cells[((d.getDay() + 6) % 7) * 24 + d.getHours()] += hours[n + 1];
    }
    return cells;
}

function drawBars(box, spec, { reader, htmlEl }, signal) {
    const { days, seconds } = dayColumns(spec.hours, spec.days);
    const top = Math.max(...seconds);
    const busiest = firstMax(seconds);
    const say = days.map((d, k) => [dayName(d), seconds[k] > 0 ? `: ${duration(seconds[k])} played.` : ': not played.']);
    const columns = seconds.map((value, k) => {
        const column = htmlEl('span', value <= 0 ? 'z' : (k === busiest ? 'peak' : ''));
        column.title = say[k][0] + say[k][1];
        const bar = htmlEl('i');
        bar.style.height = `${Math.round((value / Math.max(1, top)) * 1000) / 10}%`;
        column.append(bar);
        return column;
    });
    const bars = box.querySelector('.pt-bars');
    bars.replaceChildren(...columns);
    bars.setAttribute('aria-label', `Hours played in each of the last ${spec.days} days`);
    const max = box.querySelector('.pt-max');
    if (max) max.textContent = duration(top);
    box.querySelector('.pt-x').replaceChildren(...[days[0], days[Math.floor((spec.days - 1) / 2)], days[spec.days - 1]].map((d) => htmlEl('span', '', short(d))));
    const summary = busiest === null ? [`No play in the last ${spec.days} days`, ''] : ['Busiest day', `: ${dayName(days[busiest])}, ${duration(top)}`];
    bars.setAttribute('tabindex', '0');
    reader(bars, { out: 'days-out', say, summary, rest: busiest ?? 0 }, {
        count: spec.days,
        at: (e) => {
            const k = columns.indexOf(e.target.closest('span'));
            return k < 0 ? null : k;
        },
        show: (i, live) => columns.forEach((column, k) => column.classList.toggle('on', live && k === i)),
    }, signal);
}

function drawGrid(box, spec, { draw }, signal) {
    const cells = weekCells(spec.hours);
    const top = Math.max(...cells);
    const busiest = firstMax(cells);
    const levels = WEEKDAYS.map(() => []);
    const say = [];
    cells.forEach((value, n) => {
        const level = value <= 0 ? 0 : Math.max(1, Math.ceil((value / top) * 4));
        levels[Math.floor(n / 24)].push(level);
        say.push([`${WEEKDAYS[Math.floor(n / 24)]} ${pad(n % 24)}:00`, `, ${WORDS[level]}${value > 0 ? ', ' + duration(value) + ' played' : ''}`]);
    });
    const grid = box.querySelector('.hours');
    grid.setAttribute('aria-label', 'Play time by weekday and hour of day, in your time zone');
    draw.hours(grid, {
        days: WEEKDAYS,
        levels,
        say,
        out: 'hours-out',
        rest: busiest,
        summary: busiest === null ? null : ['Busiest hour', `: ${WEEKDAYS[Math.floor(busiest / 24)]} ${pad(busiest % 24)}:00`],
    }, signal);
}

export function drawPlay(box, spec, signal, helpers) {
    drawBars(box, spec, helpers, signal);
    drawGrid(box, spec, helpers, signal);
    const heading = document.getElementById('h-hours');
    if (heading) heading.textContent = heading.textContent.replace(' (UTC)', '');
}
