// The search fields are comboboxes (WAI-ARIA, list autocomplete): under two characters they list the last searches made
// on this device, from two characters they list what GET /search/suggest answers. The answer is already formatted, so
// this file only draws it. Without this file the fields are plain forms.
const MIN_CHARS = 2;
const MAX_CHARS = 80;
const WAIT_MS = 250;
const KEPT_ANSWERS = 60;
const RECENT_KEY = 'scarlet.searches';
const RECENT_MAX = 6;

const answers = new Map();
const glyphs = document.querySelector('template[data-search-icons]')?.content;

function readRecent() {
    try {
        const stored = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
        return Array.isArray(stored) ? stored.filter((term) => typeof term === 'string' && term.length >= MIN_CHARS).slice(0, RECENT_MAX) : [];
    } catch {
        return [];
    }
}

function writeRecent(terms) {
    try {
        if (terms.length === 0) localStorage.removeItem(RECENT_KEY);
        else localStorage.setItem(RECENT_KEY, JSON.stringify(terms));
    } catch {
        return;
    }
}

function remember(term) {
    if (term.length < MIN_CHARS) return;
    const key = term.toLowerCase();
    writeRecent([term, ...readRecent().filter((each) => each.toLowerCase() !== key)].slice(0, RECENT_MAX));
}

function glyph(name) {
    const found = glyphs?.querySelector(`[data-glyph="${name}"]`)?.cloneNode(true);
    found?.removeAttribute('data-glyph');
    return found ?? null;
}

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function titleOf(line) {
    const title = el('b', line.quality || '');
    if (!Array.isArray(line.marks)) {
        title.textContent = line.title;
        return title;
    }
    for (const [text, matched] of line.marks) {
        title.append(matched ? el('mark', '', text) : document.createTextNode(text));
    }
    return title;
}

function optionOf(line) {
    const option = el('a', 'find-opt');
    option.href = line.href;
    const icon = el('span', 'find-ico');
    if (line.class) icon.dataset.class = line.class;
    icon.append(glyph(line.icon) ?? '');
    option.append(icon, titleOf(line));
    if (line.meta) option.append(el('span', 'find-meta', line.meta));
    if (line.value) option.append(el('span', 'find-val', line.value));
    return option;
}

function allOf(all) {
    const option = el('a', 'find-opt find-all', all.label);
    option.href = all.href;
    return option;
}

function recentOf(term) {
    const option = el('a', 'find-opt find-recent');
    option.href = `/search?${new URLSearchParams({ q: term })}`;
    const icon = el('span', 'find-ico');
    icon.append(glyph('clock') ?? '');
    option.append(icon, el('b', '', term));
    return option;
}

