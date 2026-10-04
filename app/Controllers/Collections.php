<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Models\CollectionModel;
use App\Models\ProjectModel;
use App\Models\UuidModel;

final class Collections extends BaseController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm', 'avi', 'mp3', 'wav', 'ogg', 'docx', 'xlsx', 'pptx', 'txt', 'csv'];
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv', 'application/csv'];
    private const MAX_FILE_SIZE = 1073741824;

    public function show(string $id): string
    {
        $db = db_connect();
        $collection = $db->table('collections')
            ->select('collections.*, projects.name AS community_name, projects.id AS community_id')
            ->join('projects', 'projects.id = collections.project_id')
            ->where('collections.id', $id)
            ->where('projects.status !=', 'excluded')
            ->get()
            ->getRowArray();

        if (! $collection) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $viewMode = (string) $this->request->getGet('view');
        if (! in_array($viewMode, ['list', 'details', 'thumbnail', 'cards'], true)) {
            $viewMode = 'cards';
        }

        $selectedTermId = (string) $this->request->getGet('term');
        $selectedTerm = null;
        if ($selectedTermId !== '') {
            $selectedTerm = $db->table('terms')->select('id, label')->where('id', $selectedTermId)->where('is_active', true)->get()->getRowArray();
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
        $pieces = $piecesBuilder->orderBy('pieces.title')->get()->getResultArray();
        $keywordsByPiece = [];
        if ($pieces !== []) {
            $keywordRows = $db->table('piece_terms')
                ->select('piece_terms.piece_id, terms.id, terms.label')
                ->join('terms', 'terms.id = piece_terms.term_id')
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
        }
        unset($piece);

        return view('collections/show', [
            'title' => $collection['name'],
            'collection' => $collection,
            'viewMode' => $viewMode,
            'selectedTerm' => $selectedTerm,
            'pieces' => $pieces,
            'terms' => $db->table('terms')
                ->select('terms.id, terms.label, vocabularies.name AS vocabulary_name')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('terms.is_active', true)
                ->orderBy('vocabularies.name')
                ->orderBy('terms.label')
                ->get()
                ->getResultArray(),
        ]);
    }

    public function removeDrafts(string $id)
    {
        $db = db_connect();
        $collection = $db->table('collections')->where('id', $id)->get()->getRowArray();
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

    public function create(string $projectId)
    {
        $project = (new ProjectModel())->find($projectId);
        if (! $project) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if ($this->request->getMethod() === 'POST') {
            $model = new CollectionModel();
            $collectionId = UuidModel::generateUuid();
            $data = [
                'id' => $collectionId,
                'project_id' => $projectId,
                'code' => strtoupper($project['code']) . '-' . $collectionId,
                'name' => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')),
            ];
            if ($model->insert($data)) {
                (new AuditLogger())->record('collection.created', 'collection', $collectionId, ['project_id' => $projectId, 'code' => $data['code']]);
                return redirect()->to('/comunidades/' . $projectId)->with('success', 'Coleção criada.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('collections/form', ['title' => 'Nova coleção', 'project' => $project]);
    }

    public function select(string $id)
    {
        $collection = db_connect()->table('collections')
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
        $collection = $db->table('collections')
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

        $termIds = $this->resolveTermIds((array) $this->request->getPost('term_ids'), (string) $this->request->getPost('new_keywords'));

        $created = 0;
        $restored = 0;
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
            $detectedMime = $file->getMimeType();
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
                ->select('repository_files.id AS file_id, assets.piece_id, pieces.project_id')
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
                (new AuditLogger())->record('collection.file_restored', 'collection', $id, ['file_id' => $existingFile['file_id'], 'piece_id' => $existingFile['piece_id'], 'sha256' => $checksum]);
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
                $db->table('pieces')->insert(['id' => $pieceId, 'project_id' => $collection['project_id'], 'code' => 'UP-' . strtoupper(substr(str_replace('-', '', $pieceId), 0, 12)), 'title' => $title, 'summary' => 'Criada automaticamente por upload na coleção ' . $collection['name'] . '.', 'type' => $pieceType, 'status' => 'draft', 'visibility' => 'private', 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
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
        $message = $created . ($created === 1 ? ' arquivo enviado.' : ' arquivos enviados.');
        if ($restored > 0) {
            $message .= ' ' . $restored . ($restored === 1 ? ' arquivo existente restaurado para Draft.' : ' arquivos existentes restaurados para Draft.');
        }
        if ($errors !== []) {
            $message .= ' Alguns arquivos foram rejeitados: ' . implode(' ', $errors);
        }
        return redirect()->to('/colecoes/' . $id)->with('success', $message);
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

    private function resolveTermIds(array $selectedIds, string $newKeywords): array
    {
        $db = db_connect();
        $selectedIds = array_values(array_unique(array_filter($selectedIds, static fn ($value): bool => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1)));
        $validIds = $selectedIds === [] ? [] : array_column($db->table('terms')->select('id')->where('is_active', true)->whereIn('id', $selectedIds)->get()->getResultArray(), 'id');

        $keywords = array_slice(array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\r\n]+/u', $newKeywords) ?: [])))), 0, 20);
        if ($keywords === []) {
            return $validIds;
        }

        $vocabulary = $db->table('vocabularies')->where('code', 'keywords')->get()->getRowArray();
        if (! $vocabulary) {
            $vocabularyId = UuidModel::generateUuid();
            $now = date('Y-m-d H:i:s');
            $db->table('vocabularies')->insert(['id' => $vocabularyId, 'code' => 'keywords', 'name' => 'Palavras-chave', 'description' => 'Palavras-chave criadas durante a ingestão de arquivos.', 'created_at' => $now, 'updated_at' => $now]);
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
