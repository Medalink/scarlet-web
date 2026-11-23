let element;

export function tip(event, text) {
    if (!element) {
        element = document.createElement('div');
        element.className = 'sc-tip';
        document.body.append(element);
    }
    if (!text) {
        element.classList.remove('on');
        return;
    }
    const [lead, rest] = text.split('|');
    element.replaceChildren(document.createTextNode(lead));
    if (rest) {
        const small = document.createElement('small');
        small.textContent = rest;
        element.append(small);
    }
    element.classList.add('on');
    const width = element.offsetWidth;
    element.style.left = `${Math.max(8, Math.min(innerWidth - width - 8, event.clientX + 14))}px`;
    element.style.top = `${event.clientY + 16}px`;
}

const target = (event) => event.target.closest?.('[data-tip]');

document.addEventListener('mouseover', (event) => {
    const hit = target(event);
    if (hit) tip(event, hit.dataset.tip);
});
document.addEventListener('mousemove', (event) => {
    const hit = target(event);
    if (hit && !hit.closest('svg[data-own]')) tip(event, hit.dataset.tip);
});
document.addEventListener('mouseout', (event) => {
    if (target(event)) tip();
});
