// The tally under a page hero counts each whole number up once when the page opens. The server's text is the
// truth: it is what a reader without script or with reduced motion sees, and what the count ends on.
const format = new Intl.NumberFormat('en-US');
const DURATION = 900;

function countUp(node) {
    const target = Number(node.dataset.count);
    const final = node.textContent;
    if (!Number.isFinite(target) || target < 10) return;
    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / DURATION);
        const eased = 1 - Math.pow(1 - t, 4);
        node.textContent = t < 1 ? format.format(Math.round(target * eased)) : final;
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

if (matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    document.querySelectorAll('.tally [data-count]').forEach(countUp);
}
