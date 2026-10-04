<?php

namespace App\Commands;

use App\Models\ProjectModel;
use App\Models\UuidModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class MosaicoBootstrap extends BaseCommand
{
    protected $group = 'MOSAICO';
    protected $name = 'mosaico:bootstrap';
    protected $description = 'Cria a comunidade e o administrador iniciais.';
    protected $usage = 'mosaico:bootstrap <codigo> <comunidade> <email> <nome>';

    public function run(array $params): void
    {
        if (count($params) < 4) {
            CLI::error('Informe código, nome da comunidade, e-mail e nome do administrador.');
            return;
        }
        $password = getenv('MOSAICO_BOOTSTRAP_PASSWORD') ?: '';
        if (strlen($password) < 12) {
            CLI::error('Defina MOSAICO_BOOTSTRAP_PASSWORD com pelo menos 12 caracteres.');
            return;
        }
        [$code, $projectName, $email, $displayName] = $params;
        $db = db_connect();
        if ($db->table('users')->where('email', mb_strtolower($email))->countAllResults() > 0) {
            CLI::error('O e-mail informado já está cadastrado.');
            return;
        }
        $userId = UuidModel::generateUuid();
        $db->transStart();
        $db->table('users')->insert(['id' => $userId, 'email' => mb_strtolower($email), 'display_name' => $displayName, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'is_active' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $projectModel = new ProjectModel();
        $projectModel->insert(['code' => strtoupper($code), 'name' => $projectName, 'status' => 'active']);
        $role = $db->table('roles')->where('code', 'administrator')->get()->getRowArray();
        $db->table('memberships')->insert(['project_id' => $projectModel->getInsertID(), 'user_id' => $userId, 'role_id' => $role['id'], 'created_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();
        CLI::write('MOSAICO inicializado. Acesse /login com o e-mail informado.', 'green');
    }
}
