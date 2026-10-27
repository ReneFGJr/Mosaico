<?php

namespace App\Libraries;

final class KeywordFilter
{
    public static function context(string $projectId, ?string $collectionId = null): array
    {
        $db = db_connect();
        $terms = $db->table('terms')->select('terms.id, terms.label, vocabularies.name AS vocabulary_name')
            ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')->where('vocabularies.project_id', $projectId)
            ->where('terms.is_active', true)->orderBy('vocabularies.name')->orderBy('terms.label')->get()->getResultArray();
        $counts = $db->table('piece_terms AS keyword_links')->select('keyword_links.term_id, COUNT(DISTINCT keyword_pieces.id) AS total')
            ->join('pieces AS keyword_pieces', 'keyword_pieces.id = keyword_links.piece_id')->where('keyword_pieces.project_id', $projectId);
        if ($collectionId !== null) {
            $counts->join('piece_collections', 'piece_collections.piece_id = keyword_pieces.id')->where('piece_collections.collection_id', $collectionId);
        }
        $totals = array_column($counts->groupBy('keyword_links.term_id')->get()->getResultArray(), 'total', 'term_id');
        foreach ($terms as &$term) {
            $term['total'] = (int) ($totals[$term['id']] ?? 0);
        }
        unset($term);
        $scope = $collectionId !== null ? 'collection:' . $collectionId : 'project:' . $projectId;
        $request = service('request');
        $saved = (array) session('keyword_filters');
        $submitted = $request->getGet('terms');
        $legacy = $request->getGet('term');
        $explicit = $request->getGet('keyword_filter') === '1' || $submitted !== null || $legacy !== null;
        $selected = $explicit ? ($submitted ?? ($legacy !== null ? [$legacy] : [])) : ($saved[$scope] ?? []);
        $selected = array_values(array_intersect(array_column($terms, 'id'), array_filter((array) $selected, 'is_string')));
        if ($explicit) {
            $saved[$scope] = $selected;
            session()->set('keyword_filters', $saved);
        }
        return ['terms' => $terms, 'selected' => $selected, 'project_id' => $projectId, 'collection_id' => $collectionId,
            'url' => $collectionId !== null ? site_url('colecoes/' . $collectionId) : site_url('pecas')];
    }

    public static function apply($builder, array $selected): void
    {
        $db = db_connect();
        foreach ($selected as $termId) {
            $builder->whereIn('pieces.id', $db->table('piece_terms')->select('piece_id')->where('term_id', $termId));
        }
    }
}
