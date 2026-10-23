<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'MOSAICO') ?> | MOSAICO</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/mosaico.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/navbar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/communities.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pieces.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/status-footer.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/buttons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/navigation.css') ?>">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="navbar">
    <a class="brand" href="<?= site_url('painel') ?>" aria-label="MOSAICO — Repositório de Ativos Digitais"><img src="<?= base_url('assets/logo/logo_mosaico.png') ?>" alt="MOSAICO repository"></a>
    <?php if (session('user_id')): ?>
        <?php $firstName = (preg_split('/\s+/u', trim((string) session('user_name'))) ?: [])[0] ?? ''; ?>
        <details class="user user-dropdown">
            <summary><?= esc($firstName !== '' ? $firstName : 'Usuário') ?></summary>
            <div class="user-dropdown-panel">
                <a href="<?= site_url('profile') ?>">Perfil</a>
                <form method="post" action="<?= site_url('logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit">Sair</button>
                </form>
            </div>
        </details>
    <?php endif ?>
</header>
<?php if (session('user_id')): ?>
<aside class="sidebar" aria-label="Navegação principal">
    <?php $selectedCommunityCookie = (string) service('request')->getCookie('mosaico_selected_community'); ?>
    <?php $selectedCollectionCookie = (string) service('request')->getCookie('mosaico_selected_collection'); ?>
    <?php $communityCount = db_connect()->table('projects')->where('status !=', 'excluded')->countAllResults(); ?>
    <?php $db = db_connect(); ?>
    <?php $selectedCommunity = $selectedCommunityCookie !== '' ? $db->table('projects')->select('id, name')->where('id', $selectedCommunityCookie)->where('status !=', 'excluded')->get()->getRowArray() : null; ?>
    <?php $collectionCount = $selectedCommunity ? $db->table('collections')->where('deleted_at', null)->where('project_id', $selectedCommunity['id'])->countAllResults() : 0; ?>
    <?php $selectedCollection = $selectedCollectionCookie !== '' && $selectedCommunity ? $db->table('collections')->where('deleted_at', null)->select('id, name')->where('id', $selectedCollectionCookie)->where('project_id', $selectedCommunity['id'])->get()->getRowArray() : null; ?>
    <nav>
        <a href="<?= site_url('painel') ?>">Visão geral</a>
        <?php if ($communityCount > 0): ?>
            <a href="<?= site_url('comunidades') ?>">Comunidade (<?= esc($communityCount) ?>)</a>
        <?php else: ?>
            <span class="nav-disabled" aria-disabled="true" title="Nenhuma comunidade cadastrada">Comunidade (0)</span>
        <?php endif ?>
        <?php if ($selectedCommunity): ?>
            <a class="nav-context-item" href="<?= site_url('comunidades/'.$selectedCommunity['id']) ?>">Coleções <span class="nav-count">(<?= esc($collectionCount) ?>)</span><span class="nav-context"><?= esc($selectedCommunity['name']) ?></span></a>
        <?php else: ?>
            <span class="nav-disabled" aria-disabled="true" title="Selecione uma comunidade para acessar as coleções">Coleções</span>
        <?php endif ?>
        <?php if ($selectedCollection): ?>
            <a class="nav-context-item" href="<?= site_url('colecoes/'.$selectedCollection['id']) ?>">Peças<span class="nav-context"><?= esc($selectedCollection['name']) ?></span></a>
        <?php else: ?>
            <span class="nav-disabled" aria-disabled="true" title="Selecione uma coleção para acessar as peças">Peças</span>
        <?php endif ?>
    </nav>
</aside>
<?php endif ?>
<main id="conteudo" class="content <?= esc($contentClass ?? '') ?> <?= session('user_id') ? '' : 'content-auth' ?>" tabindex="-1">
    <?= $this->renderSection('content') ?>
</main>
<footer class="footer">
    <span class="footer-signature">MOSAICO — preservação, acesso e rastreabilidade.</span>
    <div class="system-status" aria-live="polite" aria-atomic="true">
        <strong>Status:</strong>
        <?php if (session('warning')): ?>
            <span class="status-message status-warning" role="alert" tabindex="-1" data-status-warning><?= esc(session('warning')) ?></span>
        <?php elseif (session('success')): ?>
            <span class="status-message status-success" role="status"><?= esc(session('success')) ?></span>
        <?php elseif (session('error')): ?>
            <span class="status-message status-error" role="alert"><?= esc(session('error')) ?></span>
        <?php elseif (session('errors')): ?>
            <span class="status-message status-error" role="alert">Revise os dados: <?= esc(implode(' ', session('errors'))) ?></span>
        <?php else: ?>
            <span class="status-message status-idle">Sistema disponível.</span>
        <?php endif ?>
    </div>
</footer>
<script src="<?= base_url('assets/js/collections.js') ?>" defer></script>
<script src="<?= base_url('assets/js/user-menu.js') ?>" defer></script>
</body>
</html>
