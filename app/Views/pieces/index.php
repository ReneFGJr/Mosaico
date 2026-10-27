<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$termProjects = [];
foreach ($pieces as $resultPiece) {
    foreach ($resultPiece['keywords'] as $keyword) $termProjects[$keyword['id']] = $resultPiece['project_id'];
}
$urlFor = static function (array $changes = []) use ($viewMode, $keywordFilter, $query, $termProjects): string {
    $params = ['view' => $viewMode, 'project' => $keywordFilter['project_id'] ?? null,
        'keyword_filter' => '1', 'terms' => $keywordFilter['selected'] ?? [], 'q' => $query !== '' ? $query : null];
    foreach ($changes as $key => $value) {
        if ($key === 'term') {
            $params['terms'] = $value ? [$value] : [];
            if ($value) $params['project'] = $termProjects[$value] ?? $params['project'];
        } else {
            $params[$key] = $value;
        }
    }
    return site_url('pecas') . '?' . http_build_query(array_filter($params, static fn ($value) => $value !== null && $value !== ''));
};
$selectedLabels = $keywordFilter ? array_column(array_filter($keywordFilter['terms'], static fn ($term) => in_array($term['id'], $keywordFilter['selected'], true)), 'label') : [];
?>
<div class="page-head">
    <div><p class="eyebrow">Catálogo</p><h1>Peças<?= $project ? ' — ' . esc($project['name']) : '' ?></h1><p>Encontre e acompanhe os produtos informacionais.</p></div>
    <a class="button" href="<?= site_url('pecas/nova') . ($project ? '?project=' . $project['id'] : '') ?>">Nova peça</a>
</div>
<section aria-labelledby="pieces-title">
    <div class="section-head collection-toolbar">
        <h2 id="pieces-title">Peças (<?= count($pieces) ?>)</h2>
        <nav class="view-switcher" aria-label="Modo de visualização">
            <?php foreach (['list' => 'Lista', 'details' => 'Detalhes', 'thumbnail' => 'Miniatura', 'cards' => 'Cards'] as $mode => $label): ?>
                <a class="<?= $viewMode === $mode ? 'active' : '' ?>" href="<?= esc($urlFor(['view' => $mode])) ?>"><?= esc($label) ?></a>
            <?php endforeach ?>
        </nav>
    </div>
    <form class="collection-search" method="get" action="<?= site_url('pecas') ?>" role="search">
        <input type="hidden" name="view" value="<?= esc($viewMode) ?>">
        <?php if ($keywordFilter): ?>
            <input type="hidden" name="project" value="<?= esc($keywordFilter['project_id']) ?>">
            <input type="hidden" name="keyword_filter" value="1">
            <?php foreach ($keywordFilter['selected'] as $termId): ?><input type="hidden" name="terms[]" value="<?= esc($termId) ?>"><?php endforeach ?>
        <?php endif ?>
        <label for="q">Buscar peças</label>
        <div><input id="q" name="q" value="<?= esc($query) ?>" placeholder="Título, código ou descrição"><button class="button" type="submit">Buscar</button><?php if ($query !== ''): ?><a class="button secondary" href="<?= esc($urlFor(['q' => null])) ?>">Limpar busca</a><?php endif ?></div>
    </form>
    <?php if ($selectedLabels): ?>
        <div class="active-filter" role="status"><span>Filtros: <strong><?= esc(implode(', ', $selectedLabels)) ?></strong></span><a href="<?= esc($urlFor(['term' => null])) ?>">Limpar filtros</a></div>
    <?php endif ?>
    <?php if ($pieces): ?>
        <?= view('components/piece_results', ['pieces' => $pieces, 'viewMode' => $viewMode, 'urlFor' => $urlFor, 'selectable' => false]) ?>
    <?php else: ?><p class="empty">Nenhuma peça encontrada.</p><?php endif ?>
</section>
<?= $this->endSection() ?>
