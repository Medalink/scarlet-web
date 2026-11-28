const RESET_MS = 1600;

function copyBySelection(text) {
  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', '');
  area.style.position = 'fixed';
  area.style.opacity = '0';
  document.body.append(area);
  try {
    area.select();
    if (!document.execCommand('copy')) throw new Error('copy refused');
  } finally {
    area.remove();
  }
}

function copyText(text) {
  return Promise.resolve()
    .then(() => navigator.clipboard.writeText(text))
    .catch(() => copyBySelection(text));
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
