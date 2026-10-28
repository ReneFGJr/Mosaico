<?php

namespace App\Libraries;

use App\Models\UuidModel;

final class LabelReplacer
{
    public function replace(string $text, array $rules): string
    {
        if ($rules === []) return $text;
        $map = array_column($rules, 'replacement', 'source');
        uksort($map, static fn ($a, $b) => strlen($b) <=> strlen($a));
        $pattern = '~(?<![\p{L}\p{N}_])(?:' . implode('|', array_map(static fn ($s) => preg_quote($s, '~'), array_keys($map))) . ')(?![\p{L}\p{N}_])~u';
        return preg_replace_callback($pattern, static fn ($match) => $map[$match[0]], $text);
    }

    public function apply(string $projectId, ?string $collectionId, string $target): int
    {
        $db = db_connect();
        $rules = $db->table('label_replacements')->where('project_id', $projectId)->get()->getResultArray();
        $query = $db->table('pieces')->select('pieces.id, pieces.title')->distinct()->where('pieces.project_id', $projectId)->where('pieces.type', 'imagem')->where('pieces.status !=', 'deleted');
        if ($collectionId !== null) $query->join('piece_collections', 'piece_collections.piece_id = pieces.id')->where('piece_collections.collection_id', $collectionId);
        $pieces = $query->get()->getResultArray();
        $count = 0;
        $db->transException(true)->transStart();
        foreach ($pieces as $piece) {
            $changed = false;
            if (in_array($target, ['title', 'both'], true)) {
                $title = $this->replace($piece['title'], $rules);
                if (mb_strlen($title) > 190) throw new \RuntimeException('Uma substituição excede o limite de 190 caracteres do título.');
                if ($title !== $piece['title']) {
                    $db->table('pieces')->where('id', $piece['id'])->update(['title' => $title, 'updated_at' => date('Y-m-d H:i:s')]);
                    $changed = true;
                }
            }
            if (in_array($target, ['tags', 'both'], true)) {
                $terms = $db->table('piece_terms')->select('terms.*')->join('terms', 'terms.id = piece_terms.term_id')->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')->where('piece_id', $piece['id'])->where('vocabularies.project_id', $projectId)->get()->getResultArray();
                $preferredIds = [];
                $removedIds = [];
                foreach ($terms as $term) {
                    $label = $this->replace($term['label'], $rules);
                    if ($label === $term['label']) continue;
                    if (mb_strlen($label) > 190) throw new \RuntimeException('Uma substituição excede o limite de 190 caracteres da palavra-chave.');
                    $candidates = $db->table('terms')->select('terms.id, terms.label')->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')->where('vocabularies.project_id', $projectId)->where('terms.label', $label)->where('terms.is_active', 1)->orderBy('terms.id')->get()->getResultArray();
                    $preferred = null;
                    foreach ($candidates as $candidate) {
                        if ($candidate['label'] === $label) {
                            $preferred = ['id' => $candidate['id']];
                            break;
                        }
                    }
                    if (! $preferred) {
                        $preferred = ['id' => UuidModel::generateUuid()];
                        $db->table('terms')->insert($preferred + ['vocabulary_id' => $term['vocabulary_id'], 'code' => $preferred['id'], 'label' => $label, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                    $preferredIds[] = $preferred['id'];
                    $removedIds[] = $term['id'];
                    $changed = true;
                }
                if ($removedIds !== []) $db->table('piece_terms')->where('piece_id', $piece['id'])->whereIn('term_id', $removedIds)->delete();
                foreach (array_unique($preferredIds) as $preferredId) {
                    if (! $db->table('piece_terms')->where(['piece_id' => $piece['id'], 'term_id' => $preferredId])->countAllResults()) $db->table('piece_terms')->insert(['piece_id' => $piece['id'], 'term_id' => $preferredId]);
                }
            }
            if ($changed) $count++;
        }
        (new AuditLogger())->record('community.labels_replaced', 'community', $projectId, ['collection_id' => $collectionId, 'target' => $target, 'count' => $count]);
        $db->transComplete();
        return $count;
    }
}
