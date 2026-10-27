<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCollectionMembers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'collection_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'user_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'privilege' => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['collection_id', 'user_id'], true);
        $this->forge->addForeignKey('collection_id', 'collections', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('collection_members');
    }

    public function down(): void
    {
        $this->forge->dropTable('collection_members');
    }
}
