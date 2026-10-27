document.querySelectorAll('[data-keyword-autosave]').forEach((form) => {
    const checkboxes = [...form.querySelectorAll('input[name="term_ids[]"]')];
    let saved = checkboxes.map((input) => input.checked);
    let saving = false;
    const status = (message, error = false) => {
        const area = document.querySelector('.system-status');
        if (!area) return;
        let output = area.querySelector('.status-message');
        if (!output) {
            output = document.createElement('span');
            area.append(output);
        }
        output.className = `status-message ${error ? 'status-error' : 'status-success'}`;
        output.setAttribute('role', error ? 'alert' : 'status');
        output.textContent = message;
    };
    form.addEventListener('change', async (event) => {
        if (!checkboxes.includes(event.target) || saving) return;
        saving = true;
        const data = new FormData();
        const token = form.querySelector('input[type="hidden"]');
        data.append(token.name, token.value);
        data.append('autosave', '1');
        checkboxes.forEach((input) => {
            if (input.checked) data.append('term_ids[]', input.value);
            input.disabled = true;
        });
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        buttons.forEach((button) => { button.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        status('Salvando palavras-chave…');
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Não foi possível salvar. Atualize a página e tente novamente.');
            }
            const result = await response.json();
            if (result.csrfName && result.csrfHash) {
                document.querySelectorAll('input[type="hidden"]').forEach((input) => {
                    if (input.name === result.csrfName) input.value = result.csrfHash;
                });
            }
            if (!response.ok) throw new Error(result.message);
            saved = checkboxes.map((input) => input.checked);
            status(result.message);
        } catch (error) {
            checkboxes.forEach((input, index) => { input.checked = saved[index]; });
            status(error.message || 'Não foi possível salvar as palavras-chave.', true);
        } finally {
            checkboxes.forEach((input) => { input.disabled = false; });
            buttons.forEach((button) => { button.disabled = false; });
            form.removeAttribute('aria-busy');
            saving = false;
        }
    });
});
