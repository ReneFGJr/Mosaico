<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AdoptCommunityTerminology extends Migration
{
    public function up(): void
    {
        $this->db->table('permissions')->where('code', 'projects.manage')->update([
            'description' => 'Gerenciar comunidades e coleções',
        ]);
        $this->db->table('permissions')->where('code', 'pieces.read')->update([
            'description' => 'Consultar peças da comunidade',
        ]);
    }

    public function down(): void
    {
        $this->db->table('permissions')->where('code', 'projects.manage')->update([
            'description' => 'Gerenciar projetos e coleções',
        ]);
        $this->db->table('permissions')->where('code', 'pieces.read')->update([
            'description' => 'Consultar peças do projeto',
        ]);
    }
}
