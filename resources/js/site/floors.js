// A dungeon's map shows one floor at a time: a floor button shows that floor's picture and hides the others.
// Without script the page hides the buttons and shows every floor, one under the other.
for (const map of document.querySelectorAll('.floors')) {
    const buttons = map.querySelectorAll('.sc-seg button[data-floor]');
    const floors = map.querySelectorAll('.floor');
    for (const button of buttons) {
        button.addEventListener('click', () => {
            buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            floors.forEach((floor) => {
                floor.hidden = floor.dataset.floor !== button.dataset.floor;
            });
        });
    }
}
