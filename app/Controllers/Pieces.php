<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Libraries\PieceThumbnail;
use App\Libraries\ProjectAccess;
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
            $collection = db_connect()->table('collections')->where('id', $collectionId)->get()->getRowArray();
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
        $requestedCollection = $requestedCollectionId !== '' ? $db->table('collections')->where('id', $requestedCollectionId)->get()->getRowArray() : null;
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
        return view('pieces/form', ['title' => 'Nova peça', 'projects' => $projects, 'collections' => $db->table('collections')->orderBy('name')->get()->getResultArray(), 'selectedProjectId' => $requestedCollection['project_id'] ?? (string) $this->request->getGet('project'), 'selectedCollectionId' => $requestedCollectionId]);
    }

    public function show(string $id): string
    {
        $db = db_connect();
        $piece = $db->table('pieces')->select('pieces.*, projects.name AS project_name')->join('projects', 'projects.id = pieces.project_id')->where('pieces.id', $id)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $assets = $db->table('assets AS piece_assets')->select('piece_assets.*, COUNT(versions.version_number) AS version_count, MAX(versions.version_number) AS latest_version')->join('asset_versions AS versions', 'versions.asset_id = piece_assets.id', 'left')->where('piece_assets.piece_id', $id)->groupBy('piece_assets.id')->orderBy('piece_assets.created_at', 'DESC')->get()->getResultArray();
        $previewFile = $this->findPreviewFile($id);
        $vocabularies = $db->table('vocabularies')->orderBy('name')->get()->getResultArray();
        $terms = $db->table('terms AS keyword_terms')
            ->select('keyword_terms.id, keyword_terms.vocabulary_id, keyword_terms.label, vocabularies.name AS vocabulary_name')
            ->join('vocabularies', 'vocabularies.id = keyword_terms.vocabulary_id')
            ->where('keyword_terms.is_active', true)
            ->orderBy('vocabularies.name')
            ->orderBy('keyword_terms.label')
            ->get()
            ->getResultArray();
        $selectedTermIds = array_column($db->table('piece_terms')->select('term_id')->where('piece_id', $id)->get()->getResultArray(), 'term_id');

        return view('pieces/show', [
            'title' => $piece['title'],
            'piece' => $piece,
            'assets' => $assets,
            'previewFile' => $previewFile,
            'previewType' => $this->previewType((string) ($previewFile['mime_type'] ?? '')),
            'vocabularies' => $vocabularies,
            'terms' => $terms,
            'selectedTermIds' => $selectedTermIds,
            'canManageKeywords' => $this->canManage((string) $piece['project_id']),
            'canManageCategories' => $this->canManageCategories((string) $piece['project_id']),
        ]);
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
        $validIds = $selectedIds === [] ? [] : array_column($db->table('terms')->select('id')->where('is_active', true)->whereIn('id', $selectedIds)->get()->getResultArray(), 'id');
        $categoryName = trim((string) $this->request->getPost('new_category'));
        $keywordLabel = trim((string) $this->request->getPost('new_keyword'));
        if (mb_strlen($categoryName) > 190 || mb_strlen($keywordLabel) > 190) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Categoria e palavra-chave devem possuir no máximo 190 caracteres.');
        }
        if (($categoryName !== '' || $keywordLabel !== '') && ! $this->canManageCategories((string) $piece['project_id'])) {
            return redirect()->to('/pecas/' . $id)->with('error', 'Você não tem permissão para criar palavras-chave ou categorias.');
        }

        $db->transStart();
        $categoryId = $this->resolveCategory((string) $this->request->getPost('vocabulary_id'), $categoryName);
        if ($keywordLabel !== '') {
            $categoryId ??= $this->defaultKeywordCategory();
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

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $filename = str_replace(['"', "\r", "\n"], '', basename((string) $file['original_name']));

        return $this->response
            ->setHeader('Content-Type', (string) $file['mime_type'])
            ->setHeader('Content-Length', (string) strlen($contents))
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($contents);
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
            ->select('files.id, files.original_name, files.storage_key, files.mime_type, files.size_bytes')
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

    private function resolveCategory(string $selectedId, string $newName): ?string
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
            $db->table('vocabularies')->insert(['id' => $categoryId, 'code' => $code, 'name' => $newName, 'description' => 'Categoria criada na edição de palavras-chave.', 'created_at' => $now, 'updated_at' => $now]);

            return $categoryId;
        }

        if (preg_match('/^[0-9a-f-]{36}$/i', $selectedId) !== 1) {
            return null;
        }
        $category = $db->table('vocabularies')->select('id')->where('id', $selectedId)->get()->getRowArray();

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

    private function defaultKeywordCategory(): string
    {
        $db = db_connect();
        $category = $db->table('vocabularies')->select('id')->where('code', 'keywords')->get()->getRowArray();
        if ($category) {
            return (string) $category['id'];
        }

        $categoryId = UuidModel::generateUuid();
        $now = date('Y-m-d H:i:s');
        $db->table('vocabularies')->insert(['id' => $categoryId, 'code' => 'keywords', 'name' => 'Palavras-chave', 'description' => 'Palavras-chave gerais.', 'created_at' => $now, 'updated_at' => $now]);

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
