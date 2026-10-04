<?php /** @var list<array{label: string, url?: string}> $breadcrumbs */ ?>
<nav class="breadcrumbs" aria-label="Caminho de navegação">
    <ol>
        <?php foreach (($breadcrumbs ?? []) as $index => $breadcrumb): ?>
            <li>
                <?php if (isset($breadcrumb['url']) && $index < count($breadcrumbs) - 1): ?>
                    <a href="<?= esc($breadcrumb['url']) ?>"><?= esc($breadcrumb['label']) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= esc($breadcrumb['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<main class="page-shell">