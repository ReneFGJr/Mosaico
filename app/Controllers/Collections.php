<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Libraries\GoogleDriveImporter;
use App\Libraries\ProjectAccess;
use App\Models\CollectionModel;
use App\Models\ProjectModel;
use App\Models\UuidModel;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\Exceptions\HTTPException;

final class Collections extends BaseController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm', 'avi', 'mp3', 'wav', 'ogg', 'ods', 'xml', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/xml',
        'text/xml',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
        'application/vnd.ms-office',
        'application/cdfv2',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/csv',
    ];
    private const MAX_FILE_SIZE = 1073741824;
    private const COVER_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function show(string $id): string
    {
        $db = db_connect();
        $collection = $db->table('collections')->where('collections.deleted_at', null)
            ->select('collections.*, projects.name AS community_name, projects.id AS community_id')
            ->join('projects', 'projects.id = collections.project_id')
            ->where('collections.id', $id)
            ->where('projects.status !=', 'excluded')
            ->get()
            ->getRowArray();

        if (! $collection) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $allowedViewModes = ['list', 'details', 'thumbnail', 'cards'];
        $requestedViewMode = (string) $this->request->getGet('view');
        if (in_array($requestedViewMode, $allowedViewModes, true)) {
            $viewMode = $requestedViewMode;
            $this->response->setCookie('mosaico_collection_view', $viewMode, 60 * 60 * 24 * 365, '', '/', '', $this->request->isSecure(), true, 'Lax');
        } else {
            $savedViewMode = (string) $this->request->getCookie('mosaico_collection_view');
            $viewMode = in_array($savedViewMode, $allowedViewModes, true) ? $savedViewMode : 'thumbnail';
        }

        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 150);

        $selectedTermId = (string) $this->request->getGet('term');
        $selectedTerm = null;
        if ($selectedTermId !== '') {
            $selectedTerm = $db->table('terms')
                ->select('terms.id, terms.label')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('terms.id', $selectedTermId)
                ->where('vocabularies.project_id', $collection['project_id'])
                ->where('terms.is_active', true)
                ->get()
                ->getRowArray();
            if (! $selectedTerm) {
                $selectedTermId = '';
            }
        }
        $piecesBuilder = $db->table('pieces')
            ->select('pieces.*')
            ->join('piece_collections', 'piece_collections.piece_id = pieces.id')
            ->where('piece_collections.collection_id', $id);
        if ($selectedTermId !== '') {
            $piecesBuilder->join('piece_terms AS selected_piece_terms', 'selected_piece_terms.piece_id = pieces.id')
                ->where('selected_piece_terms.term_id', $selectedTermId);
        }
        if ($query !== '') {
            $piecesBuilder
                ->distinct()
                ->join('piece_terms AS search_piece_terms', 'search_piece_terms.piece_id = pieces.id', 'left')
                ->join(
                    'terms AS search_terms',
                    new RawSql('search_terms.id = search_piece_terms.term_id AND search_terms.is_active = 1 AND search_terms.vocabulary_id IN (SELECT scoped_vocabularies.id FROM vocabularies AS scoped_vocabularies WHERE scoped_vocabularies.project_id = ' . $db->escape($collection['project_id']) . ')'),
                    'left',
                )
                ->groupStart()
                ->like('pieces.title', $query)
                ->orLike('pieces.code', $query)
                ->orLike('pieces.summary', $query)
                ->orLike('pieces.type', $query)
                ->orLike('pieces.original_filename', $query)
                ->orLike('pieces.original_directory', $query)
                ->orLike('search_terms.label', $query)
                ->groupEnd();
        }
        $pieces = $piecesBuilder->orderBy('pieces.title')->get()->getResultArray();
        $keywordsByPiece = [];
        if ($pieces !== []) {
            $keywordRows = $db->table('piece_terms')
                ->select('piece_terms.piece_id, terms.id, terms.label')
                ->join('terms', 'terms.id = piece_terms.term_id')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('vocabularies.project_id', $collection['project_id'])
                ->where('terms.is_active', true)
                ->whereIn('piece_terms.piece_id', array_column($pieces, 'id'))
                ->orderBy('terms.label')
                ->get()
                ->getResultArray();
            foreach ($keywordRows as $row) {
                $keywordsByPiece[$row['piece_id']][] = ['id' => $row['id'], 'label' => $row['label']];
            }
        }
        foreach ($pieces as &$piece) {
            $piece['keywords'] = $keywordsByPiece[$piece['id']] ?? [];
            $piece['display_state'] = match (true) {
                ($piece['access_condition'] ?? null) === 'restricted' => 'restricted',
                ($piece['access_condition'] ?? null) === 'embargoed' => 'embargoed',
                $piece['status'] === 'published' => 'published',
                default => 'draft',
            };
            $piece['display_state_label'] = match ($piece['display_state']) {
                'restricted' => 'Restrito',
                'embargoed' => 'Embargado',
                'published' => 'Publicado',
                default => 'Draft',
            };
            $piece['display_state_locked'] = in_array($piece['display_state'], ['restricted', 'embargoed'], true);
        }
        unset($piece);

        return view('collections/show', [
            'title' => $collection['name'],
            'contentClass' => 'content-wide',
            'collection' => $collection,
            'viewMode' => $viewMode,
            'query' => $query,
            'selectedTerm' => $selectedTerm,
            'pieces' => $pieces,
            'terms' => $db->table('terms')
                ->select('terms.id, terms.label, vocabularies.name AS vocabulary_name')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('vocabularies.project_id', $collection['project_id'])
                ->where('terms.is_active', true)
                ->orderBy('vocabularies.name')
                ->orderBy('terms.label')
                ->get()
                ->getResultArray(),
        ]);
    }

    public function coverImage(string $id)
    {
        $collection = db_connect()->table('collections')->where('collections.deleted_at', null)
            ->select('cover_image')
            ->where('id', $id)
            ->get()
            ->getRowArray();
        if (! $collection || ! $collection['cover_image']) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $root = realpath(WRITEPATH . 'collection-covers');
        $path = realpath(WRITEPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $collection['cover_image']));
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

    public function removeDrafts(string $id)
    {
        $db = db_connect();
        $collection = $db->table('collections')->where('collections.deleted_at', null)->where('id', $id)->get()->getRowArray();
        if (! $collection) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $collection['project_id'])) {
            return redirect()->back()->with('error', 'Você não tem permissão para remover peças desta coleção.');
        }

        $builder = $db->table('piece_collections')
            ->select('piece_collections.piece_id')
            ->join('pieces', 'pieces.id = piece_collections.piece_id')
            ->where('piece_collections.collection_id', $id)
            ->where('pieces.status', 'draft');
        if ((string) $this->request->getPost('scope') !== 'all') {
            $selectedIds = array_values(array_filter((array) $this->request->getPost('piece_ids'), static fn ($value): bool => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1));
            if ($selectedIds === []) {
                return redirect()->back()->with('error', 'Selecione ao menos uma peça em Draft.');
            }
            $builder->whereIn('piece_collections.piece_id', $selectedIds);
        }
        $pieceIds = array_column($builder->get()->getResultArray(), 'piece_id');
        if ($pieceIds === []) {
            return redirect()->back()->with('error', 'Nenhuma peça em Draft foi encontrada para remoção.');
        }

        $db->table('piece_collections')->where('collection_id', $id)->whereIn('piece_id', $pieceIds)->delete();
        (new AuditLogger())->record('collection.drafts_removed', 'collection', $id, ['piece_ids' => $pieceIds, 'count' => count($pieceIds)]);

        return redirect()->to('/colecoes/' . $id)->with('success', count($pieceIds) . (count($pieceIds) === 1 ? ' peça em Draft removida da coleção.' : ' peças em Draft removidas da coleção.'));
    }

    public function bulkAction(string $id)
    {
        $db = db_connect();
        $collection = $db->table('collections')->where('collections.deleted_at', null)->where('id', $id)->get()->getRowArray();
        if (! $collection) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $collection['project_id'])) {
            return redirect()->to('/colecoes/' . $id)->with('error', 'Você não tem permissão para executar ações nesta coleção.');
        }

        $action = (string) $this->request->getPost('bulk_action');
        $allowedActions = ['publish', 'embargo', 'restrict', 'remove'];
        if (! in_array($action, $allowedActions, true)) {
            return redirect()->to('/colecoes/' . $id)->with('error', 'Selecione uma ação válida.');
        }
        $selectedIds = array_values(array_unique(array_filter(
            (array) $this->request->getPost('piece_ids'),
            static fn ($value): bool => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1,
        )));
        if ($selectedIds === []) {
            return redirect()->to('/colecoes/' . $id)->with('error', 'Selecione ao menos uma peça em Draft.');
        }
        $pieceIds = array_column($db->table('pieces')
            ->select('pieces.id')
            ->join('piece_collections', 'piece_collections.piece_id = pieces.id')
            ->where('piece_collections.collection_id', $id)
            ->where('pieces.status', 'draft')
            ->whereIn('pieces.id', $selectedIds)
            ->get()->getResultArray(), 'id');
        if ($pieceIds === []) {
            return redirect()->to('/colecoes/' . $id)->with('error', 'Nenhuma peça em Draft válida foi selecionada.');
        }

        $now = date('Y-m-d H:i:s');
        $labels = [
            'publish' => 'publicada(s)',
            'embargo' => 'embargada(s)',
            'restrict' => 'com acesso restrito',
            'remove' => 'removida(s) da coleção',
        ];
        if ($action === 'remove') {
            $db->table('piece_collections')->where('collection_id', $id)->whereIn('piece_id', $pieceIds)->delete();
        } else {
            $changes = match ($action) {
                'publish' => ['status' => 'published', 'visibility' => 'public', 'access_condition' => 'public', 'updated_at' => $now],
                'embargo' => ['visibility' => 'private', 'access_condition' => 'embargoed', 'updated_at' => $now],
                'restrict' => ['visibility' => 'internal', 'access_condition' => 'restricted', 'updated_at' => $now],
            };
            $db->table('pieces')->whereIn('id', $pieceIds)->update($changes);
        }
        (new AuditLogger())->record('collection.bulk_action', 'collection', $id, ['action' => $action, 'piece_ids' => $pieceIds, 'count' => count($pieceIds)]);

        return redirect()->to('/colecoes/' . $id)->with('success', count($pieceIds) . ' peça(s) ' . $labels[$action] . '.');
    }

    public function create(string $projectId)
    {
        $project = (new ProjectModel())->find($projectId);
        if (! $project) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if ($this->request->getMethod() === 'POST') {
            $model = new CollectionModel();
            $collectionId = UuidModel::generateUuid();
            $coverImage = $this->request->getFile('cover_image');
            $coverImagePath = null;

            if ($coverImage !== null && $coverImage->getError() !== UPLOAD_ERR_NO_FILE) {
                if (! $coverImage->isValid()) {
                    return redirect()->back()->withInput()->with('error', 'Não foi possível receber a imagem da coleção.');
                }
                if ($coverImage->getSize() > 5 * 1024 * 1024) {
                    return redirect()->back()->withInput()->with('error', 'A imagem representativa deve ter no máximo 5 MB.');
                }

                $mimeType = $coverImage->getMimeType();
                if (! isset(self::COVER_IMAGE_TYPES[$mimeType])) {
                    return redirect()->back()->withInput()->with('error', 'Use uma imagem JPEG, PNG, WebP ou GIF.');
                }

                $relativeDirectory = 'collection-covers/' . $projectId;
                $absoluteDirectory = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
                if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0750, true) && ! is_dir($absoluteDirectory)) {
                    log_message('error', 'Could not create collection cover image directory for community {projectId}.', ['projectId' => $projectId]);
                    return redirect()->back()->withInput()->with('error', 'Não foi possível preparar o armazenamento da imagem.');
                }

                $coverImagePath = $relativeDirectory . '/' . $collectionId . '.' . self::COVER_IMAGE_TYPES[$mimeType];
                try {
                    $coverImage->move($absoluteDirectory, $collectionId . '.' . self::COVER_IMAGE_TYPES[$mimeType]);
                } catch (HTTPException $exception) {
                    log_message('error', 'Could not store collection cover image: {error}', ['error' => $exception->getMessage()]);
                    return redirect()->back()->withInput()->with('error', 'Não foi possível armazenar a imagem representativa.');
                }
            }

            $data = [
                'id' => $collectionId,
                'project_id' => $projectId,
                'code' => strtoupper($project['code']) . '-' . $collectionId,
                'name' => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')),
                'cover_image' => $coverImagePath,
            ];
            if ($model->insert($data)) {
                (new AuditLogger())->record('collection.created', 'collection', $collectionId, ['project_id' => $projectId, 'code' => $data['code']]);
                return redirect()->to('/comunidades/' . $projectId)->with('success', 'Coleção criada.');
            }
            if ($coverImagePath !== null) {
                $storedImagePath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $coverImagePath);
                if (is_file($storedImagePath)) {
                    unlink($storedImagePath);
                }
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('collections/form', ['title' => 'Nova coleção', 'project' => $project]);
    }

    private function manageableCollection(string $id): ?array
    {
        $collection = db_connect()->table('collections')->where('collections.deleted_at', null)->select('collections.*, projects.name AS community_name')
            ->join('projects', 'projects.id = collections.project_id')->where('collections.id', $id)
            ->where('projects.status !=', 'excluded')->get()->getRowArray();
        if (! $collection) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return (new ProjectAccess())->allows((string) session('user_id'), $collection['project_id'], 'projects.manage') ? $collection : null;
    }

    public function edit(string $id)
    {
        $collection = $this->manageableCollection($id);
        if (! $collection) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para editar esta coleção.');
        }
        if ($this->request->getMethod() === 'POST') {
            $data = ['name' => trim((string) $this->request->getPost('name')), 'description' => trim((string) $this->request->getPost('description'))];
            if ($data['name'] === '' || mb_strlen($data['name']) > 190) {
                return redirect()->to('/colecoes/' . $id . '/editar')->withInput()->with('error', 'Informe um nome de até 190 caracteres.');
            }
            $db = db_connect();
            $db->transStart();
            $db->table('collections')->where('collections.deleted_at', null)->where('id', $id)->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogger())->record('collection.updated', 'collection', $id);
            $db->transComplete();
            return redirect()->to('/comunidades/' . $collection['project_id'])->with($db->transStatus() ? 'success' : 'error', $db->transStatus() ? 'Coleção atualizada.' : 'Não foi possível atualizar a coleção.');
        }
        return view('collections/manage', ['title' => 'Editar coleção', 'collection' => $collection, 'mode' => 'edit']);
    }

    public function changeImage(string $id)
    {
        $collection = $this->manageableCollection($id);
        if (! $collection) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para trocar a imagem desta coleção.');
        }
        if ($this->request->getMethod() === 'POST') {
            $image = $this->request->getFile('cover_image');
            if (! $image || ! $image->isValid() || $image->getSize() > 5 * 1024 * 1024) {
                return redirect()->to('/colecoes/' . $id . '/trocar-imagem')->with('error', 'Selecione uma imagem válida de até 5 MB.');
            }
            $mime = $image->getMimeType();
            $dimensions = @getimagesize($image->getTempName());
            if (! isset(self::COVER_IMAGE_TYPES[$mime]) || ! $dimensions || ($dimensions['mime'] ?? '') !== $mime || $dimensions[0] * $dimensions[1] > 40000000) {
                return redirect()->to('/colecoes/' . $id . '/trocar-imagem')->with('error', 'Use uma imagem JPEG, PNG, WebP ou GIF de até 40 megapixels.');
            }
            $directory = 'collection-covers/' . $collection['project_id'];
            $filename = $id . '-' . UuidModel::generateUuid() . '.' . self::COVER_IMAGE_TYPES[$mime];
            try {
                $image->move(WRITEPATH . $directory, $filename);
            } catch (HTTPException $exception) {
                return redirect()->to('/colecoes/' . $id . '/trocar-imagem')->with('error', 'Não foi possível armazenar a imagem. Tente novamente.');
            }
            $db = db_connect();
            $db->transStart();
            $db->table('collections')->where('collections.deleted_at', null)->where('id', $id)->update(['cover_image' => $directory . '/' . $filename, 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogger())->record('collection.image_updated', 'collection', $id);
            $db->transComplete();
            return redirect()->to('/comunidades/' . $collection['project_id'])->with($db->transStatus() ? 'success' : 'error', $db->transStatus() ? 'Imagem da coleção atualizada.' : 'Não foi possível atualizar a imagem da coleção.');
        }
        return view('collections/manage', ['title' => 'Trocar imagem', 'collection' => $collection, 'mode' => 'image']);
    }

    public function delete(string $id)
    {
        $collection = $this->manageableCollection($id);
        if (! $collection) {
            return redirect()->to('/comunidades')->with('error', 'Você não tem permissão para deletar esta coleção.');
        }
        if ($this->request->getMethod() === 'POST') {
            if ($this->request->getPost('confirmed') !== '1') {
                return redirect()->to('/colecoes/' . $id . '/deletar')->with('error', 'Confirme a exclusão da coleção.');
            }
            $db = db_connect();
            $db->transStart();
            (new CollectionModel())->delete($id);
            (new AuditLogger())->record('collection.hidden', 'collection', $id, ['project_id' => $collection['project_id']]);
            $db->transComplete();
            $response = redirect()->to('/comunidades/' . $collection['project_id']);
            if ($db->transStatus() && $this->request->getCookie('mosaico_selected_collection') === $id) {
                $response->deleteCookie('mosaico_selected_collection');
            }
            return $response->with($db->transStatus() ? 'success' : 'error', $db->transStatus() ? 'Coleção ocultada. Os dados e arquivos foram preservados.' : 'Não foi possível ocultar a coleção.');
        }
        return view('collections/manage', ['title' => 'Deletar coleção', 'collection' => $collection, 'mode' => 'delete']);
    }

    public function select(string $id)
    {
        $collection = db_connect()->table('collections')->where('collections.deleted_at', null)
            ->select('collections.*, projects.status AS community_status')
            ->join('projects', 'projects.id = collections.project_id')
            ->where('collections.id', $id)
            ->get()
            ->getRowArray();

        if (! $collection || $collection['community_status'] === 'excluded') {
            return redirect()->back()->with('error', 'Coleção indisponível para seleção.');
        }

        (new AuditLogger())->record('collection.selected', 'collection', $id, ['project_id' => $collection['project_id']]);

        return redirect()->to('/colecoes/' . $id)
            ->setCookie('mosaico_selected_collection', $id, 60 * 60 * 24 * 30, '', '/', '', $this->request->isSecure(), true, 'Lax')
            ->setCookie('mosaico_selected_community', $collection['project_id'], 60 * 60 * 24 * 30, '', '/', '', $this->request->isSecure(), true, 'Lax')
            ->with('success', 'Coleção selecionada para a navegação.');
    }

    public function upload(string $id)
    {
        $db = db_connect();
        $collection = $db->table('collections')->where('collections.deleted_at', null)
            ->select('collections.*, projects.status AS community_status')
            ->join('projects', 'projects.id = collections.project_id')
            ->where('collections.id', $id)
            ->get()
            ->getRowArray();

        if (! $collection || $collection['community_status'] === 'excluded') {
            return redirect()->back()->with('error', 'Coleção indisponível para upload.');
        }
        if (! $this->canManage((string) $collection['project_id'])) {
            return redirect()->back()->with('error', 'Você não tem permissão para enviar arquivos nesta coleção.');
        }

        $files = $this->request->getFileMultiple('files');
        if (! $files) {
            return redirect()->back()->with('error', 'Selecione pelo menos um arquivo.');
        }

        $termIds = $this->resolveTermIds(
            (array) $this->request->getPost('term_ids'),
            (string) $this->request->getPost('new_keywords'),
            (string) $collection['project_id'],
        );

        $created = 0;
        $restored = 0;
        $restoredFiles = [];
        $errors = [];
        foreach ($files as $file) {
            $originalName = basename($file->getClientName());
            $extension = strtolower($file->getClientExtension());
            if (! $file->isValid()) {
                $errors[] = $originalName . ': ' . $this->uploadErrorMessage($file->getError());
                continue;
            }
            if ($file->hasMoved()) {
                $errors[] = $originalName . ': o arquivo já foi processado.';
                continue;
            }
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                $errors[] = $originalName . ': excede o limite de 1 GB por arquivo.';
                continue;
            }
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $errors[] = $originalName . ': formato não permitido.';
                continue;
            }
            $detectedMime = strtolower($file->getMimeType());
            if (! str_starts_with($detectedMime, 'image/') && ! str_starts_with($detectedMime, 'video/') && ! str_starts_with($detectedMime, 'audio/') && ! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
                $errors[] = $originalName . ': tipo de conteúdo não permitido.';
                continue;
            }

            $checksum = hash_file('sha256', $file->getTempName());
            if ($checksum === false) {
                $errors[] = $originalName . ': não foi possível calcular o checksum.';
                continue;
            }
            $existingFile = $db->table('repository_files')
                ->select('repository_files.id AS file_id, repository_files.original_name, assets.piece_id, pieces.project_id')
                ->join('asset_versions', 'asset_versions.id = repository_files.version_id')
                ->join('assets', 'assets.id = asset_versions.asset_id')
                ->join('pieces', 'pieces.id = assets.piece_id')
                ->where('repository_files.sha256', $checksum)
                ->get()
                ->getRowArray();
            if ($existingFile) {
                if ($existingFile['project_id'] !== $collection['project_id']) {
                    $errors[] = $originalName . ': este conteúdo já pertence a outra comunidade.';
                    continue;
                }
                $db->table('pieces')->where('id', $existingFile['piece_id'])->update(['status' => 'draft', 'visibility' => 'private', 'updated_at' => date('Y-m-d H:i:s')]);
                $linked = $db->table('piece_collections')->where(['piece_id' => $existingFile['piece_id'], 'collection_id' => $id])->countAllResults() > 0;
                if (! $linked) {
                    $db->table('piece_collections')->insert(['piece_id' => $existingFile['piece_id'], 'collection_id' => $id]);
                }
                $this->attachTerms((string) $existingFile['piece_id'], $termIds);
                (new AuditLogger())->record('collection.file_restored', 'collection', $id, ['file_id' => $existingFile['file_id'], 'piece_id' => $existingFile['piece_id'], 'sha256' => $checksum, 'submitted_name' => $originalName]);
                $restoredFiles[] = $originalName . ': conteúdo idêntico ao arquivo ' . $existingFile['original_name'] . '. A peça existente foi restaurada para Draft e está disponível nesta coleção.';
                $restored++;
                continue;
            }

            $pieceId = UuidModel::generateUuid();
            $assetId = UuidModel::generateUuid();
            $versionId = UuidModel::generateUuid();
            $fileId = UuidModel::generateUuid();
            $relativeDirectory = 'repository/' . $collection['project_id'] . '/' . $id . '/' . $pieceId . '/' . $versionId;
            $absoluteDirectory = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0750, true) && ! is_dir($absoluteDirectory)) {
                $errors[] = $originalName . ': não foi possível preparar o armazenamento.';
                continue;
            }

            $storedName = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/u', '_', $originalName) ?: $fileId . '.' . $extension;
            $file->move($absoluteDirectory, $storedName);
            $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;
            $now = date('Y-m-d H:i:s');
            $title = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Arquivo sem título';
            $mime = mime_content_type($absolutePath) ?: $detectedMime;
            $pieceType = str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'image/') ? 'imagem' : 'documento'));

            try {
                $db->transBegin();
                $db->table('pieces')->insert(['id' => $pieceId, 'project_id' => $collection['project_id'], 'code' => 'UP-' . strtoupper(substr(str_replace('-', '', $pieceId), 0, 12)), 'title' => $title, 'summary' => 'Criada automaticamente por upload na coleção ' . $collection['name'] . '.', 'type' => $pieceType, 'original_directory' => null, 'original_filename' => $originalName, 'status' => 'draft', 'visibility' => 'private', 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
                $db->table('piece_collections')->insert(['piece_id' => $pieceId, 'collection_id' => $id]);
                $db->table('assets')->insert(['id' => $assetId, 'piece_id' => $pieceId, 'name' => $title, 'type' => $pieceType, 'description' => 'Ativo original enviado para a coleção.', 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
                $db->table('asset_versions')->insert(['id' => $versionId, 'asset_id' => $assetId, 'version_number' => 1, 'change_notes' => 'Versão inicial criada por upload.', 'status' => 'draft', 'created_by' => session('user_id'), 'created_at' => $now]);
                $db->table('repository_files')->insert(['id' => $fileId, 'version_id' => $versionId, 'original_name' => $originalName, 'storage_key' => $relativeDirectory . '/' . $storedName, 'mime_type' => $mime, 'size_bytes' => filesize($absolutePath), 'sha256' => $checksum, 'created_at' => $now]);
                foreach ($termIds as $termId) {
                    $db->table('piece_terms')->insert(['piece_id' => $pieceId, 'term_id' => $termId]);
                }
                if (! $db->transStatus()) {
                    throw new \RuntimeException('Falha na transação do arquivo.');
                }
                $db->transCommit();
            } catch (\Throwable) {
                $db->transRollback();
                if (method_exists($db, 'resetTransStatus')) {
                    $db->resetTransStatus();
                }
                @unlink($absolutePath);
                $errors[] = $originalName . ': falha ao registrar no repositório.';
                continue;
            }
            (new AuditLogger())->record('collection.file_uploaded', 'collection', $id, ['file_id' => $fileId, 'piece_id' => $pieceId, 'sha256' => $checksum, 'original_name' => $originalName, 'term_ids' => $termIds]);
            $created++;
        }

        if ($created === 0 && $restored === 0) {
            return redirect()->back()->with('errors', $errors ?: ['Nenhum arquivo foi processado.']);
        }
        $message = $created > 0 ? $created . ($created === 1 ? ' arquivo enviado.' : ' arquivos enviados.') : '';
        if ($restored > 0) {
            $message .= ' Aviso: ' . $restored . ($restored === 1 ? ' arquivo já existe. ' : ' arquivos já existem. ') . implode(' ', $restoredFiles);
        }
        if ($errors !== []) {
            $message .= ' Alguns arquivos foram rejeitados: ' . implode(' ', $errors);
        }
        return redirect()->to('/colecoes/' . $id)->with($restored > 0 || $errors !== [] ? 'warning' : 'success', trim($message));
    }

    public function importGoogleDrive(string $id)
    {
        ini_set('display_errors', '0');
        ignore_user_abort(true);
        set_time_limit(0);
        $db = db_connect();
        $collection = $db->table('collections')->where('collections.deleted_at', null)
            ->select('collections.*, projects.status AS community_status')
            ->join('projects', 'projects.id = collections.project_id')
            ->where('collections.id', $id)
            ->get()
            ->getRowArray();

        $this->response->setHeader('Content-Type', 'application/x-ndjson; charset=UTF-8');
        $this->response->setHeader('Cache-Control', 'no-cache, no-store');
        $this->response->setHeader('X-Accel-Buffering', 'no');
        // Send and clear framework-managed headers/cookies before streaming.
        // The explicit exits below prevent CodeIgniter from sending them again.
        $this->response->send();
        $emit = static function (array $event): void {
            echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        };

        if (! $collection || $collection['community_status'] === 'excluded') {
            $emit(['type' => 'fatal', 'message' => 'Coleção indisponível para importação.']);
            exit;
        }
        if (! $this->canManage((string) $collection['project_id'])) {
            $emit(['type' => 'fatal', 'message' => 'Você não tem permissão para importar arquivos nesta coleção.']);
            exit;
        }

        $importer = new GoogleDriveImporter();
        $folderId = $importer->extractFolderId((string) $this->request->getPost('drive_url'));
        if ($folderId === null) {
            $emit(['type' => 'fatal', 'message' => 'Informe uma URL válida de pasta do Google Drive.']);
            exit;
        }

        try {
            $files = $importer->listFiles($folderId);
            $emit(['type' => 'discovered', 'files' => array_map(static fn (array $file): array => ['id' => $file['id'], 'name' => $file['name']], $files)]);
            $created = 0;
            $restored = 0;
            foreach ($files as $file) {
                $temporaryPath = tempnam(WRITEPATH . 'cache', 'drive_');
                if ($temporaryPath === false) {
                    $emit(['type' => 'file', 'id' => $file['id'], 'name' => $file['name'], 'status' => 'error', 'message' => 'Não foi possível preparar o download.']);
                    continue;
                }
                try {
                    $emit(['type' => 'file', 'id' => $file['id'], 'name' => $file['name'], 'status' => 'downloading', 'progress' => 0]);
                    $importer->download($file, $temporaryPath, static function (int $percent, int $current, int $total) use ($emit, $file): void {
                        $emit(['type' => 'file', 'id' => $file['id'], 'name' => $file['name'], 'status' => 'downloading', 'progress' => $percent, 'bytes' => $current, 'total' => $total]);
                    });
                    $result = $this->ingestDriveFile($temporaryPath, (string) $file['name'], $collection, $id);
                    $created += $result === 'created' ? 1 : 0;
                    $restored += $result === 'restored' ? 1 : 0;
                    $emit(['type' => 'file', 'id' => $file['id'], 'name' => $file['name'], 'status' => 'imported', 'message' => $result === 'restored' ? 'Arquivo existente restaurado para Draft.' : 'Copiado para o repositório do MOSAICO.']);
                } catch (\Throwable $exception) {
                    $emit(['type' => 'file', 'id' => $file['id'], 'name' => $file['name'], 'status' => 'error', 'message' => $exception->getMessage()]);
                } finally {
                    if (is_file($temporaryPath)) {
                        @unlink($temporaryPath);
                    }
                }
            }
            (new AuditLogger())->record('collection.google_drive_imported', 'collection', $id, ['folder_id' => $folderId, 'created' => $created, 'restored' => $restored]);
            $emit([
                'type' => 'complete',
                'created' => $created,
                'restored' => $restored,
                'message' => 'Importação concluída: ' . $created . ' novo(s), ' . $restored . ' restaurado(s).',
            ]);
        } catch (\Throwable $exception) {
            $emit(['type' => 'fatal', 'message' => $exception->getMessage()]);
        }

        exit;
    }

    private function ingestDriveFile(string $sourcePath, string $originalName, array $collection, string $collectionId): string
    {
        $db = db_connect();
        $sourceName = str_replace('\\', '/', trim($originalName));
        $sourceParts = array_values(array_filter(array_map('trim', explode('/', $sourceName)), static fn (string $part): bool => $part !== ''));
        $originalName = array_pop($sourceParts) ?: basename($sourceName);
        $sourceDirectory = $sourceParts === [] ? null : implode('/', $sourceParts);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = filesize($sourcePath);
        if ($size === false || $size === 0) {
            throw new \RuntimeException('O arquivo recebido está vazio.');
        }
        if ($size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('O arquivo excede o limite de 1 GB.');
        }
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Formato não permitido.');
        }
        $mime = strtolower(mime_content_type($sourcePath) ?: 'application/octet-stream');
        if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/') && ! str_starts_with($mime, 'audio/') && ! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException('Tipo de conteúdo não permitido.');
        }
        $checksum = hash_file('sha256', $sourcePath);
        if ($checksum === false) {
            throw new \RuntimeException('Não foi possível calcular o checksum.');
        }
        $existingFile = $db->table('repository_files')
            ->select('repository_files.id AS file_id, assets.piece_id, pieces.project_id')
            ->join('asset_versions', 'asset_versions.id = repository_files.version_id')
            ->join('assets', 'assets.id = asset_versions.asset_id')
            ->join('pieces', 'pieces.id = assets.piece_id')
            ->where('repository_files.sha256', $checksum)
            ->get()->getRowArray();
        if ($existingFile) {
            if ($existingFile['project_id'] !== $collection['project_id']) {
                throw new \RuntimeException('Este conteúdo já pertence a outra comunidade.');
            }
            $db->table('pieces')->where('id', $existingFile['piece_id'])->update(['status' => 'draft', 'visibility' => 'private', 'updated_at' => date('Y-m-d H:i:s')]);
            if ($db->table('piece_collections')->where(['piece_id' => $existingFile['piece_id'], 'collection_id' => $collectionId])->countAllResults() === 0) {
                $db->table('piece_collections')->insert(['piece_id' => $existingFile['piece_id'], 'collection_id' => $collectionId]);
            }
            (new AuditLogger())->record('collection.drive_file_restored', 'collection', $collectionId, ['file_id' => $existingFile['file_id'], 'piece_id' => $existingFile['piece_id'], 'sha256' => $checksum]);
            return 'restored';
        }

        $pieceId = UuidModel::generateUuid();
        $assetId = UuidModel::generateUuid();
        $versionId = UuidModel::generateUuid();
        $fileId = UuidModel::generateUuid();
        $relativeDirectory = 'repository/' . $collection['project_id'] . '/' . $collectionId . '/' . $pieceId . '/' . $versionId;
        $absoluteDirectory = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0750, true) && ! is_dir($absoluteDirectory)) {
            throw new \RuntimeException('Não foi possível preparar o armazenamento.');
        }
        $storedName = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/u', '_', $originalName) ?: $fileId . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;
        if (! rename($sourcePath, $absolutePath)) {
            throw new \RuntimeException('Não foi possível copiar o arquivo para o repositório.');
        }
        $now = date('Y-m-d H:i:s');
        $title = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Arquivo sem título';
        $pieceType = str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'image/') ? 'imagem' : 'documento'));
        try {
            $db->transBegin();
            $db->table('pieces')->insert(['id' => $pieceId, 'project_id' => $collection['project_id'], 'code' => 'GD-' . strtoupper(substr(str_replace('-', '', $pieceId), 0, 12)), 'title' => $title, 'summary' => 'Importada do Google Drive para a coleção ' . $collection['name'] . '.', 'type' => $pieceType, 'original_directory' => $sourceDirectory ?: null, 'original_filename' => $originalName, 'status' => 'draft', 'visibility' => 'private', 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
            $db->table('piece_collections')->insert(['piece_id' => $pieceId, 'collection_id' => $collectionId]);
            $db->table('assets')->insert(['id' => $assetId, 'piece_id' => $pieceId, 'name' => $title, 'type' => $pieceType, 'description' => 'Ativo original importado do Google Drive.', 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
            $db->table('asset_versions')->insert(['id' => $versionId, 'asset_id' => $assetId, 'version_number' => 1, 'change_notes' => 'Versão inicial importada do Google Drive.', 'status' => 'draft', 'created_by' => session('user_id'), 'created_at' => $now]);
            $db->table('repository_files')->insert(['id' => $fileId, 'version_id' => $versionId, 'original_name' => $originalName, 'storage_key' => $relativeDirectory . '/' . $storedName, 'mime_type' => $mime, 'size_bytes' => $size, 'sha256' => $checksum, 'created_at' => $now]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Falha ao registrar o arquivo no repositório.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            @unlink($absolutePath);
            throw $exception;
        }
        (new AuditLogger())->record('collection.drive_file_imported', 'collection', $collectionId, ['file_id' => $fileId, 'piece_id' => $pieceId, 'sha256' => $checksum, 'original_name' => $originalName]);

        return 'created';
    }

    private function canManage(string $projectId): bool
    {
        return db_connect()->table('memberships')
            ->join('roles', 'roles.id = memberships.role_id')
            ->where('memberships.project_id', $projectId)
            ->where('memberships.user_id', session('user_id'))
            ->whereIn('roles.code', ['administrator', 'repository_manager', 'producer_editor'])
            ->countAllResults() > 0;
    }

    private function resolveTermIds(array $selectedIds, string $newKeywords, string $projectId): array
    {
        $db = db_connect();
        $selectedIds = array_values(array_unique(array_filter($selectedIds, static fn ($value): bool => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1)));
        $validIds = $selectedIds === [] ? [] : array_column(
            $db->table('terms')
                ->select('terms.id')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('vocabularies.project_id', $projectId)
                ->where('terms.is_active', true)
                ->whereIn('terms.id', $selectedIds)
                ->get()
                ->getResultArray(),
            'id',
        );

        $keywords = array_slice(array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\r\n]+/u', $newKeywords) ?: [])))), 0, 20);
        if ($keywords === []) {
            return $validIds;
        }

        $vocabulary = $db->table('vocabularies')
            ->where('project_id', $projectId)
            ->where('name', 'Palavras-chave')
            ->get()
            ->getRowArray();
        if (! $vocabulary) {
            $vocabularyId = UuidModel::generateUuid();
            $now = date('Y-m-d H:i:s');
            $baseCode = 'keywords-' . substr(str_replace('-', '', $projectId), 0, 8);
            $code = $baseCode;
            $suffix = 2;
            while ($db->table('vocabularies')->where('code', $code)->countAllResults() > 0) {
                $code = $baseCode . '-' . $suffix++;
            }
            $db->table('vocabularies')->insert([
                'id'          => $vocabularyId,
                'project_id'  => $projectId,
                'code'        => $code,
                'name'        => 'Palavras-chave',
                'description' => 'Palavras-chave criadas durante a ingestão de arquivos.',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            $vocabularyId = $vocabulary['id'];
        }

        foreach ($keywords as $keyword) {
            $label = mb_substr($keyword, 0, 190);
            $baseCode = mb_substr(url_title($label, '-', true), 0, 70) ?: 'tag';
            $term = $db->table('terms')->where('vocabulary_id', $vocabularyId)->where('code', $baseCode)->get()->getRowArray();
            if (! $term) {
                $termId = UuidModel::generateUuid();
                $now = date('Y-m-d H:i:s');
                $db->table('terms')->insert(['id' => $termId, 'vocabulary_id' => $vocabularyId, 'code' => $baseCode, 'label' => $label, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            } else {
                $termId = $term['id'];
            }
            $validIds[] = $termId;
        }

        return array_values(array_unique($validIds));
    }

    private function attachTerms(string $pieceId, array $termIds): void
    {
        $db = db_connect();
        foreach ($termIds as $termId) {
            $exists = $db->table('piece_terms')->where(['piece_id' => $pieceId, 'term_id' => $termId])->countAllResults() > 0;
            if (! $exists) {
                $db->table('piece_terms')->insert(['piece_id' => $pieceId, 'term_id' => $termId]);
            }
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'excede o limite de tamanho permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'o envio foi interrompido antes de terminar.',
            UPLOAD_ERR_NO_FILE => 'nenhum arquivo foi recebido.',
            UPLOAD_ERR_NO_TMP_DIR => 'o diretório temporário do servidor não está disponível.',
            UPLOAD_ERR_CANT_WRITE => 'o servidor não conseguiu gravar o arquivo temporário.',
            UPLOAD_ERR_EXTENSION => 'o envio foi interrompido por uma extensão do servidor.',
            default => 'não foi possível receber o arquivo.',
        };
    }
}
