<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Models\ProjectModel;
use App\Models\UuidModel;

final class Projects extends BaseController
{
    private const COVER_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function changeImage(string $id)
    {
        $project = (new ProjectModel())->where('status !=', 'excluded')->find($id);
        if (! $project || ! $this->canManage($id)) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para trocar a imagem desta comunidade.');
        }
        if ($this->request->getMethod() === 'POST') {
            $image = $this->request->getFile('cover_image');
            if (! $image || ! $image->isValid() || $image->getSize() > 5 * 1024 * 1024) {
                return redirect()->to('/comunidades/' . $id . '/trocar-imagem')->with('error', 'Selecione uma imagem válida de até 5 MB.');
            }
            $mime = $image->getMimeType();
            $dimensions = @getimagesize($image->getTempName());
            if (! isset(self::COVER_IMAGE_TYPES[$mime]) || ! $dimensions || ($dimensions['mime'] ?? '') !== $mime || $dimensions[0] * $dimensions[1] > 40000000) {
                return redirect()->to('/comunidades/' . $id . '/trocar-imagem')->with('error', 'Use uma imagem JPEG, PNG, WebP ou GIF de até 40 megapixels.');
            }
            $directory = 'community-covers/' . $id;
            $filename = $id . '-' . UuidModel::generateUuid() . '.' . self::COVER_IMAGE_TYPES[$mime];
            try {
                $image->move(WRITEPATH . $directory, $filename);
            } catch (\CodeIgniter\HTTP\Exceptions\HTTPException $exception) {
                return redirect()->to('/comunidades/' . $id . '/trocar-imagem')->with('error', 'Não foi possível armazenar a imagem. Tente novamente.');
            }
            $db = db_connect();
            $db->transStart();
            $db->table('projects')->where('id', $id)->update(['cover_image' => $directory . '/' . $filename, 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogger())->record('community.image_updated', 'community', $id);
            $db->transComplete();
            return redirect()->to('/comunidades')->with($db->transStatus() ? 'success' : 'error', $db->transStatus() ? 'Imagem da comunidade atualizada.' : 'Não foi possível atualizar a imagem da comunidade.');
        }
        return view('projects/image', ['title' => 'Trocar imagem', 'project' => $project, 'mode' => 'image']);
    }

    public function coverImage(string $id)
    {
        $project = db_connect()->table('projects')->where('status !=', 'excluded')
            ->select('cover_image')
            ->where('id', $id)
            ->get()
            ->getRowArray();
        if (! $project || ! $project['cover_image']) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $root = realpath(WRITEPATH . 'community-covers');
        $path = realpath(WRITEPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $project['cover_image']));
        if ($root === false || $path === false || ! is_file($path) || ! str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mimeType = mime_content_type($path);
        if (! is_string($mimeType) || ! in_array($mimeType, array_keys(self::COVER_IMAGE_TYPES), true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody((string) file_get_contents($path));
    }

    public function index(): string
    {
        $projects = (new ProjectModel())->where('status !=', 'excluded')->orderBy('name')->findAll();
        $selectedCommunityId = (string) $this->request->getCookie('mosaico_selected_community');
        if (! in_array($selectedCommunityId, array_column($projects, 'id'), true)) {
            $selectedCommunityId = '';
        }

        return view('projects/index', [
            'title' => 'Comunidades',
            'contentClass' => 'content-wide',
            'projects' => $projects,
            'selectedCommunityId' => $selectedCommunityId,
        ]);
    }

    public function create()
    {
        if ($this->request->getMethod() === 'POST') {
            $model = new ProjectModel();
            $id = UuidModel::generateUuid();
            $data = ['id' => $id, 'code' => strtoupper(trim((string) $this->request->getPost('code'))), 'name' => trim((string) $this->request->getPost('name')), 'description' => trim((string) $this->request->getPost('description')), 'status' => 'active'];
            if ($model->insert($data)) {
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
            'contentClass' => 'content-wide',
            'project' => $project,
            'collections' => db_connect()->table('collections')->where('project_id', $id)->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'canManageCollections' => $this->canManage($id),
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
