document.addEventListener('DOMContentLoaded', () => {
    const uploadWarning = document.querySelector('[data-status-warning]');
    if (uploadWarning) {
        uploadWarning.focus({ preventScroll: true });
        uploadWarning.scrollIntoView({ block: 'nearest' });
    }

    const uploadForm = document.querySelector('[data-upload-form]');
    const uploadDialog = document.querySelector('[data-upload-processing]');
    if (uploadForm && uploadDialog) {
        const submitButton = uploadForm.querySelector('button[type="submit"]');
        let uploading = false;
        uploadDialog.addEventListener('cancel', (event) => event.preventDefault());
        uploadForm.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            if (uploading) {
                event.preventDefault();
                return;
            }
            uploading = true;
            uploadDialog.showModal();
            uploadDialog.querySelector('h2').focus();
            document.body.classList.add('upload-in-progress');
            uploadForm.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
        });
        window.addEventListener('pageshow', () => {
            uploading = false;
            uploadDialog.close();
            document.body.classList.remove('upload-in-progress');
            uploadForm.removeAttribute('aria-busy');
            submitButton.disabled = false;
        });
    }

    document.querySelectorAll('[data-drop-zone]').forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        const list = zone.parentElement.querySelector('[data-file-list]');
        const renderFiles = () => {
            list.replaceChildren(...[...input.files].map((file) => {
                const item = document.createElement('li');
                item.textContent = file.name;
                return item;
            }));
        };
        ['dragenter', 'dragover'].forEach((eventName) => zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragging');
        }));
        ['dragleave', 'drop'].forEach((eventName) => zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragging');
        }));
        zone.addEventListener('drop', (event) => {
            if (event.dataTransfer.files.length > 0) {
                input.files = event.dataTransfer.files;
                renderFiles();
            }
        });
        input.addEventListener('change', renderFiles);
    });

    document.querySelectorAll('[data-piece-selection]').forEach((form) => {
        const toggle = form.querySelector('[data-select-all]');
        const items = [...form.querySelectorAll('input[name="piece_ids[]"]')];
        if (!toggle) return;

        toggle.addEventListener('change', () => {
            items.forEach((item) => {
                if (!item.disabled) item.checked = toggle.checked;
            });
        });

        items.forEach((item) => item.addEventListener('change', () => {
            const enabled = items.filter((candidate) => !candidate.disabled);
            toggle.checked = enabled.length > 0 && enabled.every((candidate) => candidate.checked);
            toggle.indeterminate = enabled.some((candidate) => candidate.checked) && !toggle.checked;
        }));

        if (form.hasAttribute('data-bulk-actions')) {
            form.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                if (submitter && submitter.name === 'scope') return;
                const action = form.querySelector('[name="bulk_action"]').value;
                if (action === 'remove' && !window.confirm('Remover as peças selecionadas em Draft desta coleção?')) {
                    event.preventDefault();
                }
            });
        }
    });

    const driveDialog = document.querySelector('[data-drive-dialog]');
    const driveForm = document.querySelector('[data-drive-form]');
    if (driveDialog && driveForm) {
        const progressPanel = driveDialog.querySelector('[data-drive-progress]');
        const fileList = driveDialog.querySelector('[data-drive-files]');
        const summary = driveDialog.querySelector('[data-drive-summary]');
        const submitButton = driveForm.querySelector('button[type="submit"]');
        const finishButton = driveDialog.querySelector('[data-drive-finish]');
        const fileItems = new Map();
        document.querySelector('[data-drive-open]').addEventListener('click', () => {
            driveDialog.showModal();
            driveForm.querySelector('input[name="drive_url"]').focus();
        });
        driveDialog.querySelector('[data-drive-cancel]').addEventListener('click', () => driveDialog.close());
        finishButton.addEventListener('click', () => window.location.reload());
        const renderFile = (event) => {
            let item = fileItems.get(event.id);
            if (!item) {
                item = document.createElement('li');
                item.className = 'drive-file';
                const row = document.createElement('div');
                row.className = 'drive-file-row';
                const name = document.createElement('span');
                name.className = 'drive-file-name';
                name.textContent = event.name;
                const status = document.createElement('span');
                status.className = 'drive-file-status';
                row.append(name, status);
                const bar = document.createElement('progress');
                bar.max = 100;
                bar.value = 0;
                item.append(row, bar);
                fileList.append(item);
                fileItems.set(event.id, item);
            }
            const status = item.querySelector('.drive-file-status');
            const bar = item.querySelector('progress');
            if (event.status === 'downloading') {
                status.textContent = `Baixando ${event.progress || 0}%`;
                bar.value = event.progress || 0;
            } else if (event.status === 'imported') {
                item.classList.add('is-imported');
                status.textContent = 'Importado';
                bar.value = 100;
                bar.hidden = true;
            } else if (event.status === 'error') {
                item.classList.add('is-error');
                status.textContent = `Erro: ${event.message}`;
                bar.hidden = true;
            } else {
                status.textContent = 'Aguardando';
            }
        };
        driveForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            submitButton.disabled = true;
            progressPanel.hidden = false;
            progressPanel.classList.add('is-busy');
            finishButton.hidden = true;
            fileList.replaceChildren();
            fileItems.clear();
            summary.textContent = 'Consultando a pasta do Google Drive…';
            try {
                const response = await fetch(driveForm.action, { method: 'POST', body: new FormData(driveForm), headers: { Accept: 'application/x-ndjson', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok || !response.body) throw new Error('O servidor não iniciou a importação.');
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let importComplete = false;
                while (true) {
                    const { value, done } = await reader.read();
                    buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';
                    for (const line of lines) {
                        if (!line.trim()) continue;
                        let update;
                        try {
                            update = JSON.parse(line);
                        } catch {
                            throw new Error('O servidor interrompeu o fluxo da importação. Consulte o Status e tente novamente.');
                        }
                        if (update.type === 'discovered') {
                            summary.textContent = `${update.files.length} arquivo(s) encontrado(s).`;
                            update.files.forEach((file) => renderFile({ ...file, status: 'waiting' }));
                        } else if (update.type === 'file') {
                            renderFile(update);
                        } else if (update.type === 'complete') {
                            summary.textContent = update.message;
                            progressPanel.classList.remove('is-busy');
                            finishButton.hidden = false;
                            importComplete = true;
                            break;
                        } else if (update.type === 'fatal') {
                            summary.textContent = `Erro: ${update.message}`;
                            progressPanel.classList.remove('is-busy');
                            importComplete = true;
                            break;
                        }
                    }
                    if (importComplete) {
                        await reader.cancel();
                        break;
                    }
                    if (done) break;
                }
                if (!importComplete) throw new Error('A importação foi interrompida antes da conclusão.');
            } catch (error) {
                progressPanel.classList.remove('is-busy');
                summary.textContent = `Erro: ${error.message}`;
            } finally {
                submitButton.disabled = false;
            }
        });
    }
});
