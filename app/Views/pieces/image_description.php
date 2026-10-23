<?php if ($previewFile && $previewType === 'image'): ?>
    <section class="image-description" aria-labelledby="image-description-title">
        <h2 id="image-description-title">Descrição da imagem</h2>
        <?php if ($canManageKeywords): ?>
            <form method="post" action="<?= site_url('pecas/' . $piece['id'] . '/descrever-imagem') ?>" data-describe-image>
                <?= csrf_field() ?>
                <input type="hidden" name="source_file_id" value="<?= esc($previewFile['id']) ?>">
                <button type="submit" class="button">Descrever Imagem</button>
            </form>
        <?php endif ?>
        <?php if ($imageDescription): ?>
            <p class="form-help">Descrição gerada por IA. Revise o texto antes de utilizá-lo.</p>
            <p id="image-description-text" class="image-description-text"><?= nl2br(esc($imageDescription['description'])) ?></p>
        <?php else: ?>
            <p class="muted">Esta imagem ainda não possui uma descrição.</p>
        <?php endif ?>
    </section>
    <?= $this->include('pieces/people_detection') ?>
    <script src="<?= base_url('assets/js/image-description.js') ?>" defer></script>
<?php endif ?>
