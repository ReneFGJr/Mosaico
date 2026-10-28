<?php if ($canManageCollections): ?>
    <div class="collection-card-actions">
        <details class="action-menu">
            <summary aria-label="Ações de <?= esc($collection['name']) ?>">Ações</summary>
            <div class="action-menu-panel">
                <a href="<?= site_url('comunidades/' . $collection['project_id'] . '/vocabulario') . '?collection=' . esc($collection['id']) ?>">Vocabulário controlado</a>
                <a href="<?= site_url('colecoes/' . $collection['id'] . '/membros') ?>">Membros</a>
                <a href="<?= site_url('colecoes/' . $collection['id'] . '/editar') ?>">Editar</a>
                <a href="<?= site_url('colecoes/' . $collection['id'] . '/trocar-imagem') ?>">Trocar imagem</a>
                <a class="danger-action" href="<?= site_url('colecoes/' . $collection['id'] . '/deletar') ?>">Deletar</a>
            </div>
        </details>
    </div>
<?php endif ?>
