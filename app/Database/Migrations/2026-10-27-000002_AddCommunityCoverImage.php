<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCommunityCoverImage extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('projects', [
            'cover_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('projects', 'cover_image');
    }
}
