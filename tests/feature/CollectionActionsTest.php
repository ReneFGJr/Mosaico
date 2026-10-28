<?php

use App\Models\CollectionModel;
use App\Models\ProjectModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class CollectionActionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;
    private string $userId = '10000000-0000-4000-8000-000000000001';
    private array $collection;

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => $this->userId, 'email' => 'collections@example.test', 'display_name' => 'Pessoa teste', 'password_hash' => 'unused', 'is_active' => true]);
        $projects = new ProjectModel();
        $projects->insert(['code' => 'ACTION', 'name' => 'Comunidade de ações']);
        $project = $projects->first();
        $collections = new CollectionModel();
        $collections->insert(['project_id' => $project['id'], 'code' => 'COL-ACTION', 'name' => 'Coleção de ações', 'description' => 'Descrição inicial']);
        $this->collection = $collections->first();
    }

    private function grantManager(): void
    {
        $role = db_connect()->table('roles')->where('code', 'administrator')->get()->getRowArray();
        db_connect()->table('memberships')->insert(['project_id' => $this->collection['project_id'], 'user_id' => $this->userId, 'role_id' => $role['id']]);
    }

    private function request(string $action, ?array $data = null)
    {
        $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste']);
        $url = '/colecoes/' . $this->collection['id'] . '/' . $action;
        if ($data === null) {
            return $this->get($url);
        }
        $token = csrf_hash();
        return $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])->post($url, $data + ['csrf_test_name' => $token]);
    }

    public function testMembersCanBeManagedOnlyByCommunityManagers(): void
    {
        $this->request('membros')->assertRedirectTo('/comunidades');
        $this->grantManager();
        $this->request('membros')->assertStatus(200);
        foreach (['view', 'add'] as $privilege) {
            $this->request('membros', ['user_id' => $this->userId, 'privilege' => $privilege])->assertRedirect();
            $row = db_connect()->table('collection_members')->where('collection_id', $this->collection['id'])->get()->getRowArray();
            $this->assertSame($privilege, $row['privilege']);
        }
        $this->request('membros', ['user_id' => $this->userId, 'privilege' => 'publish'])->assertRedirect();
        $this->assertSame('add', db_connect()->table('collection_members')->get()->getRowArray()['privilege']);
        $this->request('membros', ['user_id' => $this->userId, 'privilege' => 'remove'])->assertRedirect();
        $this->assertSame(0, db_connect()->table('collection_members')->countAllResults());
    }

    public function testCollectionMembersCannotPublishOrManageMembers(): void
    {
        foreach (['view', 'add'] as $privilege) {
            db_connect()->table('collection_members')->where('user_id', $this->userId)->delete();
            db_connect()->table('collection_members')->insert(['collection_id' => $this->collection['id'], 'user_id' => $this->userId, 'privilege' => $privilege]);
            $this->request('pecas/acoes', ['bulk_action' => 'publish'])->assertRedirect();
            $this->assertStringContainsString('permissão', (string) session('error'));
            $this->request('membros', ['user_id' => $this->userId, 'privilege' => 'add'])->assertRedirectTo('/comunidades');
        }
    }

    public function testCommunityImageRequiresManagerAndValidUpload(): void
    {
        $url = '/comunidades/' . $this->collection['project_id'] . '/trocar-imagem';
        $this->withSession(['user_id' => $this->userId])->get($url)->assertRedirectTo('/comunidades');
        $this->grantManager();
        $response = $this->withSession(['user_id' => $this->userId])->get($url);
        $response->assertStatus(200);
        $response->assertSee('enctype="multipart/form-data"');
        $token = csrf_hash();
        $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])
            ->withSession(['user_id' => $this->userId])
            ->post($url, ['csrf_test_name' => $token])->assertRedirectTo($url);
        $this->assertNull(db_connect()->table('projects')->where('id', $this->collection['project_id'])->get()->getRowArray()['cover_image']);
        $this->withSession(['user_id' => $this->userId])->get('/comunidades')->assertSee($url);
    }

    public function testCardsOfferCollectionActionsAndForms(): void
    {
        $this->grantManager();
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/comunidades/' . $this->collection['project_id']);
        $response->assertStatus(200);
        foreach (['editar', 'trocar-imagem', 'deletar'] as $action) {
            $response->assertSee('/colecoes/' . $this->collection['id'] . '/' . $action);
            $this->request($action)->assertStatus(200);
        }
        $this->request('trocar-imagem')->assertSee('enctype="multipart/form-data"');
        $this->request('deletar')->assertSee('Confirmar exclusão');
        $this->assertNull(db_connect()->table('collections')->where('id', $this->collection['id'])->get()->getRowArray()['deleted_at']);
    }

    public function testEditPreservesCollectionIdentity(): void
    {
        $this->grantManager();
        $this->request('editar', ['name' => 'Nome atualizado', 'description' => 'Texto atualizado'])->assertRedirectTo('/comunidades/' . $this->collection['project_id']);
        $saved = (new CollectionModel())->find($this->collection['id']);
        $this->assertSame('Nome atualizado', $saved['name']);
        $this->assertSame('Texto atualizado', $saved['description']);
        $this->assertSame($this->collection['code'], $saved['code']);
        $this->assertSame($this->collection['project_id'], $saved['project_id']);
    }

    public function testDeleteRequiresConfirmationAndPreservesData(): void
    {
        $this->grantManager();
        $pieceId = '20000000-0000-4000-8000-000000000001';
        db_connect()->table('pieces')->insert(['id' => $pieceId, 'project_id' => $this->collection['project_id'], 'code' => 'KEEP', 'title' => 'Peça preservada', 'type' => 'image']);
        db_connect()->table('piece_collections')->insert(['piece_id' => $pieceId, 'collection_id' => $this->collection['id']]);
        db_connect()->table('assets')->insert(['id' => '30000000-0000-4000-8000-000000000001', 'piece_id' => $pieceId, 'name' => 'Imagem preservada', 'type' => 'image']);
        db_connect()->table('asset_versions')->insert(['id' => '40000000-0000-4000-8000-000000000001', 'asset_id' => '30000000-0000-4000-8000-000000000001', 'version_number' => 1, 'change_notes' => 'Original']);
        db_connect()->table('repository_files')->insert(['id' => '50000000-0000-4000-8000-000000000001', 'version_id' => '40000000-0000-4000-8000-000000000001', 'original_name' => 'imagem.png', 'storage_key' => 'repository/preserved/image.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'sha256' => str_repeat('a', 64)]);
        $this->request('deletar', [])->assertRedirect();
        $this->assertNotNull((new CollectionModel())->find($this->collection['id']));
        $this->request('deletar', ['confirmed' => '1'])->assertRedirectTo('/comunidades/' . $this->collection['project_id']);
        $this->assertNull((new CollectionModel())->find($this->collection['id']));
        $row = db_connect()->table('collections')->where('id', $this->collection['id'])->get()->getRowArray();
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame(1, db_connect()->table('pieces')->where('id', $pieceId)->countAllResults());
        $this->assertSame(1, db_connect()->table('piece_collections')->where('collection_id', $this->collection['id'])->countAllResults());
        foreach (['assets', 'asset_versions', 'repository_files'] as $table) {
            $this->assertSame(1, db_connect()->table($table)->countAllResults());
        }
        $this->assertSame(1, db_connect()->table('audit_events')->where('action', 'collection.hidden')->countAllResults());
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/comunidades/' . $this->collection['project_id']);
        $response->assertDontSee($this->collection['name']);
        foreach (['editar', 'imagem', 'trocar-imagem', 'deletar'] as $action) {
            try {
                $this->request($action);
                $this->fail('Uma coleção oculta não deve estar acessível.');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $exception) {
                $this->assertSame(404, $exception->getCode());
            }
        }
    }

    public function testActionsRequirePermissionAndDoNotAcceptMissingImage(): void
    {
        foreach (['editar', 'trocar-imagem', 'deletar'] as $action) {
            $this->request($action, ['confirmed' => '1', 'name' => 'Indevido'])->assertRedirectTo('/comunidades');
        }
        $this->assertSame($this->collection['name'], (new CollectionModel())->find($this->collection['id'])['name']);
        $this->grantManager();
        $this->request('trocar-imagem', [])->assertRedirect();
        $this->assertStringContainsString('imagem válida', session('error'));
        $this->assertNull((new CollectionModel())->find($this->collection['id'])['cover_image']);
    }

    public function testDeleteCannotBypassCsrfWithConfirmation(): void
    {
        $this->grantManager();
        $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
            ->withHeaders(['Referer' => 'http://example.com/index.php/colecoes/' . $this->collection['id'] . '/deletar'])
            ->post('/colecoes/' . $this->collection['id'] . '/deletar', ['confirmed' => '1'])->assertRedirect();
        $this->assertNotNull((new CollectionModel())->find($this->collection['id']));
        $this->assertStringContainsString('formulário expirou', session('error'));
    }

    public function testRenamedIdenticalPhotoExplainsWhichExistingFileWasRestored(): void
    {
        $this->grantManager();
        session()->set(['user_id' => $this->userId, 'user_name' => 'Pessoa teste']);
        $temporary = tempnam(sys_get_temp_dir(), 'mosaico-duplicate-');
        try {
            $image = imagecreatetruecolor(16, 16);
            imagejpeg($image, $temporary);
            imagedestroy($image);
            $pieceId = '20000000-0000-4000-8000-000000000001';
            $db = db_connect();
            $db->table('pieces')->insert(['id' => $pieceId, 'project_id' => $this->collection['project_id'], 'code' => 'PHOTO', 'title' => '001A', 'original_filename' => '001A.jpg', 'type' => 'imagem', 'status' => 'deleted']);
            $db->table('assets')->insert(['id' => '30000000-0000-4000-8000-000000000001', 'piece_id' => $pieceId, 'name' => '001A', 'type' => 'imagem']);
            $db->table('asset_versions')->insert(['id' => '40000000-0000-4000-8000-000000000001', 'asset_id' => '30000000-0000-4000-8000-000000000001', 'version_number' => 1, 'change_notes' => 'Original']);
            $db->table('repository_files')->insert(['id' => '50000000-0000-4000-8000-000000000001', 'version_id' => '40000000-0000-4000-8000-000000000001', 'original_name' => '001A.jpg', 'storage_key' => 'repository/test/001A.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => filesize($temporary), 'sha256' => hash_file('sha256', $temporary)]);
            $vocabulary = '60000000-0000-4000-8000-000000000001';
            $oldTerm = '70000000-0000-4000-8000-000000000001';
            $newTerm = '70000000-0000-4000-8000-000000000002';
            $db->table('vocabularies')->insert(['id' => $vocabulary, 'project_id' => $this->collection['project_id'], 'code' => 'UPLOAD', 'name' => 'Assuntos']);
            foreach ([$oldTerm => 'Anterior', $newTerm => 'Adicional'] as $termId => $label) {
                $db->table('terms')->insert(['id' => $termId, 'vocabulary_id' => $vocabulary, 'code' => $termId, 'label' => $label, 'is_active' => true]);
            }
            $db->table('piece_terms')->insert(['piece_id' => $pieceId, 'term_id' => $oldTerm]);
            $file = $this->createMock(\CodeIgniter\HTTP\Files\UploadedFile::class);
            $file->method('getClientName')->willReturn('001D.jpg');
            $file->method('getClientExtension')->willReturn('jpg');
            $file->method('isValid')->willReturn(true);
            $file->method('hasMoved')->willReturn(false);
            $file->method('getSize')->willReturn(filesize($temporary));
            $file->method('getMimeType')->willReturn('image/jpeg');
            $file->method('getTempName')->willReturn($temporary);
            $file->expects($this->never())->method('move');
            $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
            $request->method('getFileMultiple')->willReturn([$file]);
            $request->method('getPost')->willReturnCallback(static fn ($key) => $key === 'term_ids' ? [$oldTerm, $newTerm] : 'Nova palavra');
            $controller = new \App\Controllers\Collections();
            $controller->initController($request, service('response'), service('logger'));
            $response = $controller->upload($this->collection['id']);
            $this->assertStringContainsString('/colecoes/' . $this->collection['id'], $response->getHeaderLine('Location'));
            $this->assertSame(1, session('upload_result')['restored']);
            $this->assertSame(['submitted' => '001D.jpg', 'existing' => '001A.jpg'], session('upload_result')['duplicates'][0]);
            $this->assertSame(1, $db->table('repository_files')->countAllResults());
            $this->assertSame(1, $db->table('pieces')->countAllResults());
            $this->assertSame(1, $db->table('piece_collections')->where('piece_id', $pieceId)->where('collection_id', $this->collection['id'])->countAllResults());
            $saved = $db->table('pieces')->where('id', $pieceId)->get()->getRowArray();
            $this->assertSame('draft', $saved['status']);
            $this->assertSame('001A.jpg', $saved['original_filename']);
            $this->assertSame(3, $db->table('piece_terms')->where('piece_id', $pieceId)->countAllResults());
            foreach ([$oldTerm, $newTerm] as $termId) {
                $this->assertSame(1, $db->table('piece_terms')->where(['piece_id' => $pieceId, 'term_id' => $termId])->countAllResults());
            }
            $typedTerm = $db->table('terms')->where('label', 'Nova palavra')->get()->getRowArray();
            $this->assertNotNull($typedTerm);
            $this->assertSame(1, $db->table('piece_terms')->where(['piece_id' => $pieceId, 'term_id' => $typedTerm['id']])->countAllResults());
            // Simulate hiding the piece by removing it from this collection.
            $db->table('piece_collections')->where(['piece_id' => $pieceId, 'collection_id' => $this->collection['id']])->delete();
            $this->assertSame(0, $db->table('piece_collections')->where('piece_id', $pieceId)->countAllResults());
            $controller->upload($this->collection['id']);
            $this->assertSame(1, $db->table('piece_collections')->where(['piece_id' => $pieceId, 'collection_id' => $this->collection['id']])->countAllResults());
            $uploadResult = session('upload_result');
            $visiblePage = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/colecoes/' . $this->collection['id']);
            $visiblePage->assertStatus(200);
            $visiblePage->assertSee('001A.jpg');
            $this->assertSame(1, $db->table('pieces')->countAllResults());
            $this->assertSame(1, $db->table('repository_files')->countAllResults());
            $this->assertSame(3, $db->table('piece_terms')->where('piece_id', $pieceId)->countAllResults());

            $page = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste', 'upload_result' => $uploadResult])->get('/comunidades/' . $this->collection['project_id']);
            $page->assertSee('data-upload-result');
            $page->assertSee('upload-result-body');
            $page->assertSee('Fechar');
            $page->assertSee('1 arquivo já existe na comunidade');
            $page->assertSee('001D.jpg');
            $page->assertSee('Mesmo conteúdo de 001A.jpg.');
            $page->assertSee('Palavras-chave aplicadas à peça original.');
        } finally {
            unlink($temporary);
        }
    }

    public function testSidebarKeywordsCombineFiltersAndPreserveViewAndSearch(): void
    {
        $db = db_connect();
        $vocabulary = '60000000-0000-4000-8000-000000000001';
        $first = '70000000-0000-4000-8000-000000000001';
        $second = '70000000-0000-4000-8000-000000000002';
        $db->table('vocabularies')->insert(['id' => $vocabulary, 'project_id' => $this->collection['project_id'], 'code' => 'FILTER', 'name' => 'Assuntos']);
        foreach ([$first => 'Saúde', $second => 'Educação'] as $id => $label) {
            $db->table('terms')->insert(['id' => $id, 'vocabulary_id' => $vocabulary, 'code' => $id, 'label' => $label, 'is_active' => true]);
        }
        foreach (['Ambas as tags' => [$first, $second], 'Somente saúde' => [$first], 'Somente educação' => [$second]] as $title => $termIds) {
            $pieceId = \App\Models\UuidModel::generateUuid();
            $db->table('pieces')->insert(['id' => $pieceId, 'project_id' => $this->collection['project_id'], 'code' => $pieceId, 'title' => $title, 'type' => 'image']);
            $db->table('piece_collections')->insert(['piece_id' => $pieceId, 'collection_id' => $this->collection['id']]);
            foreach ($termIds as $termId) {
                $db->table('piece_terms')->insert(['piece_id' => $pieceId, 'term_id' => $termId]);
            }
        }
        $params = http_build_query(['keyword_filter' => '1', 'terms' => [$first, $second], 'view' => 'list']);
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/colecoes/' . $this->collection['id'] . '?' . $params);
        $response->assertStatus(200);
        $response->assertSee('sidebar-keywords');
        $response->assertSee('name="terms[]"');
        $response->assertSee('Assuntos');
        $response->assertSee('Ambas as tags');
        $response->assertDontSee('Somente saúde');
        $response->assertDontSee('Somente educação');
        $response->assertSee('name="view" value="list"');
        $response->assertSee('name="terms[]" value="' . $first . '"');
        $saved = session('keyword_filters');
        $response = $this->withSession(['user_id' => $this->userId, 'keyword_filters' => $saved])->get('/colecoes/' . $this->collection['id']);
        $response->assertSee('Ambas as tags');
        $response->assertDontSee('Somente saúde');
        $response = $this->withSession(['user_id' => $this->userId, 'keyword_filters' => $saved])->get('/colecoes/' . $this->collection['id'] . '?keyword_filter=1');
        $response->assertSee('Somente saúde');
        $response->assertSee('Somente educação');
        $response = $this->withSession(['user_id' => $this->userId])->get('/colecoes/' . $this->collection['id'] . '?term=' . $first);
        $response->assertSee('Somente saúde');
        $response->assertDontSee('Somente educação');
        $otherProjects = new ProjectModel();
        $otherProjects->insert(['code' => 'OTHER-FILTER', 'name' => 'Outra comunidade']);
        $otherProject = $otherProjects->where('code', 'OTHER-FILTER')->first();
        $otherVocabulary = '60000000-0000-4000-8000-000000000002';
        $otherTerm = '70000000-0000-4000-8000-000000000003';
        $db->table('vocabularies')->insert(['id' => $otherVocabulary, 'project_id' => $otherProject['id'], 'code' => 'OTHER-TAGS', 'name' => 'Outra categoria']);
        $db->table('terms')->insert(['id' => $otherTerm, 'vocabulary_id' => $otherVocabulary, 'code' => 'FOREIGN', 'label' => 'Termo de outra comunidade', 'is_active' => true]);
        $response = $this->withSession(['user_id' => $this->userId])->get('/colecoes/' . $this->collection['id'] . '?' . http_build_query(['keyword_filter' => '1', 'terms' => [$first, $otherTerm]]));
        $response->assertSee('Somente saúde');
        $response->assertDontSee('Somente educação');
        $response->assertDontSee('Termo de outra comunidade');
        foreach (['thumbnail' => 'thumbnail-grid', 'cards' => 'card-grid', 'list' => 'piece-list', 'details' => 'table-wrap'] as $mode => $class) {
            $catalogParams = http_build_query(['keyword_filter' => '1', 'project' => $this->collection['project_id'], 'terms' => [$first, $second], 'view' => $mode, 'q' => 'Ambas']);
            $response = $this->withSession(['user_id' => $this->userId])->get('/pecas?' . $catalogParams);
            $response->assertStatus(200);
            $response->assertSee('class="' . $class);
            $response->assertSee('Ambas as tags');
            $response->assertDontSee('Somente saúde');
            $response->assertDontSee('Somente educação');
            $response->assertSee('name="project" value="' . $this->collection['project_id'] . '"');
            $response->assertSee('name="terms[]" value="' . $first . '"');
            $response->assertSee('Modo de visualização');
            $response->assertDontSee('data-bulk-actions');
        }
    }
}
