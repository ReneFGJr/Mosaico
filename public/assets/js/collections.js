document.addEventListener('DOMContentLoaded', () => {
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
    });
});
