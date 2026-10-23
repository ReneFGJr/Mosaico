<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePiecePeopleDetections extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'piece_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'source_file_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'result_json' => ['type' => 'TEXT'],
            'model' => ['type' => 'VARCHAR', 'constraint' => 120],
            'generated_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'generated_at' => ['type' => 'DATETIME', 'constraint' => 6],
            'manual_names' => ['type' => 'TEXT', 'null' => true],
            'names_updated_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'names_updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_file_id', 'generated_at']);
        $this->forge->addForeignKey('piece_id', 'pieces', 'id', 'RESTRICT');
        $this->forge->addForeignKey('source_file_id', 'repository_files', 'id', 'RESTRICT');
        $this->forge->addForeignKey('generated_by', 'users', 'id', 'SET NULL');
        $this->forge->addForeignKey('names_updated_by', 'users', 'id', 'SET NULL');
        $this->forge->createTable('piece_people_detections', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('piece_people_detections', true);
    }
}
