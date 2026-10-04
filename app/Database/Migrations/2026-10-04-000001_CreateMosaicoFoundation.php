<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMosaicoFoundation extends Migration
{
    public function up(): void
    {
        $this->createTable('users', [
            'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 254],
            'display_name'  => ['type' => 'VARCHAR', 'constraint' => 190],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_active'     => ['type' => 'BOOLEAN', 'default' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['email']]);

        $this->createTable('roles', [
            'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['code']]);

        $this->createTable('permissions', [
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
        ], 'id', [['code']]);

        $this->createTable('role_permissions', [
            'role_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
            'permission_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        ], ['role_id', 'permission_id'], [], [
            ['role_id', 'roles', 'id', 'CASCADE'],
            ['permission_id', 'permissions', 'id', 'CASCADE'],
        ]);

        $this->createTable('projects', [
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 190],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['code']]);

        $this->createTable('memberships', [
            'project_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'user_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
            'role_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ], ['project_id', 'user_id', 'role_id'], [], [
            ['project_id', 'projects', 'id', 'CASCADE'],
            ['user_id', 'users', 'id', 'CASCADE'],
            ['role_id', 'roles', 'id', 'RESTRICT'],
        ]);

        $this->createTable('collections', [
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'project_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 190],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['project_id', 'code']], [
            ['project_id', 'projects', 'id', 'CASCADE'],
        ]);

        $this->createTable('pieces', [
            'id'                    => ['type' => 'VARCHAR', 'constraint' => 36],
            'project_id'            => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'                  => ['type' => 'VARCHAR', 'constraint' => 80],
            'title'                 => ['type' => 'VARCHAR', 'constraint' => 255],
            'summary'               => ['type' => 'TEXT', 'null' => true],
            'type'                  => ['type' => 'VARCHAR', 'constraint' => 80],
            'pedagogical_purpose'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'audience'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'draft'],
            'visibility'            => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'private'],
            'created_by'            => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['project_id', 'code']], [
            ['project_id', 'projects', 'id', 'RESTRICT'],
            ['created_by', 'users', 'id', 'SET NULL'],
        ]);

        $this->createTable('piece_collections', [
            'piece_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'collection_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        ], ['piece_id', 'collection_id'], [], [
            ['piece_id', 'pieces', 'id', 'CASCADE'],
            ['collection_id', 'collections', 'id', 'CASCADE'],
        ]);

        $this->createTable('vocabularies', [
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 190],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['code']]);

        $this->createTable('terms', [
            'id'             => ['type' => 'VARCHAR', 'constraint' => 36],
            'vocabulary_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'label'          => ['type' => 'VARCHAR', 'constraint' => 190],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'parent_term_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'replaced_by_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'is_active'      => ['type' => 'BOOLEAN', 'default' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ], 'id', [['vocabulary_id', 'code']], [
            ['vocabulary_id', 'vocabularies', 'id', 'CASCADE'],
            ['parent_term_id', 'terms', 'id', 'RESTRICT'],
            ['replaced_by_id', 'terms', 'id', 'RESTRICT'],
        ]);

        $this->createTable('piece_terms', [
            'piece_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'term_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
        ], ['piece_id', 'term_id'], [], [
            ['piece_id', 'pieces', 'id', 'CASCADE'],
            ['term_id', 'terms', 'id', 'RESTRICT'],
        ]);

        $this->insertReferenceData();
    }

    public function down(): void
    {
        foreach ([
            'piece_terms',
            'terms',
            'vocabularies',
            'piece_collections',
            'pieces',
            'collections',
            'memberships',
            'projects',
            'role_permissions',
            'permissions',
            'roles',
            'users',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createTable(
        string $table,
        array $fields,
        string|array $primaryKey,
        array $uniqueKeys = [],
        array $foreignKeys = [],
    ): void {
        $this->forge->addField($fields);
        $this->forge->addKey($primaryKey, true);

        foreach ($uniqueKeys as $key) {
            $this->forge->addKey($key, false, true);
        }

        foreach ($foreignKeys as [$field, $referencedTable, $referencedField, $onDelete]) {
            $this->forge->addForeignKey($field, $referencedTable, $referencedField, $onDelete);
        }

        $this->forge->createTable($table, true);
    }

    private function insertReferenceData(): void
    {
        $roles = [
            'administrator'       => 'Administrador',
            'repository_manager'  => 'Gestor do repositório',
            'producer_editor'     => 'Produtor/editor',
            'reviewer'            => 'Revisor',
            'coordinator_approver' => 'Coordenador/aprovador',
            'publisher'           => 'Publicador',
            'consultation'         => 'Consulta',
            'auditor'              => 'Auditor',
        ];
        $permissions = [
            'projects.manage'     => 'Gerenciar projetos e coleções',
            'pieces.create'       => 'Cadastrar peças',
            'pieces.read'         => 'Consultar peças do projeto',
            'pieces.update'       => 'Editar metadados de peças',
            'vocabularies.manage' => 'Gerenciar vocabulários',
        ];

        $roleIds = [];
        foreach ($roles as $code => $name) {
            $roleIds[$code] = $this->newUuid();
            $this->db->table('roles')->insert([
                'id'   => $roleIds[$code],
                'code' => $code,
                'name' => $name,
            ]);
        }

        $permissionIds = [];
        foreach ($permissions as $code => $description) {
            $permissionIds[$code] = $this->newUuid();
            $this->db->table('permissions')->insert([
                'id'          => $permissionIds[$code],
                'code'        => $code,
                'description' => $description,
            ]);
        }

        $grants = [
            'administrator' => array_keys($permissions),
            'repository_manager' => array_keys($permissions),
            'producer_editor' => ['pieces.create', 'pieces.read', 'pieces.update'],
            'reviewer' => ['pieces.read'],
            'coordinator_approver' => ['pieces.read'],
            'publisher' => ['pieces.read'],
            'consultation' => ['pieces.read'],
            'auditor' => ['pieces.read'],
        ];

        foreach ($grants as $roleCode => $permissionCodes) {
            foreach ($permissionCodes as $permissionCode) {
                $this->db->table('role_permissions')->insert([
                    'role_id'       => $roleIds[$roleCode],
                    'permission_id' => $permissionIds[$permissionCode],
                ]);
            }
        }
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}