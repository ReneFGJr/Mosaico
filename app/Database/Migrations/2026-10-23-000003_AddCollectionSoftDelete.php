<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCollectionSoftDelete extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('collections', ['deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('collections', 'deleted_at');
    }
}
