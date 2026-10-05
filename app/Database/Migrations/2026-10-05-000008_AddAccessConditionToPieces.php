<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAccessConditionToPieces extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pieces', [
            'access_condition' => [
                'type' => 'VARCHAR',
                'constraint' => 24,
                'null' => true,
                'after' => 'visibility',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('pieces', 'access_condition');
    }
}
