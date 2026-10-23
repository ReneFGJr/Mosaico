document.querySelectorAll('[data-describe-image]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        form.setAttribute('aria-busy', 'true');
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = form.dataset.progressLabel || 'Descrevendo imagem…';
        const status = document.querySelector('.system-status .status-message');
        if (status) {
            status.className = 'status-message status-idle';
            status.setAttribute('role', 'status');
            status.textContent = form.dataset.progressStatus || 'Gerando a descrição da imagem. Aguarde…';
        }
    });
});
