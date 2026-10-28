<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateLabelReplacements extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'project_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'source' => ['type' => 'VARCHAR', 'constraint' => 80],
            'replacement' => ['type' => 'VARCHAR', 'constraint' => 190],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['project_id', 'source'], false, true);
        $this->forge->addForeignKey('project_id', 'projects', 'id', 'RESTRICT');
        $this->forge->createTable('label_replacements', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('label_replacements', true);
    }
}
