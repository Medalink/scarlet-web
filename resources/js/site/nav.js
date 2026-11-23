// Close header Explore disclosure menus on outside click or Escape.
const navMenus = [...document.querySelectorAll('details.nav-more')];
if (navMenus.length > 0) {
    document.addEventListener('click', (event) => {
        for (const menu of navMenus) {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        for (const menu of navMenus) {
            if (menu.open) {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            }
        }
    });
}
