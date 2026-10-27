<?php

namespace App\Libraries;

final class PieceListing
{
    public static function decorate(array $pieces): array
    {
        $keywordsByPiece = [];
        if ($pieces !== []) {
            $rows = db_connect()->table('piece_terms AS listing_links')
                ->select('listing_links.piece_id, listing_terms.id, listing_terms.label')
                ->join('pieces AS listing_pieces', 'listing_pieces.id = listing_links.piece_id')
                ->join('terms AS listing_terms', 'listing_terms.id = listing_links.term_id')
                ->join('vocabularies AS listing_vocabularies', 'listing_vocabularies.id = listing_terms.vocabulary_id AND listing_vocabularies.project_id = listing_pieces.project_id')
                ->where('listing_terms.is_active', true)->whereIn('listing_links.piece_id', array_column($pieces, 'id'))
                ->orderBy('listing_terms.label')->get()->getResultArray();
            foreach ($rows as $row) {
                $keywordsByPiece[$row['piece_id']][] = ['id' => $row['id'], 'label' => $row['label']];
            }
        }
        foreach ($pieces as &$piece) {
            $piece['keywords'] = $keywordsByPiece[$piece['id']] ?? [];
            $piece['display_state'] = match (true) {
                ($piece['access_condition'] ?? null) === 'restricted' => 'restricted',
                ($piece['access_condition'] ?? null) === 'embargoed' => 'embargoed',
                $piece['status'] === 'published' => 'published',
                default => 'draft',
            };
            $piece['display_state_label'] = match ($piece['display_state']) {
                'restricted' => 'Restrito',
                'embargoed' => 'Embargado',
                'published' => 'Publicado',
                default => 'Draft',
            };
            $piece['display_state_locked'] = in_array($piece['display_state'], ['restricted', 'embargoed'], true);
        }
        unset($piece);

        return $pieces;
    }
}
