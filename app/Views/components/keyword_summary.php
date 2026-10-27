<?php
$filterContext = $keywordFilter ?? null;
if (! $filterContext) {
    $contextProjectId = $piece['project_id'] ?? $project['id'] ?? $selectedCommunity['id'] ?? null;
    $contextCollectionId = $selectedCollection['id'] ?? null;
    if ($contextProjectId && $contextCollectionId && ($selectedCommunity['id'] ?? null) !== $contextProjectId) {
        $contextCollectionId = null;
    }
    $filterContext = $contextProjectId ? \App\Libraries\KeywordFilter::context($contextProjectId, $contextCollectionId) : null;
}
?>
<?php if ($filterContext): ?>
<section class="sidebar-keywords" aria-labelledby="sidebar-keywords-title">
    <h2 id="sidebar-keywords-title">Palavras-chave</h2>
    <?php if ($filterContext['terms']): ?>
    <form method="get" action="<?= esc($filterContext['url']) ?>">
        <input type="hidden" name="keyword_filter" value="1">
        <?php if (! $filterContext['collection_id']): ?><input type="hidden" name="project" value="<?= esc($filterContext['project_id']) ?>"><?php endif ?>
        <?php if (isset($viewMode)): ?><input type="hidden" name="view" value="<?= esc($viewMode) ?>"><?php endif ?>
        <?php if (isset($query) && $query !== ''): ?><input type="hidden" name="q" value="<?= esc($query) ?>"><?php endif ?>
        <p>Mostrar peças com todas as palavras selecionadas.</p>
        <?php $category = null; ?>
        <?php foreach ($filterContext['terms'] as $term): ?>
            <?php if ($category !== $term['vocabulary_name']): ?>
                <?php if ($category !== null): ?></fieldset><?php endif ?>
                <?php $category = $term['vocabulary_name']; ?><fieldset><legend><?= esc($category) ?></legend>
            <?php endif ?>
            <label><input type="checkbox" name="terms[]" value="<?= esc($term['id']) ?>" <?= in_array($term['id'], $filterContext['selected'], true) ? 'checked' : '' ?>><span><?= esc($term['label']) ?> <small>(<?= esc($term['total']) ?>)</small></span></label>
        <?php endforeach ?></fieldset>
        <button type="submit" class="button">Aplicar filtros</button>
        <a class="keyword-clear" href="<?= esc($filterContext['url'] . '?' . http_build_query(['keyword_filter' => '1', 'project' => $filterContext['collection_id'] ? null : $filterContext['project_id'], 'view' => $viewMode ?? null, 'q' => $query ?? null])) ?>">Limpar filtros</a>
    </form>
    <?php else: ?><p>Nenhuma palavra-chave cadastrada nesta comunidade.</p><?php endif ?>
</section>
<?php endif ?>
