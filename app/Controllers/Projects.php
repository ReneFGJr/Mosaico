<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Models\ProjectModel;

final class Projects extends BaseController
{
    public function index(): string
    {
        return view('projects/index', [
            'title' => 'Comunidades',
            'projects' => (new ProjectModel())->where('status !=', 'excluded')->orderBy('name')->findAll(),
            'selectedCommunityId' => (string) $this->request->getCookie('mosaico_selected_community'),
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            $model = new ProjectModel();
            $data = ['code' => strtoupper(trim((string) $this->request->getPost('code'))), 'name' => trim((string) $this->request->getPost('name')), 'description' => trim((string) $this->request->getPost('description')), 'status' => 'active'];
            if ($model->insert($data)) {
                $id = (string) $model->getInsertID();
                $role = db_connect()->table('roles')->where('code', 'administrator')->get()->getRowArray();
                if ($role) {
                    db_connect()->table('memberships')->insert(['project_id' => $id, 'user_id' => session('user_id'), 'role_id' => $role['id'], 'created_at' => date('Y-m-d H:i:s')]);
                }
                (new AuditLogger())->record('project.created', 'project', $id, ['code' => $data['code']]);
                return redirect()->to('/comunidades/' . $id)->with('success', 'Comunidade criada.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('projects/form', ['title' => 'Nova comunidade']);
    }

    public function show(string $id): string
    {
        $project = (new ProjectModel())->find($id);
        if (! $project) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('projects/show', [
            'title' => $project['name'],
            'project' => $project,
            'collections' => db_connect()->table('collections')->where('project_id', $id)->orderBy('name')->get()->getResultArray(),
            'selectedCollectionId' => (string) $this->request->getCookie('mosaico_selected_collection'),
        ]);
    }

    public function select(string $id)
    {
        $project = (new ProjectModel())->where('status !=', 'excluded')->find($id);
        if (! $project) {
            return redirect()->to('/comunidades')->with('error', 'Comunidade indisponível para seleção.');
        }

        (new AuditLogger())->record('community.selected', 'community', $id);

        return redirect()->to('/comunidades/' . $id)
            ->setCookie(
                'mosaico_selected_community',
                $id,
                60 * 60 * 24 * 30,
                '',
                '/',
                '',
                $this->request->isSecure(),
                true,
                'Lax',
            )
            ->with('success', 'Comunidade selecionada para a navegação.');
    }

    public function edit(string $id)
    {
        $model = new ProjectModel();
        $project = $model->find($id);
        if (! $project) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage($id)) {
            return redirect()->to('/comunidades/' . $id)->with('error', 'Você não tem permissão para editar esta comunidade.');
        }

        if ($this->request->getMethod() === 'POST') {
            $data = [
                'code' => strtoupper(trim((string) $this->request->getPost('code'))),
                'name' => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')),
            ];
            if ($model->update($id, $data)) {
                (new AuditLogger())->record('community.updated', 'community', $id, ['code' => $data['code']]);
                return redirect()->to('/comunidades/' . $id)->with('success', 'Dados da comunidade atualizados.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return view('projects/edit', ['title' => 'Editar comunidade', 'project' => $project]);
    }

    public function permissions(string $id)
    {
        $project = (new ProjectModel())->find($id);
        if (! $project) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage($id)) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para gerenciar os acessos desta comunidade.');
        }

        $db = db_connect();
        if ($this->request->getMethod() === 'POST') {
            $userId = (string) $this->request->getPost('user_id');
            $roleId = (string) $this->request->getPost('role_id');
            $userExists = $db->table('users')->where('id', $userId)->where('is_active', true)->countAllResults() === 1;
            $roleExists = $db->table('roles')->where('id', $roleId)->countAllResults() === 1;
            if (! $userExists || ! $roleExists) {
                return redirect()->back()->withInput()->with('error', 'Selecione um usuário e um papel válidos.');
            }
            $exists = $db->table('memberships')->where(['project_id' => $id, 'user_id' => $userId, 'role_id' => $roleId])->countAllResults() > 0;
            if (! $exists) {
                $db->table('memberships')->insert(['project_id' => $id, 'user_id' => $userId, 'role_id' => $roleId, 'created_at' => date('Y-m-d H:i:s')]);
                (new AuditLogger())->record('community.access_granted', 'community', $id, ['user_id' => $userId, 'role_id' => $roleId]);
            }
            return redirect()->to('/comunidades/' . $id . '/permissoes')->with('success', $exists ? 'Esse acesso já estava configurado.' : 'Acesso concedido.');
        }

        $members = $db->table('memberships')->select('users.display_name, users.email, roles.name AS role_name')->join('users', 'users.id = memberships.user_id')->join('roles', 'roles.id = memberships.role_id')->where('memberships.project_id', $id)->orderBy('users.display_name')->get()->getResultArray();
        return view('projects/permissions', ['title' => 'Permissões', 'project' => $project, 'members' => $members, 'users' => $db->table('users')->where('is_active', true)->orderBy('display_name')->get()->getResultArray(), 'roles' => $db->table('roles')->orderBy('name')->get()->getResultArray()]);
    }

    public function visibility(string $id)
    {
        if (! $this->canManage($id)) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para alterar a visibilidade.');
        }
        $visibility = (string) $this->request->getPost('visibility');
        if (! in_array($visibility, ['public', 'restricted', 'excluded'], true)) {
            return redirect()->back()->with('error', 'Visibilidade inválida.');
        }
        (new ProjectModel())->update($id, ['visibility' => $visibility]);
        (new AuditLogger())->record('community.visibility_changed', 'community', $id, ['visibility' => $visibility]);
        return redirect()->back()->with('success', 'Visibilidade atualizada.');
    }

    public function delete(string $id)
    {
        if (! $this->canManage($id, true)) {
            return redirect()->to('/comunidades')->with('error', 'Somente administradores podem excluir comunidades.');
        }
        (new ProjectModel())->update($id, ['status' => 'excluded', 'visibility' => 'excluded']);
        (new AuditLogger())->record('community.excluded', 'community', $id);
        return redirect()->to('/comunidades')->with('success', 'Comunidade excluída logicamente. O histórico foi preservado.');
    }

    private function canManage(string $projectId, bool $administratorOnly = false): bool
    {
        $roles = $administratorOnly ? ['administrator'] : ['administrator', 'repository_manager'];
        return db_connect()->table('memberships')->join('roles', 'roles.id = memberships.role_id')->where('memberships.project_id', $projectId)->where('memberships.user_id', session('user_id'))->whereIn('roles.code', $roles)->countAllResults() > 0;
    }
}
