const commands = { 'show-modal': (dialog) => dialog.showModal(), close: (dialog) => dialog.close() };

if (!('commandForElement' in HTMLButtonElement.prototype)) {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button[commandfor]') : null;
        const run = button ? commands[button.getAttribute('command')] : null;
        const dialog = button ? document.getElementById(button.getAttribute('commandfor')) : null;
        if (run && dialog instanceof HTMLDialogElement) {
            run(dialog);
        }
    });
}

if (!('closedBy' in HTMLDialogElement.prototype)) {
    document.addEventListener('click', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.matches('[closedby="any"]')) {
            event.target.close();
        }
    });
}
