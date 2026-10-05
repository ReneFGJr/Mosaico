<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddOriginalFileMetadataToPieces extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pieces', [
            'original_directory' => [
                'type' => 'VARCHAR',
                'constraint' => 1000,
                'null' => true,
                'after' => 'type',
            ],
            'original_filename' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'original_directory',
            ],
        ]);

        $rows = $this->db->table('repository_files')
            ->select('assets.piece_id, repository_files.original_name')
            ->join('asset_versions', 'asset_versions.id = repository_files.version_id')
            ->join('assets', 'assets.id = asset_versions.asset_id')
            ->orderBy('asset_versions.version_number', 'ASC')
            ->orderBy('repository_files.created_at', 'ASC')
            ->get()
            ->getResultArray();
        $updated = [];
        foreach ($rows as $row) {
            $pieceId = (string) $row['piece_id'];
            if (isset($updated[$pieceId])) {
                continue;
            }
            $this->db->table('pieces')->where('id', $pieceId)->update([
                'original_filename' => (string) $row['original_name'],
            ]);
            $updated[$pieceId] = true;
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('pieces', ['original_directory', 'original_filename']);
    }
}
