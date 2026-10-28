<?php

namespace App\Database\Seeds;

use App\Models\UuidModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

final class AdminUserSeeder extends Seeder
{
    private const EMAIL = 'admin@mosaico.local';

    public function run(): void
    {
        $password = (string) env('MOSAICO_BOOTSTRAP_PASSWORD', '');

        if (strlen($password) < 12) {
            throw new RuntimeException('Defina MOSAICO_BOOTSTRAP_PASSWORD com pelo menos 12 caracteres.');
        }

        if ($this->db->table('users')->where('email', self::EMAIL)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('users')->insert([
            'id'            => UuidModel::generateUuid(),
            'email'         => self::EMAIL,
            'is_admin'      => true,
            'display_name'  => 'Administrador',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
