const changedForms = new WeakSet();
const numberBox = (node) => node?.closest?.('input[type="number"][data-autosubmit]');

document.addEventListener('change', (event) => {
    const control = event.target.closest?.('[data-autosubmit]');
    const form = control?.form;
    if (!form) return;
    if (control.type === 'number') {
        changedForms.add(form);
        return;
    }
    form.requestSubmit();
});

document.addEventListener('focusout', (event) => {
    const box = numberBox(event.target);
    const form = box?.form;
    if (!form || !changedForms.has(form) || numberBox(event.relatedTarget)?.form === form) return;
    changedForms.delete(form);
    form.requestSubmit();
});

document.addEventListener('change', (event) => {
    const select = event.target.closest?.('select[data-nav]');
    if (select && select.value) window.location.assign(select.value);
});

function spy(nav) {
    const links = [...nav.querySelectorAll('a[href^="#"]')];
    const sections = links.map((link) => document.getElementById(link.getAttribute('href').slice(1))).filter(Boolean);
    if (!sections.length || !('IntersectionObserver' in window)) return;
    const seen = new Set();
    const mark = () => {
        const first = sections.find((section) => seen.has(section));
        if (!first) return;
        for (const link of links) {
            if (link.getAttribute('href') === `#${first.id}`) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
    };
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) entry.isIntersecting ? seen.add(entry.target) : seen.delete(entry.target);
            mark();
        },
        { rootMargin: '-60px 0px -65% 0px' },
    );
    sections.forEach((section) => observer.observe(section));
}

export function controls(root = document) {
    root.querySelectorAll('nav[data-spy]').forEach(spy);
}
