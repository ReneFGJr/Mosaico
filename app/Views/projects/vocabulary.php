<?= $this->extend('layouts/main') ?><?= $this->section('content') ?>
<nav class="breadcrumbs"><a href="<?= site_url('comunidades/'.$project['id']) ?>"><?= esc($project['name']) ?></a><span>/</span><span>Vocabulário controlado</span></nav>
<h1>Vocabulário controlado</h1>
<p>Cadastre termos e seus labels preferidos. Exemplo: <strong>#I → Indigena</strong>. Salvar ou excluir uma regra não altera as fotos; use a substituição em lote para aplicar o vocabulário.</p>
<section class="panel"><h2>Termos cadastrados</h2>
<?php if (! $rules): ?><p>Nenhum termo cadastrado.</p><?php else: ?>
<div style="overflow-x:auto"><table><thead><tr><th>Termo original</th><th>Substituir por</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($rules as $rule): ?>
<tr><td><input form="rule-<?= esc($rule['id']) ?>" aria-label="Termo original" name="source" value="<?= esc($rule['source']) ?>" maxlength="80" required></td>
<td><input form="rule-<?= esc($rule['id']) ?>" aria-label="Substituir por" name="replacement" value="<?= esc($rule['replacement']) ?>" maxlength="190" required></td>
<td><form id="rule-<?= esc($rule['id']) ?>" method="post" action="<?= current_url() ?>"><?= csrf_field() ?><input type="hidden" name="rule_id" value="<?= esc($rule['id']) ?>"><button class="button" name="action" value="save">Salvar</button><button class="button secondary" name="action" value="delete" formnovalidate onclick="return confirm('Excluir esta regra do vocabulário? As fotos já alteradas serão mantidas.')">Excluir</button></form></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
</section>
<form method="post" action="<?= current_url() ?>" class="form-card stack">
<?= csrf_field() ?><input type="hidden" name="action" value="save"><h2>Novo termo</h2>
<div class="form-row"><label>Termo original<input name="source" maxlength="80" placeholder="#I" required></label><label>Substituir por<input name="replacement" maxlength="190" placeholder="Indigena" required></label></div>
<button class="button" type="submit">Adicionar termo</button></form>
<form method="post" action="<?= current_url() ?>" class="form-card stack" onsubmit="return confirm('Aplicar todas as regras às fotos do escopo selecionado? Esta ação altera os labels salvos.')">
<?= csrf_field() ?><input type="hidden" name="action" value="apply"><h2>Substituir em lote</h2>
<p>A substituição distingue maiúsculas e minúsculas e respeita os limites das palavras: #I não altera #II. Cada regra é aplicada uma vez por execução. Arquivos originais e nomes de arquivo são preservados.</p>
<label>Aplicar em<select name="collection_id"><option value="">Toda a comunidade</option><?php foreach ($collections as $collection): ?><option value="<?= esc($collection['id']) ?>" <?= $selectedCollection === $collection['id'] ? 'selected' : '' ?>>Coleção: <?= esc($collection['name']) ?></option><?php endforeach ?></select></label>
<label>Labels a substituir<select name="target"><option value="tags">Palavras-chave (tags)</option><option value="title">Título/legenda das fotos</option><option value="both">Palavras-chave e título/legenda</option></select></label>
<p>Fotos compartilhadas entre coleções terão seus labels atualizados em todos os locais onde aparecem.</p>
<button class="button" type="submit" <?= ! $rules ? 'disabled' : '' ?>>Substituir tudo no escopo selecionado</button></form>
<?= $this->endSection() ?>
