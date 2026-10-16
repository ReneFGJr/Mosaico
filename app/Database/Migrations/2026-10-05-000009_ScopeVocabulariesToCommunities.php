<?php

namespace App\Database\Migrations;

use App\Models\UuidModel;
use CodeIgniter\Database\Migration;

final class ScopeVocabulariesToCommunities extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('vocabularies', [
            'project_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $projects = array_column(
            $this->db->table('projects')->select('id')->orderBy('id')->get()->getResultArray(),
            'id',
        );
        $vocabularies = $this->db->table('vocabularies')->get()->getResultArray();

        foreach ($vocabularies as $vocabulary) {
            $projectIds = $projects;
            if ($projectIds !== []) {
                $this->db->table('vocabularies')->where('id', $vocabulary['id'])->update([
                    'project_id' => $projectIds[0],
                ]);

                foreach (array_slice($projectIds, 1) as $projectId) {
                    $this->copyVocabularyToProject($vocabulary, (string) $projectId);
                }
                continue;
            }

            $this->db->table('vocabularies')->where('id', $vocabulary['id'])->update(['project_id' => null]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('vocabularies', 'project_id');
    }

    private function copyVocabularyToProject(array $vocabulary, string $projectId): void
    {
        $vocabularyId = UuidModel::generateUuid();
        $now = date('Y-m-d H:i:s');
        $this->db->table('vocabularies')->insert([
            'id'          => $vocabularyId,
            'project_id'  => $projectId,
            'code'        => $this->uniqueCode((string) $vocabulary['code'], $projectId),
            'name'        => $vocabulary['name'],
            'description' => $vocabulary['description'],
            'created_at'  => $vocabulary['created_at'],
            'updated_at'  => $vocabulary['updated_at'] ?? $now,
        ]);

        $terms = $this->db->table('terms')->where('vocabulary_id', $vocabulary['id'])->get()->getResultArray();
        $termIds = [];
        foreach ($terms as $term) {
            $termIds[$term['id']] = UuidModel::generateUuid();
        }

        foreach ($terms as $term) {
            $this->db->table('terms')->insert([
                'id'             => $termIds[$term['id']],
                'vocabulary_id'  => $vocabularyId,
                'code'           => $term['code'],
                'label'          => $term['label'],
                'description'    => $term['description'],
                'parent_term_id' => isset($termIds[$term['parent_term_id']]) ? null : $term['parent_term_id'],
                'replaced_by_id' => isset($termIds[$term['replaced_by_id']]) ? null : $term['replaced_by_id'],
                'is_active'      => $term['is_active'],
                'created_at'     => $term['created_at'],
                'updated_at'     => $term['updated_at'],
            ]);
        }

        foreach ($terms as $term) {
            $this->db->table('terms')->where('id', $termIds[$term['id']])->update([
                'parent_term_id' => $termIds[$term['parent_term_id']] ?? $term['parent_term_id'],
                'replaced_by_id' => $termIds[$term['replaced_by_id']] ?? $term['replaced_by_id'],
            ]);
        }

        if ($terms === []) {
            return;
        }

        $links = $this->db->table('piece_terms')
            ->select('piece_terms.piece_id, piece_terms.term_id')
            ->join('pieces', 'pieces.id = piece_terms.piece_id')
            ->where('pieces.project_id', $projectId)
            ->whereIn('piece_terms.term_id', array_keys($termIds))
            ->get()
            ->getResultArray();

        foreach ($links as $link) {
            $this->db->table('piece_terms')
                ->where('piece_id', $link['piece_id'])
                ->where('term_id', $link['term_id'])
                ->delete();
            $this->db->table('piece_terms')->insert([
                'piece_id' => $link['piece_id'],
                'term_id'  => $termIds[$link['term_id']],
            ]);
        }
    }

    private function uniqueCode(string $baseCode, string $projectId): string
    {
        $suffix = '-' . substr(str_replace('-', '', $projectId), 0, 8);
        $baseCode = substr($baseCode, 0, 80 - strlen($suffix));
        $code = $baseCode . $suffix;
        $counter = 2;

        while ($this->db->table('vocabularies')->where('code', $code)->countAllResults() > 0) {
            $counterSuffix = $suffix . '-' . $counter++;
            $code = substr($baseCode, 0, 80 - strlen($counterSuffix)) . $counterSuffix;
        }

        return $code;
    }
}
