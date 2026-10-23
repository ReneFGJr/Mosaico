document.addEventListener('click', (event) => {
    const menu = document.querySelector('.user-dropdown');
    if (menu && !menu.contains(event.target)) menu.open = false;
});

document.addEventListener('keydown', (event) => {
    const menu = document.querySelector('.user-dropdown[open]');
    if (event.key === 'Escape' && menu) {
        menu.open = false;
        menu.querySelector('summary').focus();
    }
});
