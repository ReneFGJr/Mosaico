<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Libraries\PieceThumbnail;
use App\Libraries\ProjectAccess;
use App\Libraries\RangeFileResponse;
use App\Models\PieceModel;
use App\Models\UuidModel;

final class Pieces extends BaseController
{
    public function index(?string $collectionId = null): string
    {
        $query = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('pieces')->select('pieces.*, projects.name AS project_name')->join('projects', 'projects.id = pieces.project_id');
        $collection = null;
        if ($collectionId !== null) {
            $collection = db_connect()->table('collections')->where('deleted_at', null)->where('id', $collectionId)->get()->getRowArray();
            if (! $collection) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            $builder->join('piece_collections', 'piece_collections.piece_id = pieces.id')->where('piece_collections.collection_id', $collectionId);
        }
        if ($query !== '') {
            $builder->groupStart()->like('pieces.title', $query)->orLike('pieces.code', $query)->orLike('pieces.summary', $query)->groupEnd();
        }
        return view('pieces/index', ['title' => 'Peças', 'pieces' => $builder->orderBy('pieces.updated_at', 'DESC')->get()->getResultArray(), 'query' => $query, 'collection' => $collection]);
    }

    public function create()
    {
        $db = db_connect();
        $projects = $db->table('projects')->orderBy('name')->get()->getResultArray();
        $requestedCollectionId = (string) $this->request->getGet('collection');
        $requestedCollection = $requestedCollectionId !== '' ? $db->table('collections')->where('deleted_at', null)->where('id', $requestedCollectionId)->get()->getRowArray() : null;
        if ($this->request->getMethod() === 'POST') {
            $model = new PieceModel();
            $data = ['project_id' => (string) $this->request->getPost('project_id'), 'code' => strtoupper(trim((string) $this->request->getPost('code'))), 'title' => trim((string) $this->request->getPost('title')), 'summary' => trim((string) $this->request->getPost('summary')), 'type' => (string) $this->request->getPost('type'), 'pedagogical_purpose' => trim((string) $this->request->getPost('pedagogical_purpose')), 'audience' => trim((string) $this->request->getPost('audience')), 'status' => 'draft', 'visibility' => 'private', 'created_by' => session('user_id')];
            if ($model->insert($data)) {
                $id = (string) $model->getInsertID();
                $collectionId = (string) $this->request->getPost('collection_id');
                if ($collectionId !== '') {
                    $db->table('piece_collections')->insert(['piece_id' => $id, 'collection_id' => $collectionId]);
                }
                (new AuditLogger())->record('piece.created', 'piece', $id, ['code' => $data['code']]);
                return redirect()->to('/pecas/' . $id)->with('success', 'Peça cadastrada.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('pieces/form', ['title' => 'Nova peça', 'projects' => $projects, 'collections' => $db->table('collections')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(), 'selectedProjectId' => $requestedCollection['project_id'] ?? (string) $this->request->getGet('project'), 'selectedCollectionId' => $requestedCollectionId]);
    }

    public function show(string $id): string
    {
        $db = db_connect();
        $piece = $db->table('pieces')->select('pieces.*, projects.name AS project_name')->join('projects', 'projects.id = pieces.project_id')->where('pieces.id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $assets = $db->table('assets AS piece_assets')->select('piece_assets.*, COUNT(versions.version_number) AS version_count, MAX(versions.version_number) AS latest_version')->join('asset_versions AS versions', 'versions.asset_id = piece_assets.id', 'left')->where('piece_assets.piece_id', $id)->groupBy('piece_assets.id')->orderBy('piece_assets.created_at', 'DESC')->get()->getResultArray();
        $pieceCollection = $db->table('piece_collections')->select('collection_id')->join('collections', 'collections.id = piece_collections.collection_id')->where('collections.deleted_at', null)->where('piece_id', $id)->get()->getRowArray();
        $previewFile = $this->findPreviewFile($id);
        $projectId = (string) $piece['project_id'];
        $vocabularies = $db->table('vocabularies')->where('project_id', $projectId)->orderBy('name')->get()->getResultArray();
        $terms = $db->table('terms AS keyword_terms')
            ->select('keyword_terms.id, keyword_terms.vocabulary_id, keyword_terms.label, vocabularies.name AS vocabulary_name')
            ->join('vocabularies', 'vocabularies.id = keyword_terms.vocabulary_id')
            ->where('vocabularies.project_id', $projectId)
            ->where('keyword_terms.is_active', true)
            ->orderBy('vocabularies.name')
            ->orderBy('keyword_terms.label')
            ->get()
            ->getResultArray();
        $selectedTermIds = array_column($db->table('piece_terms')->select('term_id')->where('piece_id', $id)->get()->getResultArray(), 'term_id');

        return view('pieces/show', [
            'title' => $piece['title'],
            'contentClass' => 'content-wide',
            'piece' => $piece,
            'assets' => $assets,
            'backUrl' => $pieceCollection ? site_url('colecoes/' . $pieceCollection['collection_id']) : site_url('pecas'),
            'previewFile' => $previewFile,
            'previewType' => $this->previewType((string) ($previewFile['mime_type'] ?? '')),
            'imageDescription' => $previewFile ? $db->table('piece_image_descriptions')->where('piece_id', $id)->where('source_file_id', $previewFile['id'])->get()->getRowArray() : null,
            'peopleDetection' => $previewFile ? $this->latestPeopleDetection($id, $previewFile['id']) : null,
            'vocabularies' => $vocabularies,
            'terms' => $terms,
            'selectedTermIds' => $selectedTermIds,
            'canManageKeywords' => $this->canManage((string) $piece['project_id']),
            'canManageCategories' => $this->canManageCategories((string) $piece['project_id']),
        ]);
    }

    public function rename(string $id)
    {
        $db = db_connect();
        $piece = $db->table('pieces')->where('id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para editar o nome desta peça.');
        }
        $title = trim((string) $this->request->getPost('title'));
        if ($title === '' || mb_strlen($title) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $title)) {
            return redirect()->to('/pecas/' . $id)->withInput()->with('rename_form_open', true)->with('error', 'Informe um nome da peça de até 255 caracteres, sem quebras de linha.');
        }
        $file = $this->findPreviewFile($id);
        $data = ['title' => $title, 'updated_at' => date('Y-m-d H:i:s')];
        if ($file) {
            if ((string) $this->request->getPost('source_file_id') !== $file['id']) {
                return redirect()->to('/pecas/' . $id)->with('error', 'O arquivo da peça mudou. Atualize a página e tente novamente.');
            }
            $filename = trim((string) $this->request->getPost('filename'));
            $extension = pathinfo($file['original_name'], PATHINFO_EXTENSION);
            if ($filename === '' || mb_strlen($filename) > 255 || preg_match('~[<>:"/\\\\|?*\x00-\x1F\x7F]~u', $filename)
                || trim(pathinfo($filename, PATHINFO_FILENAME), '. ') === '' || str_ends_with($filename, '.')
                || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($extension)) {
                return redirect()->to('/pecas/' . $id)->withInput()->with('rename_form_open', true)->with('error', 'Informe um nome de arquivo válido de até 255 caracteres e mantenha a extensão original' . ($extension !== '' ? ' (.' . $extension . ')' : '') . '.');
            }
            $data['original_filename'] = $filename;
        }
        $db->transStart();
        $db->table('pieces')->where('id', $id)->update($data);
        (new AuditLogger())->record('piece.renamed', 'piece', $id, ['source_file_id' => $file['id'] ?? null]);
        $db->transComplete();

        return redirect()->to('/pecas/' . $id)->with($db->transStatus() ? 'success' : 'error', $db->transStatus() ? 'Nome da peça e do arquivo atualizados.' : 'Não foi possível atualizar o nome.');
    }

    public function keywords(string $id)
    {
        $db = db_connect();
        $piece = $db->table('pieces')->select('id, project_id')->where('id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para alterar as palavras-chave desta peça.');
        }

        $selectedIds = array_values(array_unique(array_filter((array) $this->request->getPost('term_ids'), static fn ($value): bool => is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1)));
        $validIds = $selectedIds === [] ? [] : array_column(
            $db->table('terms')
                ->select('terms.id')
                ->join('vocabularies', 'vocabularies.id = terms.vocabulary_id')
                ->where('vocabularies.project_id', $piece['project_id'])
                ->where('terms.is_active', true)
                ->whereIn('terms.id', $selectedIds)
                ->get()
                ->getResultArray(),
            'id',
        );
        $categoryName = trim((string) $this->request->getPost('new_category'));
        $keywordLabel = trim((string) $this->request->getPost('new_keyword'));
        if (mb_strlen($categoryName) > 190 || mb_strlen($keywordLabel) > 190) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Categoria e palavra-chave devem possuir no máximo 190 caracteres.');
        }
        if (($categoryName !== '' || $keywordLabel !== '') && ! $this->canManageCategories((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para criar palavras-chave ou categorias.');
        }

        $db->transStart();
        $categoryId = $this->resolveCategory((string) $this->request->getPost('vocabulary_id'), $categoryName, (string) $piece['project_id']);
        if ($keywordLabel !== '') {
            $categoryId ??= $this->defaultKeywordCategory((string) $piece['project_id']);
            $validIds[] = $this->resolveKeyword($categoryId, $keywordLabel);
        }
        $validIds = array_values(array_unique($validIds));

        $db->table('piece_terms')->where('piece_id', $id)->delete();
        foreach ($validIds as $termId) {
            $db->table('piece_terms')->insert(['piece_id' => $id, 'term_id' => $termId]);
        }
        (new AuditLogger())->record('piece.keywords_updated', 'piece', $id, ['term_ids' => $validIds]);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Não foi possível atualizar as palavras-chave.');
        }

        return redirect()->to('/pecas/' . $id)->with('success', 'Palavras-chave atualizadas.');
    }

    public function describeImage(string $id)
    {
        $db = db_connect();
        $piece = $db->table('pieces')->select('id, project_id')->where('id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para gerar a descrição desta imagem.');
        }
        $file = $this->findPreviewFile($id);
        if (! $file || $this->previewType((string) $file['mime_type']) !== 'image') {
            return redirect()->to('/pecas/' . $id)->with('error', 'Esta peça não possui uma imagem disponível para descrição.');
        }
        // Reject a stale form rather than describing a newly uploaded version unexpectedly.
        if ((string) $this->request->getPost('source_file_id') !== $file['id']) {
            return redirect()->to('/pecas/' . $id)->with('error', 'O arquivo da peça mudou. Atualize a página e tente novamente.');
        }

        set_time_limit(150);
        try {
            $result = service('ollamaImageDescriber')->describe($file);
        } catch (\RuntimeException $exception) {
            return redirect()->to('/pecas/' . $id)->with('error', $exception->getMessage());
        }
        $now = date('Y-m-d H:i:s');
        $data = $result + ['piece_id' => $id, 'generated_by' => session('user_id'), 'updated_at' => $now];
        $db->transStart();
        $existing = $db->table('piece_image_descriptions')->where('source_file_id', $file['id'])->get()->getRowArray();
        if ($existing) {
            $db->table('piece_image_descriptions')->where('source_file_id', $file['id'])->update($data);
        } else {
            $db->table('piece_image_descriptions')->insert($data + ['source_file_id' => $file['id'], 'created_at' => $now]);
        }
        (new AuditLogger())->record('piece.image_described', 'piece', $id, ['source_file_id' => $file['id'], 'model' => $result['model']]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Não foi possível salvar a descrição. Tente novamente.');
        }

        return redirect()->to('/pecas/' . $id)->with('success', 'Descrição da imagem gerada. Revise o texto antes de utilizá-lo.');
    }

