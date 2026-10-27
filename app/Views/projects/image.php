<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<nav class="breadcrumbs" aria-label="Navegação estrutural"><a href="<?= site_url('comunidades') ?>">Comunidades</a><span>/</span><span>Trocar imagem</span></nav>
<header class="page-head"><div><h1>Imagem da comunidade</h1><p><?= esc($project['name']) ?></p></div></header>
<section class="form-card">
    <?php if (! empty($project['cover_image'])): ?>
        <img class="collection-cover" src="<?= site_url('comunidades/' . $project['id'] . '/imagem') ?>" alt="Imagem atual da comunidade">
    <?php endif ?>
    <form method="post" enctype="multipart/form-data" class="stack">
        <?= csrf_field() ?>
        <label>Imagem<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="image-help" required></label>
        <p id="image-help">Use JPEG, PNG, WebP ou GIF de até 5 MB e 40 megapixels.</p>
        <div><button class="button" type="submit">Salvar imagem</button> <a class="button secondary" href="<?= site_url('comunidades') ?>">Cancelar</a></div>
    </form>
</section>
<?= $this->endSection() ?>
