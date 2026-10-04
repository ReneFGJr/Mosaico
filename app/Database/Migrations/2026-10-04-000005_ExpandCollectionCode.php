<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ExpandCollectionCode extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->modifyColumn('collections', [
            'code' => ['name' => 'code', 'type' => 'VARCHAR', 'constraint' => 160],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->modifyColumn('collections', [
            'code' => ['name' => 'code', 'type' => 'VARCHAR', 'constraint' => 80],
        ]);
    }
}
