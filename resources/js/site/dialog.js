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
        const dialog = event.target;
        if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('[closedby="any"]')) {
            return;
        }
        const box = dialog.getBoundingClientRect();
        const inside = box.top <= event.clientY && event.clientY <= box.bottom && box.left <= event.clientX && event.clientX <= box.right;
        if (!inside) {
            dialog.close();
        }
    });
}
