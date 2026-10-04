<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateRepositoryCore extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('role_permissions', [
            'valid_from' => ['type' => 'DATETIME', 'null' => true],
            'valid_until' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->table('assets', [
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'piece_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'type' => ['type' => 'VARCHAR', 'constraint' => 80],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['piece_id', 'pieces', 'id', 'RESTRICT'], ['created_by', 'users', 'id', 'SET NULL']]);

        $this->table('asset_versions', [
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'asset_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'version_number' => ['type' => 'INT', 'unsigned' => true],
            'change_notes' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'draft'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['asset_id', 'assets', 'id', 'RESTRICT'], ['created_by', 'users', 'id', 'SET NULL']], [['asset_id', 'version_number']]);

        $this->table('repository_files', [
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'version_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 500],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 150],
            'size_bytes' => ['type' => 'BIGINT', 'unsigned' => true],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['version_id', 'asset_versions', 'id', 'RESTRICT']], [['sha256']]);

        $this->table('audit_events', [
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'user_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 80],
            'entity_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'details' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['user_id', 'users', 'id', 'SET NULL']]);
    }

    public function down(): void
    {
        foreach (['audit_events', 'repository_files', 'asset_versions', 'assets'] as $table) {
            $this->forge->dropTable($table, true);
        }
        $this->forge->dropColumn('role_permissions', ['valid_from', 'valid_until']);
    }

    private function table(string $name, array $fields, string $primary, array $foreign = [], array $unique = []): void
    {
        $this->forge->addField($fields);
        $this->forge->addKey($primary, true);
        foreach ($unique as $key) {
            $this->forge->addKey($key, false, true);
        }
        foreach ($foreign as [$field, $table, $ref, $delete]) {
            $this->forge->addForeignKey($field, $table, $ref, $delete);
        }
        $this->forge->createTable($name, true);
    }
}
