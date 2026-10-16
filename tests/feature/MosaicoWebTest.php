<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\ProjectModel;
use App\Models\CollectionModel;
use App\Models\PieceModel;
use App\Models\TermModel;
use App\Models\VocabularyModel;

/** @internal */
final class MosaicoWebTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        db_connect()->table('users')->insert([
            'id'            => '00000000-0000-4000-8000-000000000001',
            'email'         => 'pessoa-teste@example.test',
            'display_name'  => 'Pessoa teste',
            'password_hash' => password_hash('senha-de-teste', PASSWORD_DEFAULT),
            'is_active'     => true,
        ]);
    }

    public function testLoginPageIsPublic(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Bem-vindo ao MOSAICO');
    }

    public function testExpiredSessionIsDestroyedBeforeProtectedRequest(): void
    {
        $response = $this->withSession([
            'user_id' => '00000000-0000-4000-8000-000000000099',
            'user_name' => 'Pessoa removida',
        ])->get('/painel');

        $response->assertRedirectTo('/login');
        $this->assertNull(session('user_id'));
    }

    public function testLogoutWithExpiredSessionDoesNotWriteAuditEventForMissingUser(): void
    {
        $response = $this->withSession([
            'user_id' => '00000000-0000-4000-8000-000000000099',
            'user_name' => 'Pessoa removida',
        ])->post('/logout');

        $response->assertRedirectTo('/login');
        $this->assertSame(0, db_connect()->table('audit_events')->countAllResults());
    }

    public function testDashboardRedirectsAnonymousVisitorToLogin(): void
    {
        $this->get('/painel')->assertRedirectTo('/login');
    }

    public function testExpiredSessionOnProtectedPostRedirectsToLoginBeforeCsrfValidation(): void
    {
        $response = $this->post('/colecoes/b73be2c7-4b0c-47ec-abbc-a4affd7c0fbc/arquivos', []);

        $response->assertRedirectTo('/login');
    }

    public function testOversizedUploadIsRejectedBeforeCsrfWithFriendlyMessage(): void
    {
        $limit = 9 * 1024 * 1024;
        $response = $this->withHeaders([
            'Content-Type' => 'multipart/form-data; boundary=test',
            'Content-Length' => (string) $limit,
            'Referer' => 'http://example.com/index.php/colecoes/teste',
        ])->withSession([
            'user_id' => '00000000-0000-4000-8000-000000000001',
            'user_name' => 'Pessoa teste',
        ])->post('/colecoes/b73be2c7-4b0c-47ec-abbc-a4affd7c0fbc/arquivos', []);

        $response->assertRedirect();
        $this->assertStringContainsString('excede o limite total', (string) session('error'));
    }

    public function testInvalidCsrfRedirectsBackWithoutTechnicalException(): void
    {
        $response = $this->withHeaders(['Referer' => 'http://example.com/index.php/painel'])
            ->withSession([
                'user_id' => '00000000-0000-4000-8000-000000000001',
                'user_name' => 'Pessoa teste',
            ])->post('/logout', []);

        $response->assertRedirect();
        $this->assertSame('O formulário expirou. Atualize a página e tente novamente.', session('error'));
    }

    public function testAuthenticatedUserCanSeeDashboard(): void
    {
        $response = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/painel');
        $response->assertStatus(200);
        $response->assertSee('Painel do repositório');
        $response->assertSee('Status:');
        $response->assertSee('Sistema disponível.');
        $response->assertSee('class="nav-disabled"');
        $response->assertSee('aria-disabled="true"');
        $response->assertSee('Selecione uma comunidade para acessá-la');
        $response->assertSee('Selecione uma coleção para acessá-la');
    }

    public function testRepositoryCoreTablesExist(): void
    {
        foreach (['assets', 'asset_versions', 'repository_files', 'audit_events'] as $table) {
            $this->assertTrue(db_connect()->tableExists($table), "Tabela {$table} não foi criada.");
        }
        $pieceFields = db_connect()->getFieldNames('pieces');
        $this->assertContains('original_directory', $pieceFields);
        $this->assertContains('original_filename', $pieceFields);
        $this->assertContains('access_condition', $pieceFields);
    }

    public function testCommunityListOffersCommunitySelection(): void
    {
        $model = new ProjectModel();
        $model->insert(['code' => 'COM-1', 'name' => 'Comunidade de teste']);
        $community = $model->first();

        $response = $this->withSession([
            'user_id' => '00000000-0000-4000-8000-000000000001',
            'user_name' => 'Pessoa teste',
        ])->get('/comunidades');

        $response->assertStatus(200);
        $response->assertSee('Selecionar Comunidade');
        $response->assertSee('Editar dados');
        $response->assertSee('Permissões');
        $response->assertSee('Visibilidade');
        $response->assertSee('Excluir');
        $response->assertSee('/comunidades/' . $community['id'] . '/selecionar');
    }

    public function testCommunityDetailsOffersManagementActions(): void
    {
        $model = new ProjectModel();
        $model->insert(['code' => 'COM-2', 'name' => 'Comunidade gerenciável']);
        $community = $model->first();
        $collectionModel = new CollectionModel();
        $collectionModel->insert([
            'project_id' => $community['id'],
            'code' => 'COM-2-CODIGO-INTERNO',
            'name' => 'Coleção em destaque',
        ]);
        $model->insert(['code' => 'COM-2B', 'name' => 'Outra comunidade disponível']);
        $otherCommunity = $model->where('code', 'COM-2B')->first();

        $response = $this->withSession([
            'user_id' => '00000000-0000-4000-8000-000000000001',
            'user_name' => 'Pessoa teste',
        ])->get('/comunidades/' . $community['id']);

        $response->assertStatus(200);
        $response->assertSee('Editar dados');
        $response->assertSee('Visibilidade');
        $response->assertSee('Excluir');
        $response->assertSee('/comunidades/' . $community['id'] . '/editar');
        $response->assertSee('Coleções');
        $response->assertSee('Coleção em destaque');
        $response->assertSee('Selecionar Coleção');
        $response->assertDontSee('Outra comunidade disponível');
        $response->assertDontSee('COM-2-CODIGO-INTERNO');
    }

    public function testCollectionPiecesPageOnlyShowsLinkedPieces(): void
    {
        $projectModel = new ProjectModel();
        $projectModel->insert(['code' => 'COM-3', 'name' => 'Comunidade filtrada']);
        $community = $projectModel->first();
        $collectionModel = new CollectionModel();
        $collectionModel->insert(['project_id' => $community['id'], 'code' => 'COM-3-COL', 'name' => 'Coleção filtrada']);
        $collection = $collectionModel->first();
        $pieceModel = new PieceModel();
        $pieceModel->insert(['project_id' => $community['id'], 'code' => 'VINCULADA', 'title' => 'Peça vinculada', 'type' => 'video']);
        $linkedPiece = $pieceModel->first();
        $pieceModel->insert(['project_id' => $community['id'], 'code' => 'FORA', 'title' => 'Peça fora da coleção', 'type' => 'video']);
        $pieceModel->insert(['project_id' => $community['id'], 'code' => 'SEM-TAG', 'title' => 'Peça sem a palavra-chave', 'type' => 'image']);
        $pieceWithoutTerm = $pieceModel->where('code', 'SEM-TAG')->first();
        db_connect()->table('piece_collections')->insert(['piece_id' => $linkedPiece['id'], 'collection_id' => $collection['id']]);
        db_connect()->table('piece_collections')->insert(['piece_id' => $pieceWithoutTerm['id'], 'collection_id' => $collection['id']]);
        $vocabularyModel = new VocabularyModel();
        $vocabularyModel->insert(['code' => 'tags-teste', 'name' => 'Tags de teste']);
        $vocabulary = $vocabularyModel->first();
        $termModel = new TermModel();
        $termModel->insert(['vocabulary_id' => $vocabulary['id'], 'code' => 'saude-digital', 'label' => 'Saúde digital']);
        $term = $termModel->first();
        db_connect()->table('piece_terms')->insert(['piece_id' => $linkedPiece['id'], 'term_id' => $term['id']]);

        $response = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '/pecas');

        $response->assertRedirectTo('/colecoes/' . $collection['id']);

        $collectionResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id']);
        $collectionResponse->assertStatus(200);
        $collectionResponse->assertSee('Coleção filtrada');
        $collectionResponse->assertSee('Comunidade filtrada');
        $collectionResponse->assertSee('Peça vinculada');
        $collectionResponse->assertSee('Peça sem a palavra-chave');
        $collectionResponse->assertSee('Peças (2)');
        $collectionResponse->assertSee('Saúde digital');
        $collectionResponse->assertDontSee('Peça fora da coleção');
        $collectionResponse->assertSee('/pecas/nova?collection=' . $collection['id']);
        $collectionResponse->assertSee('Enviar arquivos');
        $collectionResponse->assertSee('Importar do Google Drive');
        $collectionResponse->assertSee('data-drive-dialog');
        $collectionResponse->assertSee('/colecoes/' . $collection['id'] . '/google-drive');
        $collectionResponse->assertSee('name="drive_url"');
        $collectionResponse->assertSee('name="files[]"');
        $collectionResponse->assertSee('multiple');
        $collectionResponse->assertSee('Arraste os arquivos');
        $collectionResponse->assertSee('data-drop-zone');
        $collectionResponse->assertSee('.mp4,.webm,.avi');
        $collectionResponse->assertSee('.ods,.xml,.doc,.docx,.xls,.xlsx,.ppt,.pptx');
        $collectionResponse->assertSee('Limite de 1 GB por arquivo');
        $collectionResponse->assertSee('Palavras-chave do grupo');
        $collectionResponse->assertSee('name="new_keywords"');
        $collectionResponse->assertSee('/pecas/' . $linkedPiece['id'] . '/miniatura');
        $collectionResponse->assertSee('Lista');
        $collectionResponse->assertSee('Detalhes');
        $collectionResponse->assertSee('Miniatura');
        $collectionResponse->assertSee('Cards');
        $collectionResponse->assertSee('class="thumbnail-grid"');
        $collectionResponse->assertSee('Buscar peças');
        $collectionResponse->assertSee('name="q"');
        $collectionResponse->assertSee('Selecionar todas em Draft');
        $collectionResponse->assertDontSee('Remover todas em Draft');
        $collectionResponse->assertSee('name="bulk_action"');
        $collectionResponse->assertSee('Publicar');
        $collectionResponse->assertSee('Embargar');
        $collectionResponse->assertSee('Restringir acesso');
        $collectionResponse->assertSee('Remover selecionadas');

        $viewPreferenceResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?view=list');
        $viewPreferenceResponse->assertCookie('mosaico_collection_view', 'list');

        $searchResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?q=VINCULADA');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('1 resultado(s)');
        $searchResponse->assertSee('Peça vinculada');
        $searchResponse->assertDontSee('Peça sem a palavra-chave');

        $keywordSearchResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?view=thumbnail&q=' . rawurlencode('Saúde digital'));
        $keywordSearchResponse->assertStatus(200);
        $keywordSearchResponse->assertSee('1 resultado(s)');
        $keywordSearchResponse->assertSee('Peça vinculada');
        $keywordSearchResponse->assertDontSee('Peça sem a palavra-chave');

        $filteredResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?view=details&term=' . $term['id']);
        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSee('Filtro:');
        $filteredResponse->assertSee('Saúde digital');
        $filteredResponse->assertSee('Remover filtro');
        $filteredResponse->assertSee('Peça vinculada');
        $filteredResponse->assertSee('Peças (1)');
        $filteredResponse->assertDontSee('Peça sem a palavra-chave');
        $filteredResponse->assertSee('term=' . $term['id']);

        $thumbnailResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/pecas/' . $linkedPiece['id'] . '/miniatura');
        $thumbnailResponse->assertStatus(200);
        $thumbnailResponse->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $thumbnailResponse->assertSee('<svg');

        $assetId = '10000000-0000-4000-8000-000000000001';
        $versionId = '20000000-0000-4000-8000-000000000001';
        db_connect()->table('assets')->insert(['id' => $assetId, 'piece_id' => $linkedPiece['id'], 'name' => 'Vídeo principal', 'type' => 'video']);
        db_connect()->table('asset_versions')->insert(['id' => $versionId, 'asset_id' => $assetId, 'version_number' => 1, 'change_notes' => 'Versão de teste']);
        db_connect()->table('repository_files')->insert([
            'id' => '30000000-0000-4000-8000-000000000001',
            'version_id' => $versionId,
            'original_name' => 'video-principal.mp4',
            'storage_key' => 'repository/teste/video-principal.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'sha256' => str_repeat('a', 64),
        ]);
        db_connect()->table('pieces')->where('id', $linkedPiece['id'])->update([
            'original_directory' => 'Materiais/Aulas',
            'original_filename' => 'video-principal.mp4',
        ]);
        $cardResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?view=cards');
        $cardResponse->assertSee('class="file-extension">MP4</span>');
        $cardResponse->assertSee('piece-state-draft');
        $thumbnailCollectionResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/colecoes/' . $collection['id'] . '?view=thumbnail');
        $thumbnailCollectionResponse->assertSee('class="file-extension">MP4</span>');
        $managerRole = db_connect()->table('roles')->where('code', 'repository_manager')->get()->getRowArray();
        db_connect()->table('users')->insert([
            'id' => '00000000-0000-4000-8000-000000000001',
            'email' => 'pessoa@example.test',
            'display_name' => 'Pessoa teste',
            'password_hash' => password_hash('senha-de-teste', PASSWORD_DEFAULT),
        ]);
        db_connect()->table('memberships')->insert([
            'project_id' => $community['id'],
            'user_id' => '00000000-0000-4000-8000-000000000001',
            'role_id' => $managerRole['id'],
        ]);
        $pieceResponse = $this->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->get('/pecas/' . $linkedPiece['id']);
        $pieceResponse->assertStatus(200);
        $pieceResponse->assertSee('Pré-visualização');
        $pieceResponse->assertSee('<video controls preload="metadata">');
        $pieceResponse->assertSee('/pecas/' . $linkedPiece['id'] . '/preview');
        $pieceResponse->assertSee('video-principal.mp4');
        $pieceResponse->assertSee('>Voltar</a>');
        $pieceResponse->assertDontSee('Adicionar ativo');
        $pieceResponse->assertSee('/colecoes/' . $collection['id']);
        $pieceResponse->assertSee('Versão do arquivo');
        $pieceResponse->assertSee('Caminho original');
        $pieceResponse->assertSee('Materiais/Aulas/video-principal.mp4');
        $pieceResponse->assertSee('Salvar palavras-chave');
        $pieceResponse->assertSee('Nova categoria');

        $csrfToken = csrf_hash();
        $keywordResponse = $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $csrfToken])
            ->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->post('/pecas/' . $linkedPiece['id'] . '/palavras-chave', [
                'csrf_test_name' => $csrfToken,
                'term_ids' => [$term['id']],
                'new_category' => 'Temas educacionais',
                'new_keyword' => 'Educação aberta',
            ]);
        $keywordResponse->assertRedirectTo('/pecas/' . $linkedPiece['id']);
        $newCategory = db_connect()->table('vocabularies')->where('name', 'Temas educacionais')->get()->getRowArray();
        $this->assertNotNull($newCategory);
        $newTerm = db_connect()->table('terms')->where('vocabulary_id', $newCategory['id'])->where('label', 'Educação aberta')->get()->getRowArray();
        $this->assertNotNull($newTerm);
        $this->assertSame(1, db_connect()->table('piece_terms')->where(['piece_id' => $linkedPiece['id'], 'term_id' => $newTerm['id']])->countAllResults());

        $csrfToken = csrf_hash();
        $removeKeywordsResponse = $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $csrfToken])
            ->withSession(['user_id' => '00000000-0000-4000-8000-000000000001', 'user_name' => 'Pessoa teste'])
            ->post('/pecas/' . $linkedPiece['id'] . '/palavras-chave', ['csrf_test_name' => $csrfToken]);
        $removeKeywordsResponse->assertRedirectTo('/pecas/' . $linkedPiece['id']);
        $this->assertSame(0, db_connect()->table('piece_terms')->where('piece_id', $linkedPiece['id'])->countAllResults());
        $this->assertSame(1, db_connect()->table('terms')->where('id', $newTerm['id'])->countAllResults());
    }
}
