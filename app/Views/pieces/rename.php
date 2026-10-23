<?php if ($canManageKeywords): ?>
    <details class="panel piece-rename" <?= session('rename_form_open') ? 'open' : '' ?>>
        <summary>Editar nome / nome do arquivo</summary>
        <form method="post" action="<?= site_url('pecas/' . $piece['id'] . '/nome') ?>" class="stack">
            <?= csrf_field() ?>
            <label>Nome da peça<input type="text" name="title" value="<?= esc(old('title', $piece['title'])) ?>" maxlength="255" required></label>
            <?php if ($previewFile): ?>
                <input type="hidden" name="source_file_id" value="<?= esc($previewFile['id']) ?>">
                <label>Nome do arquivo<input type="text" name="filename" value="<?= esc(old('filename', $piece['original_filename'] ?: $previewFile['original_name'])) ?>" maxlength="255" aria-describedby="rename-file-help" required></label>
                <p id="rename-file-help" class="form-help">Mantenha a extensão do arquivo. O novo nome será usado na exibição e na pré-visualização.</p>
            <?php endif ?>
            <button type="submit" class="button">Salvar nomes</button>
        </form>
    </details>
<?php endif ?>
