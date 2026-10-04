<?= $this->extend('layouts/main') ?><?= $this->section('content') ?>
<p class="eyebrow">Organização</p><h1>Nova comunidade</h1><form method="post" class="form-card stack"><?= csrf_field() ?>
<div class="form-row"><label>Acrônimo<input name="code" value="<?= esc(old('code')) ?>" maxlength="80" required placeholder="Ex.: MSA-2026"></label><label>Nome<input name="name" value="<?= esc(old('name')) ?>" maxlength="190" required></label></div>
<label>Descrição<textarea name="description" rows="5"><?= esc(old('description')) ?></textarea></label><div class="actions"><button class="button" type="submit">Criar comunidade</button><a href="<?= site_url('comunidades') ?>">Cancelar</a></div></form>
<?= $this->endSection() ?>
