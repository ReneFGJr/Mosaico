<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCommunityVisibility extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('projects', [
            'visibility' => [
                'type' => 'VARCHAR',
                'constraint' => 24,
                'default' => 'restricted',
                'after' => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('projects', 'visibility');
    }
}
