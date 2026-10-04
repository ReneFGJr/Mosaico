<?= $this->extend('layouts/main') ?><?= $this->section('content') ?>
<div class="page-head"><div><p class="eyebrow">Visão geral</p><h1>Painel do repositório</h1><p>Acompanhe o acervo e continue o trabalho recente.</p></div></div>
<section class="stats" aria-label="Indicadores">
<?php foreach ([['projects','Comunidades'],['collections','Coleções'],['pieces','Peças'],['assets','Ativos'],['asset_versions','Versões']] as [$key,$label]): ?><article><strong><?= esc($counts[$key]) ?></strong><span><?= esc($label) ?></span></article><?php endforeach ?>
</section>
<section class="panel"><div class="section-head"><h2>Peças recentes</h2><a href="<?= site_url('pecas') ?>">Ver catálogo</a></div>
<?php if ($recent): ?><div class="table-wrap"><table><thead><tr><th>Código</th><th>Título</th><th>Comunidade</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $piece): ?><tr><td><?= esc($piece['code']) ?></td><td><a href="<?= site_url('pecas/'.$piece['id']) ?>"><?= esc($piece['title']) ?></a></td><td><?= esc($piece['project_name']) ?></td><td><span class="badge"><?= esc($piece['status']) ?></span></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><div class="empty"><h3>O acervo começa aqui</h3><p>Nenhuma peça cadastrada até o momento.</p></div><?php endif ?></section>
<?= $this->endSection() ?>