    public function detectPeople(string $id)
    {
        $file = $this->peopleImageForUpdate($id);
        if (! is_array($file)) {
            return $file;
        }
        set_time_limit(150);
        try {
            $result = service('ollamaImageDescriber')->detectPeople($file);
        } catch (\RuntimeException $exception) {
            return redirect()->to('/pecas/' . $id)->with('error', $exception->getMessage());
        }
        $detectionId = UuidModel::generateUuid();
        $model = $result['model'];
        unset($result['model']);
        $db = db_connect();
        $db->transStart();
        $db->table('piece_people_detections')->insert([
            'id' => $detectionId, 'piece_id' => $id, 'source_file_id' => $file['id'],
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'model' => $model, 'generated_by' => session('user_id'),
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
        (new AuditLogger())->record('piece.people_detected', 'piece', $id, ['source_file_id' => $file['id'], 'detection_id' => $detectionId, 'model' => $model]);
        $db->transComplete();

        return redirect()->to('/pecas/' . $id)->with($db->transStatus() ? 'success' : 'error', $db->transStatus()
            ? 'Detecção de pessoas concluída. Revise a estimativa e as descrições.' : 'Não foi possível salvar a detecção de pessoas.');
    }

    public function peopleNames(string $id)
    {
        $file = $this->peopleImageForUpdate($id);
        if (! is_array($file)) {
            return $file;
        }
        $detection = $this->latestPeopleDetection($id, $file['id']);
        if (! $detection || (string) $this->request->getPost('detection_id') !== $detection['id']) {
            return redirect()->to('/pecas/' . $id)->with('error', 'A análise de pessoas mudou. Atualize a página antes de associar nomes.');
        }
        $result = json_decode($detection['result_json'], true, 16, JSON_THROW_ON_ERROR);
        $submitted = $this->request->getPost('names');
        if (! is_array($submitted) || count($submitted) !== count($result['people'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Revise os nomes associados às pessoas desta análise.');
        }
        $names = [];
        foreach ($result['people'] as $index => $person) {
            $name = $submitted[$index] ?? null;
            if (! is_string($name) || mb_strlen(trim($name)) > 190 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                return redirect()->to('/pecas/' . $id)->with('error', 'Informe nomes de até 190 caracteres, sem quebras de linha.');
            }
            $names[$index] = trim($name);
        }
        $db = db_connect();
        $db->transStart();
        $db->table('piece_people_detections')->where('id', $detection['id'])->update([
            'manual_names' => json_encode($names, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'names_updated_by' => session('user_id'), 'names_updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogger())->record('piece.people_names_updated', 'piece', $id, ['detection_id' => $detection['id']]);
        $db->transComplete();

        return redirect()->to('/pecas/' . $id)->with($db->transStatus() ? 'success' : 'error', $db->transStatus()
            ? 'Nomes associados manualmente.' : 'Não foi possível salvar os nomes.');
    }

    private function peopleImageForUpdate(string $id)
    {
        $piece = db_connect()->table('pieces')->select('id, project_id')->where('id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! $this->canManage((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para alterar os dados de pessoas desta imagem.');
        }
        $file = $this->findPreviewFile($id);
        if (! $file || $this->previewType((string) $file['mime_type']) !== 'image') {
            return redirect()->to('/pecas/' . $id)->with('error', 'Esta peça não possui uma imagem disponível para detectar pessoas.');
        }
        if ((string) $this->request->getPost('source_file_id') !== $file['id']) {
            return redirect()->to('/pecas/' . $id)->with('error', 'O arquivo da peça mudou. Atualize a página e tente novamente.');
        }

        return $file;
    }

    private function latestPeopleDetection(string $pieceId, string $fileId): ?array
    {
        return db_connect()->table('piece_people_detections')->where('piece_id', $pieceId)->where('source_file_id', $fileId)
            ->orderBy('generated_at', 'DESC')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    public function preview(string $id)
    {
        $file = $this->findPreviewFile($id);
        if (! $file || $this->previewType((string) $file['mime_type']) === 'file') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $root = realpath(WRITEPATH . 'repository');
        $path = realpath(WRITEPATH . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $file['storage_key']), DIRECTORY_SEPARATOR));
        if ($root === false || $path === false || ! is_file($path) || ! str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return new RangeFileResponse(
            $path,
            (string) $file['mime_type'],
            (string) (db_connect()->table('pieces')->select('original_filename')->where('id', $id)->get()->getRowArray()['original_filename'] ?: $file['original_name']),
            $this->request->getHeaderLine('Range'),
        );
    }

    public function thumbnail(string $id)
    {
        $piece = db_connect()->table('pieces')->select('title, type')->where('id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $generatedThumbnail = (new PieceThumbnail())->findOrCreate($id);
        if ($generatedThumbnail !== null) {
            return $this->response
                ->setHeader('Content-Type', 'image/jpeg')
                ->setHeader('Cache-Control', 'private, max-age=86400')
                ->setBody((string) file_get_contents($generatedThumbnail));
        }

        $type = mb_strtoupper((string) $piece['type']);
        $initial = mb_strtoupper(mb_substr(trim((string) $piece['title']), 0, 1)) ?: 'M';
        $safeType = htmlspecialchars($type, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $safeInitial = htmlspecialchars($initial, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360" role="img" aria-label="Miniatura da peça">
  <rect width="640" height="360" fill="#e7f1fa"/>
  <path d="M0 286L175 125l112 101 96-77 257 211H0z" fill="#b8d7ef"/>
  <circle cx="520" cy="76" r="34" fill="#f0b429"/>
  <rect x="36" y="32" width="568" height="296" rx="18" fill="none" stroke="#075ea8" stroke-width="6"/>
  <text x="320" y="190" text-anchor="middle" font-family="Arial, sans-serif" font-size="94" font-weight="700" fill="#064b84">{$safeInitial}</text>
  <text x="320" y="274" text-anchor="middle" font-family="Arial, sans-serif" font-size="28" font-weight="700" fill="#292927">{$safeType}</text>
</svg>
SVG;

        return $this->response
            ->setHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody($svg);
    }

    private function findPreviewFile(string $pieceId): ?array
    {
        return db_connect()->table('repository_files AS files')
            ->select('files.id, files.original_name, files.storage_key, files.mime_type, files.size_bytes, versions.version_number')
            ->join('asset_versions AS versions', 'versions.id = files.version_id')
            ->join('assets AS piece_assets', 'piece_assets.id = versions.asset_id')
            ->where('piece_assets.piece_id', $pieceId)
            ->orderBy('versions.version_number', 'DESC')
            ->orderBy('files.created_at', 'DESC')
            ->get()
            ->getRowArray();
    }

    private function previewType(string $mimeType): string
    {
        return match (true) {
            in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) => 'image',
            in_array($mimeType, ['video/mp4', 'video/webm'], true) => 'video',
            in_array($mimeType, ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg'], true) => 'audio',
            $mimeType === 'application/pdf' => 'pdf',
            default => 'file',
        };
    }

    private function resolveCategory(string $selectedId, string $newName, string $projectId): ?string
    {
        $db = db_connect();
        if ($newName !== '') {
            $baseCode = mb_substr(url_title($newName, '-', true), 0, 70) ?: 'categoria';
            $code = $baseCode;
            $suffix = 2;
            while ($db->table('vocabularies')->where('code', $code)->countAllResults() > 0) {
                $code = mb_substr($baseCode, 0, 65) . '-' . $suffix++;
            }
            $categoryId = UuidModel::generateUuid();
            $now = date('Y-m-d H:i:s');
            $db->table('vocabularies')->insert(['id' => $categoryId, 'project_id' => $projectId, 'code' => $code, 'name' => $newName, 'description' => 'Categoria criada na edição de palavras-chave.', 'created_at' => $now, 'updated_at' => $now]);

            return $categoryId;
        }

        if (preg_match('/^[0-9a-f-]{36}$/i', $selectedId) !== 1) {
            return null;
        }
        $category = $db->table('vocabularies')->select('id')->where('id', $selectedId)->where('project_id', $projectId)->get()->getRowArray();

        return $category['id'] ?? null;
    }

    private function resolveKeyword(string $categoryId, string $label): string
    {
        $db = db_connect();
        $baseCode = mb_substr(url_title($label, '-', true), 0, 70) ?: 'palavra-chave';
        $term = $db->table('terms')->select('id')->where('vocabulary_id', $categoryId)->where('code', $baseCode)->get()->getRowArray();
        if ($term) {
            return (string) $term['id'];
        }

        $termId = UuidModel::generateUuid();
        $now = date('Y-m-d H:i:s');
        $db->table('terms')->insert(['id' => $termId, 'vocabulary_id' => $categoryId, 'code' => $baseCode, 'label' => $label, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        return $termId;
    }

    private function defaultKeywordCategory(string $projectId): string
    {
        $db = db_connect();
        $category = $db->table('vocabularies')->select('id')->where('project_id', $projectId)->where('name', 'Palavras-chave')->get()->getRowArray();
        if ($category) {
            return (string) $category['id'];
        }

        $categoryId = UuidModel::generateUuid();
        $now = date('Y-m-d H:i:s');
        $baseCode = 'keywords-' . substr(str_replace('-', '', $projectId), 0, 8);
        $code = $baseCode;
        $suffix = 2;
        while ($db->table('vocabularies')->where('code', $code)->countAllResults() > 0) {
            $code = $baseCode . '-' . $suffix++;
        }
        $db->table('vocabularies')->insert(['id' => $categoryId, 'project_id' => $projectId, 'code' => $code, 'name' => 'Palavras-chave', 'description' => 'Palavras-chave da comunidade.', 'created_at' => $now, 'updated_at' => $now]);

        return $categoryId;
    }

    private function canManage(string $projectId): bool
    {
        return (new ProjectAccess())->allows((string) session('user_id'), $projectId, 'pieces.update');
    }

    private function canManageCategories(string $projectId): bool
    {
        return (new ProjectAccess())->allows((string) session('user_id'), $projectId, 'vocabularies.manage');
    }
}
