<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<nav class="breadcrumbs"><a href="<?= site_url('comunidades/' . $collection['project_id']) ?>"><?= esc($collection['community_name']) ?></a><span>/</span><span><?= esc($collection['name']) ?></span></nav>
<h1><?= esc($title) ?></h1>
<form method="post" class="form-card stack" <?= $mode === 'image' ? 'enctype="multipart/form-data"' : '' ?>>
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <label>Nome da coleção<input name="name" maxlength="190" value="<?= esc(old('name', $collection['name'])) ?>" required></label>
        <label>Descrição<textarea name="description" rows="5"><?= esc(old('description', $collection['description'])) ?></textarea></label>
    <?php elseif ($mode === 'image'): ?>
        <p>Coleção: <strong><?= esc($collection['name']) ?></strong></p>
        <label>Nova imagem<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif" required><span class="form-help">JPEG, PNG, WebP ou GIF, até 5 MB e 40 megapixels.</span></label>
    <?php else: ?>
        <p>Deseja deletar a coleção <strong><?= esc($collection['name']) ?></strong>?</p>
        <p>A coleção será ocultada da navegação. Suas peças, arquivos e histórico serão preservados.</p>
        <input type="hidden" name="confirmed" value="1">
    <?php endif ?>
    <div class="actions">
        <button class="button" type="submit"><?= $mode === 'delete' ? 'Confirmar exclusão' : 'Salvar' ?></button>
        <a class="button cancel-button" href="<?= site_url('comunidades/' . $collection['project_id']) ?>">Cancelar</a>
    </div>
</form>
<?= $this->endSection() ?>
