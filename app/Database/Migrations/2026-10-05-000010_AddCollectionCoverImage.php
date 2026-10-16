<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCollectionCoverImage extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('collections', [
            'cover_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('collections', 'cover_image');
    }
}
