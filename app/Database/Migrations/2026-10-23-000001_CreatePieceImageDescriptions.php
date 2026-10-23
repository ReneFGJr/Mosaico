<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePieceImageDescriptions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'source_file_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'piece_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'description' => ['type' => 'TEXT'],
            'model' => ['type' => 'VARCHAR', 'constraint' => 120],
            'generated_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('source_file_id', true);
        $this->forge->addKey('piece_id');
        $this->forge->addForeignKey('source_file_id', 'repository_files', 'id', 'RESTRICT');
        $this->forge->addForeignKey('piece_id', 'pieces', 'id', 'RESTRICT');
        $this->forge->addForeignKey('generated_by', 'users', 'id', 'SET NULL');
        $this->forge->createTable('piece_image_descriptions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('piece_image_descriptions', true);
    }
}
