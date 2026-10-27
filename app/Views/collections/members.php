<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<nav class="breadcrumbs" aria-label="Navegação estrutural"><a href="<?= site_url('comunidades/' . $collection['project_id']) ?>">Comunidade</a><span>/</span><a href="<?= site_url('colecoes/' . $collection['id']) ?>"><?= esc($collection['name']) ?></a><span>/</span><span>Membros</span></nav>
<header class="page-head"><div><h1>Membros</h1><p><?= esc($collection['name']) ?></p></div></header>
<div class="two-cols">
<section class="panel"><h2>Adicionar ou alterar membro</h2>
<p>Visualizar permite consultar o catálogo. Adicionar novos documentos também permite enviar arquivos, que entram como rascunhos. Nenhum desses privilégios permite publicar.</p>
<p>Permissões de administração já concedidas na comunidade continuam válidas.</p>
<form method="post" class="stack">
<?= csrf_field() ?>
<label>Usuário<select name="user_id" required><option value="">Selecione</option><?php foreach ($users as $user): ?><option value="<?= esc($user['id']) ?>"><?= esc($user['display_name']) ?> &mdash; <?= esc($user['email']) ?></option><?php endforeach ?></select></label>
<label>Privilégio<select name="privilege" required><option value="view">Visualizar</option><option value="add">Adicionar novos documentos</option></select></label>
<div><button type="submit" class="button">Salvar membro</button> <a class="button secondary" href="<?= site_url('comunidades/' . $collection['project_id']) ?>">Cancelar</a></div>
</form></section>
<section class="panel"><h2>Membros da coleção</h2>
<?php if (! $members): ?><p>Nenhum membro cadastrado.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Usuário</th><th>Privilégio</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($members as $member): ?><tr><td><?= esc($member['display_name']) ?><br><?= esc($member['email']) ?></td><td><?= $member['privilege'] === 'add' ? 'Adicionar novos documentos' : 'Visualizar' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= esc($member['id']) ?>"><input type="hidden" name="privilege" value="remove"><button type="submit" class="button secondary">Remover</button></form></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?></section></div>
<?= $this->endSection() ?>
