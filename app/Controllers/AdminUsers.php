<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Libraries\SiteAdmin;
use App\Models\UuidModel;
use CodeIgniter\Exceptions\PageNotFoundException;

final class AdminUsers extends BaseController
{
    private function deny()
    {
        return $this->response->setStatusCode(403)->setBody('Acesso restrito à administração do sistema.');
    }

    private function user(string $id): array
    {
        return db_connect()->table('users')->where('id', $id)->get()->getRowArray()
            ?? throw PageNotFoundException::forPageNotFound();
    }

    public function index()
    {
        if (! SiteAdmin::current()) return $this->deny();
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 150);
        $builder = db_connect()->table('users')->select('id, display_name, email, is_active, is_admin');
        if ($query !== '') $builder->groupStart()->like('display_name', $query)->orLike('email', $query)->groupEnd();
        return view('admin/users/index', ['title' => 'Usuários', 'users' => $builder->orderBy('display_name')->get()->getResultArray(), 'query' => $query]);
    }

    public function create()
    {
        return $this->edit();
    }

    public function edit(?string $id = null)
    {
        if (! SiteAdmin::current()) return $this->deny();
        $db = db_connect();
        $user = $id ? $this->user($id) : ['id' => '', 'display_name' => '', 'email' => '', 'is_active' => true, 'is_admin' => false];
        $projects = $db->table('projects')->where('status !=', 'excluded')->orderBy('name')->get()->getResultArray();
        $collections = $db->table('collections')->select('collections.id, collections.name, collections.project_id, projects.name AS project_name')
            ->join('projects', 'projects.id = collections.project_id')->where('projects.status !=', 'excluded')->where('collections.deleted_at', null)->orderBy('projects.name')->orderBy('collections.name')->get()->getResultArray();
        $roles = $db->table('roles')->orderBy('name')->get()->getResultArray();
        $memberships = $id ? array_column($db->table('memberships')->where('user_id', $id)->get()->getResultArray(), 'role_id', 'project_id') : [];
        $collectionAccess = $id ? array_column($db->table('collection_members')->where('user_id', $id)->get()->getResultArray(), 'privilege', 'collection_id') : [];
        $errors = [];
        if ($this->request->getMethod() === 'POST') {
            $user['display_name'] = trim((string) $this->request->getPost('display_name'));
            $user['email'] = mb_strtolower(trim((string) $this->request->getPost('email')));
            $user['is_active'] = $this->request->getPost('is_active') === '1';
            $user['is_admin'] = $this->request->getPost('is_admin') === '1';
            $password = (string) $this->request->getPost('password');
            $memberships = (array) $this->request->getPost('communities');
            $collectionAccess = (array) $this->request->getPost('collections');
            if ($user['display_name'] === '' || mb_strlen($user['display_name']) > 190) $errors[] = 'Informe um nome de até 190 caracteres.';
            if (! filter_var($user['email'], FILTER_VALIDATE_EMAIL) || strlen($user['email']) > 254) $errors[] = 'Informe um e-mail válido.';
            $duplicate = $db->table('users')->where('email', $user['email']);
            if ($id) $duplicate->where('id !=', $id);
            if ($duplicate->countAllResults()) $errors[] = 'Este e-mail já está cadastrado.';
            if ((! $id || $password !== '') && (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0") || ! preg_match('/\p{L}/u', $password) || ! preg_match('/[0-9]/', $password))) $errors[] = 'A senha deve ter pelo menos 8 caracteres com letras e números e no máximo 72 bytes.';
            if ($id === session('user_id') && (! $user['is_active'] || ! $user['is_admin'])) $errors[] = 'Você não pode desativar sua conta nem remover seu próprio perfil de administrador.';
            foreach ($memberships as $projectId => $roleId) {
                if (! is_string($roleId) || ! in_array($projectId, array_column($projects, 'id'), true) || ($roleId !== '' && ! in_array($roleId, array_column($roles, 'id'), true))) $errors[] = 'Selecione uma comunidade e um perfil válidos.';
            }
            foreach ($collectionAccess as $collectionId => $privilege) {
                if (! in_array($collectionId, array_column($collections, 'id'), true) || ! in_array($privilege, ['', 'view', 'add'], true)) $errors[] = 'Selecione uma coleção e uma permissão válidas.';
            }
            if ($errors === []) {
                $userId = $id ?? UuidModel::generateUuid();
                $data = array_intersect_key($user, array_flip(['display_name', 'email', 'is_active', 'is_admin']));
                $data['updated_at'] = date('Y-m-d H:i:s');
                if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $db->transStart();
                if ($id) $db->table('users')->where('id', $id)->update($data);
                else $db->table('users')->insert($data + ['id' => $userId, 'created_at' => $data['updated_at']]);
                // Only replace access for the active communities shown in this form.
                if ($projects !== []) $db->table('memberships')->where('user_id', $userId)->whereIn('project_id', array_column($projects, 'id'))->delete();
                foreach ($memberships as $projectId => $roleId) {
                    if ($roleId !== '') $db->table('memberships')->insert(['user_id' => $userId, 'project_id' => $projectId, 'role_id' => $roleId, 'created_at' => $data['updated_at']]);
                }
                if ($collections !== []) $db->table('collection_members')->where('user_id', $userId)->whereIn('collection_id', array_column($collections, 'id'))->delete();
                foreach ($collectionAccess as $collectionId => $privilege) {
                    if ($privilege !== '') $db->table('collection_members')->insert(['user_id' => $userId, 'collection_id' => $collectionId, 'privilege' => $privilege, 'created_at' => $data['updated_at']]);
                }
                (new AuditLogger())->record($id ? 'user.updated' : 'user.created', 'user', $userId, ['is_admin' => $user['is_admin'], 'is_active' => $user['is_active'], 'communities' => $memberships, 'collections' => $collectionAccess]);
                $db->transComplete();
                if ($db->transStatus()) {
                    if ($id === session('user_id')) session()->set('user_name', $user['display_name']);
                    return redirect()->to('/admin/users/' . $userId)->with('success', 'Usuário e perfis salvos com sucesso.');
                }
                $errors[] = 'Não foi possível salvar. Tente novamente.';
            }
        }
        return view('admin/users/form', compact('user', 'projects', 'collections', 'roles', 'memberships', 'collectionAccess', 'errors') + ['title' => $id ? 'Editar usuário' : 'Novo usuário']);
    }

    public function delete(string $id)
    {
        if (! SiteAdmin::current()) return $this->deny();
        $user = $this->user($id);
        if ($id === session('user_id')) return redirect()->to('/admin/users')->with('error', 'Você não pode desativar sua própria conta.');
        if ($this->request->getPost('confirmed') !== '1') return redirect()->to('/admin/users/' . $id . '/delete');
        db_connect()->table('users')->where('id', $id)->update(['is_active' => false, 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditLogger())->record('user.deactivated', 'user', $id);
        return redirect()->to('/admin/users')->with('success', 'Usuário desativado. O histórico foi preservado.');
    }

    public function confirmDelete(string $id)
    {
        if (! SiteAdmin::current()) return $this->deny();
        return view('admin/users/delete', ['title' => 'Desativar usuário', 'user' => $this->user($id)]);
    }

    public function impersonate(string $id)
    {
        if (! SiteAdmin::current()) return $this->deny();
        $user = $this->user($id);
        if (! $user['is_active'] || $id === session('user_id')) return redirect()->to('/admin/users')->with('error', 'Selecione outro usuário ativo.');
        $adminId = session('user_id');
        (new AuditLogger())->record('auth.impersonation_started', 'user', $id, ['administrator_id' => $adminId]);
        session()->regenerate(true);
        session()->set(['impersonator_id' => $adminId, 'user_id' => $id, 'user_name' => $user['display_name']]);
        return redirect()->to('/painel');
    }

    public function stopImpersonating()
    {
        $adminId = session('impersonator_id');
        if (! is_string($adminId) || ! SiteAdmin::isAdmin($adminId)) return $this->deny();
        (new AuditLogger())->record('auth.impersonation_finished', 'user', session('user_id'), ['administrator_id' => $adminId]);
        $admin = $this->user($adminId);
        session()->regenerate(true);
        session()->remove('impersonator_id');
        session()->set(['user_id' => $adminId, 'user_name' => $admin['display_name']]);
        return redirect()->to('/admin/users');
    }
}
