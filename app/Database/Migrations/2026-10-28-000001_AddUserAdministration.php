<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddUserAdministration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', ['is_admin' => ['type' => 'BOOLEAN', 'default' => false]]);
        // Preserve the installation's original administrative account.
        $this->db->table('users')->where('email', 'admin@mosaico.local')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'is_admin');
    }
}