function attach(input) {
    const form = input.form;
    const suggestUrl = form.dataset.suggest;
    const host = input.parentElement;
    const panel = el('div', 'find-panel');
    const head = el('div', 'find-head');
    const label = el('span');
    const clear = el('button', 'find-clear', 'Clear');
    const list = el('div', 'find-list');
    const status = el('div', 'vh');
    panel.hidden = true;
    clear.type = 'button';
    list.id = `${input.id}-list`;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Search results');
    status.setAttribute('role', 'status');
    head.append(label, clear);
    panel.append(head, list);
    input.after(panel, status);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');

    let options = [];
    let active = -1;
    let shown = '';
    let pending = 0;
    let request = null;
    let quietUntil = 0;

    const term = () => input.value.trim().slice(0, MAX_CHARS);
    const key = () => term().toLowerCase();

    function mark(index) {
        options[active]?.removeAttribute('aria-selected');
        active = index;
        const current = options[index];
        if (!current) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        current.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', current.id);
        current.scrollIntoView({ block: 'nearest' });
    }

    function hide() {
        panel.hidden = true;
        panel.removeAttribute('data-stale');
        input.setAttribute('aria-expanded', 'false');
        mark(-1);
    }

    function close() {
        clearTimeout(pending);
        request?.abort();
        request = null;
        hide();
    }

    function show(nodes, heading, announcement, shownKey) {
        options = nodes;
        options.forEach((node, index) => {
            node.id = `${input.id}-o${index}`;
            node.setAttribute('role', 'option');
            node.tabIndex = -1;
        });
        list.replaceChildren(...nodes);
        label.textContent = heading;
        head.hidden = shownKey !== '';
        status.textContent = announcement;
        shown = shownKey;
        active = -1;
        input.removeAttribute('aria-activedescendant');
        panel.removeAttribute('data-stale');
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function showRecent() {
        const terms = readRecent();
        if (terms.length === 0) {
            close();
            return;
        }
        show(terms.map(recentOf), 'Recent searches', `${terms.length} recent ${terms.length === 1 ? 'search' : 'searches'}`, '');
    }

    function showAnswer(answer) {
        const nodes = answer.lines.map(optionOf);
        nodes.push(allOf(answer.all));
        show(nodes, '', answer.lines.length === 0 ? 'No results' : `${answer.lines.length} ${answer.lines.length === 1 ? 'result' : 'results'}`, key());
    }

    async function ask(text, wanted) {
        if (Date.now() < quietUntil) return;
        request?.abort();
        request = new AbortController();
        const mine = request;
        try {
            const response = await fetch(`${suggestUrl}?${new URLSearchParams({ q: text })}`, { signal: mine.signal, headers: { Accept: 'application/json' } });
            if (response.status === 429) quietUntil = Date.now() + (Number(response.headers.get('Retry-After')) || 30) * 1000;
            if (!response.ok) throw new Error(String(response.status));
            const answer = await response.json();
            if (answers.size >= KEPT_ANSWERS) answers.delete(answers.keys().next().value);
            answers.set(wanted, answer);
            if (mine === request && key() === wanted && document.activeElement === input) showAnswer(answer);
        } catch (error) {
            if (error?.name !== 'AbortError' && mine === request) close();
        }
    }

    function refresh() {
        clearTimeout(pending);
        const text = term();
        if (text.length < MIN_CHARS) {
            request?.abort();
            request = null;
            if (text === '') showRecent();
            else close();
            return;
        }
        const wanted = key();
        const kept = answers.get(wanted);
        if (kept) {
            request?.abort();
            request = null;
            showAnswer(kept);
            return;
        }
        if (!panel.hidden && shown === '') hide();
        if (!panel.hidden && shown !== wanted) {
            panel.dataset.stale = '';
            mark(-1);
        }
        pending = setTimeout(() => ask(text, wanted), WAIT_MS);
    }

    function step(by) {
        if (panel.hidden || panel.hasAttribute('data-stale')) {
            refresh();
            return;
        }
        const next = active + by;
        mark(next < -1 ? options.length - 1 : next >= options.length ? -1 : next);
    }

    input.addEventListener('input', refresh);
    input.addEventListener('focus', refresh);
    input.addEventListener('click', () => {
        if (panel.hidden) refresh();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            step(event.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close();
            return;
        }
        if (event.key === 'Enter' && active >= 0 && !panel.hasAttribute('data-stale')) {
            event.preventDefault();
            options[active].click();
        }
    });
    panel.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        input.focus();
        close();
    });
    panel.addEventListener('mousedown', (event) => event.preventDefault());
    panel.addEventListener('click', (event) => {
        if (event.target.closest('.find-clear')) {
            writeRecent([]);
            close();
            return;
        }
        const opt = event.target.closest('.find-opt');
        if (opt) remember(opt.classList.contains('find-recent') ? opt.querySelector('b').textContent : term());
    });
    host.addEventListener('focusout', (event) => {
        if (!host.contains(event.relatedTarget)) close();
    });
    form.addEventListener('submit', () => {
        remember(term());
        close();
    });
    addEventListener('pageshow', (event) => {
        if (event.persisted) close();
    });
}

function visible(field) {
    return field.getClientRects().length > 0;
}

function editing(target) {
    return target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

const fields = [...document.querySelectorAll('form[data-suggest] input[name="q"]')];
fields.forEach(attach);

addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || editing(event.target)) return;
    const field = fields.find(visible);
    if (!field) return;
    event.preventDefault();
    field.focus();
    field.select();
});
