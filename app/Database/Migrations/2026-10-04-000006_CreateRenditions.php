<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateRenditions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'piece_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'source_file_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 40],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 500],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 150],
            'size_bytes' => ['type' => 'BIGINT', 'unsigned' => true],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['piece_id', 'kind'], false, true);
        $this->forge->addForeignKey('piece_id', 'pieces', 'id', 'CASCADE');
        $this->forge->addForeignKey('source_file_id', 'repository_files', 'id', 'RESTRICT');
        $this->forge->createTable('renditions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('renditions', true);
    }
}
