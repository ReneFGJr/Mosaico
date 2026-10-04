<?= $this->extend('layouts/main') ?><?= $this->section('content') ?>
<nav class="breadcrumbs"><a href="<?= site_url('comunidades/'.$project['id']) ?>"><?= esc($project['name']) ?></a><span>/</span><span>Nova coleção</span></nav><h1>Nova coleção</h1>
<form method="post" class="form-card stack"><?= csrf_field() ?><label>Nome da coleção<input name="name" value="<?= esc(old('name')) ?>" required></label><label>Descrição<textarea name="description" rows="5"><?= esc(old('description')) ?></textarea></label><div class="actions"><button class="button">Criar coleção</button><a class="button cancel-button" href="<?= site_url('comunidades/'.$project['id']) ?>">Cancelar</a></div></form>
<?= $this->endSection() ?>
