const openerOf = (dialog) => document.querySelector(`[data-dialog-open="${dialog.id}"]`);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (target === null) return;
    const opener = target.closest('[data-dialog-open]');
    if (opener instanceof HTMLElement) {
        const dialog = document.getElementById(opener.dataset.dialogOpen ?? '');
        if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
        return;
    }
    if (target.closest('[data-dialog-close]')) {
        target.closest('dialog')?.close();
        return;
    }
    if (target instanceof HTMLDialogElement && target.classList.contains('sc-dialog')) target.close();
});

document.addEventListener('close', (event) => {
    if (event.target instanceof HTMLDialogElement) openerOf(event.target)?.focus();
}, true);
