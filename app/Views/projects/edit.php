<?= $this->extend('layouts/main') ?><?= $this->section('content') ?>
<nav class="breadcrumbs" aria-label="Navegação estrutural"><a href="<?= site_url('comunidades') ?>">Comunidades</a><span>/</span><a href="<?= site_url('comunidades/'.$project['id']) ?>"><?= esc($project['name']) ?></a><span>/</span><span>Editar dados</span></nav>
<p class="eyebrow">Organização</p><h1>Editar comunidade</h1>
<form method="post" class="form-card stack"><?= csrf_field() ?>
<div class="form-row"><label>Código<input name="code" value="<?= esc(old('code', $project['code'])) ?>" maxlength="80" required></label><label>Nome<input name="name" value="<?= esc(old('name', $project['name'])) ?>" maxlength="190" required></label></div>
<label>Descrição<textarea name="description" rows="5"><?= esc(old('description', $project['description'])) ?></textarea></label>
<div class="actions"><button class="button" type="submit">Salvar alterações</button><a href="<?= site_url('comunidades/'.$project['id']) ?>">Cancelar</a></div></form>
<?= $this->endSection() ?>
