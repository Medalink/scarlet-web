const RESET_MS = 1600;

function copyText(text) {
  if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);
  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', '');
  area.style.position = 'fixed';
  area.style.opacity = '0';
  document.body.append(area);
  area.select();
  const done = document.execCommand('copy');
  area.remove();
  return done ? Promise.resolve() : Promise.reject(new Error('copy refused'));
}

document.addEventListener('click', (event) => {
  const button = event.target instanceof Element ? event.target.closest('[data-copy]') : null;
  const block = button?.parentElement?.querySelector('pre');
  if (!button || !block) return;
  copyText((block.textContent ?? '').trim()).then(
    () => (button.textContent = 'Copied'),
    () => (button.textContent = 'Copy failed'),
  ).finally(() => setTimeout(() => (button.textContent = 'Copy'), RESET_MS));
});
