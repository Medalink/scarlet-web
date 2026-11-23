document.addEventListener('change', (event) => {
    const select = event.target.closest?.('[data-autosubmit]');
    if (select?.form) select.form.requestSubmit();
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
