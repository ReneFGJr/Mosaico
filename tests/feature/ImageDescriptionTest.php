<?php

use App\Models\PieceModel;
use App\Models\ProjectModel;
use App\Libraries\OllamaImageDescriber;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/** @internal */
final class ImageDescriptionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;
    private string $userId = '10000000-0000-4000-8000-000000000001';
    private string $fileId = '40000000-0000-4000-8000-000000000001';
    private array $piece;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();
        $db->table('users')->insert(['id' => $this->userId, 'email' => 'image-test@example.test', 'display_name' => 'Pessoa teste', 'password_hash' => 'unused', 'is_active' => true]);
        $projects = new ProjectModel();
        $projects->insert(['code' => 'IMAGE', 'name' => 'Imagens']);
        $project = $projects->first();
        $pieces = new PieceModel();
        $pieces->insert(['project_id' => $project['id'], 'code' => 'IMG-1', 'title' => 'Imagem de teste', 'summary' => 'Resumo original', 'type' => 'image']);
        $this->piece = $pieces->first();
        $db->table('assets')->insert(['id' => '20000000-0000-4000-8000-000000000001', 'piece_id' => $this->piece['id'], 'name' => 'Imagem', 'type' => 'image']);
        $db->table('asset_versions')->insert(['id' => '30000000-0000-4000-8000-000000000001', 'asset_id' => '20000000-0000-4000-8000-000000000001', 'version_number' => 1, 'change_notes' => 'Teste']);
        $db->table('repository_files')->insert(['id' => $this->fileId, 'version_id' => '30000000-0000-4000-8000-000000000001', 'original_name' => 'image.png', 'storage_key' => 'repository/test/image.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'sha256' => str_repeat('a', 64)]);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('ollamaImageDescriber');
        parent::tearDown();
    }

    private function grantEditor(): void
    {
        $role = db_connect()->table('roles')->where('code', 'producer_editor')->get()->getRowArray();
        db_connect()->table('memberships')->insert(['project_id' => $this->piece['project_id'], 'user_id' => $this->userId, 'role_id' => $role['id']]);
    }

    private function submit(array $data = [])
    {
        $token = csrf_hash();
        return $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
            ->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])
            ->post('/pecas/' . $this->piece['id'] . '/descrever-imagem', $data + ['csrf_test_name' => $token, 'source_file_id' => $this->fileId]);
    }

    private function submitPeople(string $action, array $data = [])
    {
        $token = csrf_hash();
        return $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
            ->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])
            ->post('/pecas/' . $this->piece['id'] . '/' . $action, $data + ['csrf_test_name' => $token, 'source_file_id' => $this->fileId]);
    }

    private function seedPeopleDetection(): string
    {
        $detectionId = '50000000-0000-4000-8000-000000000001';
        db_connect()->table('piece_people_detections')->insert([
            'id' => $detectionId, 'piece_id' => $this->piece['id'], 'source_file_id' => $this->fileId,
            'result_json' => json_encode(['count_estimate' => 1, 'notes' => '', 'people' => [['position' => 'À esquerda', 'clothing' => 'Azul', 'activity' => 'Lendo']]]),
            'model' => 'qwen3.5:2b', 'generated_at' => '2000-01-01 00:00:00.000000',
        ]);
        return $detectionId;
    }

    public function testRequiresAuthenticationAndEditPermission(): void
    {
        $this->post('/pecas/' . $this->piece['id'] . '/descrever-imagem')->assertRedirectTo('/login');
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->never())->method('describe');
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submit()->assertRedirectTo('/pecas/' . $this->piece['id']);
        $this->assertStringContainsString('permissão', session('error'));
        $this->assertSame(0, db_connect()->table('piece_image_descriptions')->countAllResults());
    }

    public function testGeneratesPersistsAndEscapesDescriptionWithoutChangingSummary(): void
    {
        $this->grantEditor();
        $client = $this->createMock(OllamaImageDescriber::class);
        $description = 'Um quadro escuro. <script>alert(1)</script>';
        $client->expects($this->once())->method('describe')->willReturn(['description' => $description, 'model' => 'qwen3.5:2b']);
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submit()->assertRedirectTo('/pecas/' . $this->piece['id']);
        $saved = db_connect()->table('piece_image_descriptions')->get()->getRowArray();
        $this->assertSame($this->fileId, $saved['source_file_id']);
        $this->assertSame($description, $saved['description']);
        $this->assertSame('Resumo original', db_connect()->table('pieces')->where('id', $this->piece['id'])->get()->getRowArray()['summary']);
        $this->assertSame(1, db_connect()->table('audit_events')->where('action', 'piece.image_described')->countAllResults());
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertStatus(200);
        $response->assertSee('Descrever Imagem');
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
        $response->assertDontSee('<script>alert(1)</script>');
    }

    public function testRejectsStaleFileAndNonImageBeforeCallingOllama(): void
    {
        $this->grantEditor();
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->never())->method('describe');
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submit(['source_file_id' => 'different-file'])->assertRedirect();
        $this->assertStringContainsString('mudou', session('error'));
        db_connect()->table('repository_files')->where('id', $this->fileId)->update(['mime_type' => 'application/pdf']);
        $this->submit()->assertRedirect();
        $this->assertStringContainsString('não possui uma imagem', session('error'));
    }

    public function testRemoteFailurePreservesPreviousDescription(): void
    {
        $this->grantEditor();
        $now = date('Y-m-d H:i:s');
        db_connect()->table('piece_image_descriptions')->insert(['source_file_id' => $this->fileId, 'piece_id' => $this->piece['id'], 'description' => 'Descrição anterior', 'model' => 'qwen3.5:2b', 'created_at' => $now, 'updated_at' => $now]);
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->method('describe')->willThrowException(new RuntimeException('O Ollama está indisponível.'));
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submit()->assertRedirect();
        $this->assertStringNotContainsString('private network detail', session('error'));
        $this->assertSame('Descrição anterior', db_connect()->table('piece_image_descriptions')->get()->getRowArray()['description']);
    }

    public function testInvalidCsrfCannotGenerateDescription(): void
    {
        $this->grantEditor();
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->never())->method('describe');
        Services::injectMock('ollamaImageDescriber', $client);
        $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
            ->withHeaders(['Referer' => 'http://example.com/index.php/pecas/' . $this->piece['id']])
            ->post('/pecas/' . $this->piece['id'] . '/descrever-imagem', ['source_file_id' => $this->fileId])->assertRedirect();
        $this->assertSame(0, db_connect()->table('piece_image_descriptions')->countAllResults());
        $this->assertStringContainsString('formulário expirou', session('error'));
    }

    public function testDescriptionBelongsToDisplayedFileAndGenerationRequiresPermission(): void
    {
        $now = date('Y-m-d H:i:s');
        db_connect()->table('piece_image_descriptions')->insert(['source_file_id' => $this->fileId, 'piece_id' => $this->piece['id'], 'description' => 'Descrição da primeira versão', 'model' => 'qwen3.5:2b', 'created_at' => $now, 'updated_at' => $now]);
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertSee('Descrição da primeira versão');
        $response->assertDontSee('data-describe-image');
        db_connect()->table('asset_versions')->insert(['id' => '30000000-0000-4000-8000-000000000002', 'asset_id' => '20000000-0000-4000-8000-000000000001', 'version_number' => 2, 'change_notes' => 'Nova imagem']);
        db_connect()->table('repository_files')->insert(['id' => '40000000-0000-4000-8000-000000000002', 'version_id' => '30000000-0000-4000-8000-000000000002', 'original_name' => 'new.png', 'storage_key' => 'repository/test/new.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'sha256' => str_repeat('b', 64)]);
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertDontSee('Descrição da primeira versão');
        $response->assertSee('Esta imagem ainda não possui uma descrição.');
    }

    public function testPeopleDetectionPersistsAndRendersVisibleAttributes(): void
    {
        $this->grantEditor();
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->once())->method('detectPeople')->willReturn([
            'count_estimate' => 1, 'notes' => 'Estimativa.', 'model' => 'qwen3.5:2b',
            'people' => [['position' => 'À esquerda', 'clothing' => '<script>roupa</script>', 'activity' => 'Lendo']],
        ]);
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submitPeople('detectar-pessoas')->assertRedirectTo('/pecas/' . $this->piece['id']);
        $saved = db_connect()->table('piece_people_detections')->get()->getRowArray();
        $this->assertSame($this->fileId, $saved['source_file_id']);
        $this->assertSame($this->userId, $saved['generated_by']);
        $this->assertSame(1, db_connect()->table('audit_events')->where('action', 'piece.people_detected')->countAllResults());
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertStatus(200);
        $response->assertSee('Detectar pessoas');
        $response->assertSee('Quantidade estimada:');
        $response->assertSee('&lt;script&gt;roupa&lt;/script&gt;');
        $response->assertDontSee('<script>roupa</script>');
        $response->assertSee('Salvar nomes');
    }

    public function testManualNamesAreSavedWithoutCallingOllamaOrLoggingNames(): void
    {
        $this->grantEditor();
        $detectionId = $this->seedPeopleDetection();
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->never())->method('detectPeople');
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submitPeople('pessoas/nomes', ['detection_id' => $detectionId, 'names' => [' Ana <teste> ']])->assertRedirect();
        $saved = db_connect()->table('piece_people_detections')->where('id', $detectionId)->get()->getRowArray();
        $this->assertSame(['Ana <teste>'], json_decode($saved['manual_names'], true));
        $this->assertSame($this->userId, $saved['names_updated_by']);
        $audit = db_connect()->table('audit_events')->where('action', 'piece.people_names_updated')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Ana', $audit['details']);
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertSee('Ana &lt;teste&gt;</option>');
        $response->assertSee('Autoridade da comunidade');
        $this->submitPeople('pessoas/nomes', ['detection_id' => $detectionId, 'names' => ['']])->assertRedirect();
        $this->assertSame([''], json_decode(db_connect()->table('piece_people_detections')->where('id', $detectionId)->get()->getRowArray()['manual_names'], true));
    }

    public function testPeopleActionsRequirePermissionAndCsrf(): void
    {
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->expects($this->never())->method('detectPeople');
        Services::injectMock('ollamaImageDescriber', $client);
        foreach (['detectar-pessoas', 'pessoas/nomes'] as $action) {
            $this->submitPeople($action)->assertRedirect();
            $this->assertStringContainsString('permissão', session('error'));
        }
        $this->grantEditor();
        foreach (['detectar-pessoas', 'pessoas/nomes'] as $action) {
            $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
                ->withHeaders(['Referer' => 'http://example.com/index.php/pecas/' . $this->piece['id']])
                ->post('/pecas/' . $this->piece['id'] . '/' . $action, [])->assertRedirect();
            $this->assertStringContainsString('formulário expirou', session('error'));
        }
        $this->assertSame(0, db_connect()->table('piece_people_detections')->countAllResults());
    }

    public function testNamesRejectStaleAnalysisAndInvalidValues(): void
    {
        $this->grantEditor();
        $detectionId = $this->seedPeopleDetection();
        foreach ([
            ['detection_id' => 'other-id', 'names' => ['Ana']],
            ['detection_id' => $detectionId, 'source_file_id' => 'other-file', 'names' => ['Ana']],
            ['detection_id' => $detectionId, 'names' => [str_repeat('a', 191)]],
            ['detection_id' => $detectionId, 'names' => ['Ana', 'Extra']],
            ['detection_id' => $detectionId, 'names' => [['nested']]],
        ] as $data) {
            $this->submitPeople('pessoas/nomes', $data)->assertRedirect();
            $this->assertNotEmpty(session('error'));
            $this->assertNull(db_connect()->table('piece_people_detections')->where('id', $detectionId)->get()->getRowArray()['manual_names']);
        }
    }

    public function testNewDetectionKeepsPreviousManualAssociationsAndDoesNotTransferNames(): void
    {
        $this->grantEditor();
        $oldId = $this->seedPeopleDetection();
        db_connect()->table('piece_people_detections')->where('id', $oldId)->update(['manual_names' => '["Nome anterior"]']);
        $client = $this->createMock(OllamaImageDescriber::class);
        $client->method('detectPeople')->willReturn(['count_estimate' => 0, 'notes' => '', 'people' => [], 'model' => 'qwen3.5:2b']);
        Services::injectMock('ollamaImageDescriber', $client);
        $this->submitPeople('detectar-pessoas')->assertRedirect();
        $this->assertSame(2, db_connect()->table('piece_people_detections')->countAllResults());
        $this->assertSame('["Nome anterior"]', db_connect()->table('piece_people_detections')->where('id', $oldId)->get()->getRowArray()['manual_names']);
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertSee('Nenhuma pessoa detectada.');
        $response->assertDontSee('Nome anterior');
        $this->submitPeople('pessoas/nomes', ['detection_id' => $oldId, 'names' => ['Changed']])->assertRedirect();
        $this->assertStringContainsString('análise de pessoas mudou', session('error'));
    }

    public function testCanRenamePieceAndDisplayedFilenameWhilePreservingOriginalFile(): void
    {
        $this->grantEditor();
        $original = db_connect()->table('repository_files')->where('id', $this->fileId)->get()->getRowArray();
        $this->submitPeople('nome', ['title' => 'Novo título', 'filename' => 'nova-foto.png'])->assertRedirectTo('/pecas/' . $this->piece['id']);
        $piece = db_connect()->table('pieces')->where('id', $this->piece['id'])->get()->getRowArray();
        $this->assertSame('Novo título', $piece['title']);
        $this->assertSame('nova-foto.png', $piece['original_filename']);
        $this->assertSame($original, db_connect()->table('repository_files')->where('id', $this->fileId)->get()->getRowArray());
        $this->assertSame(1, db_connect()->table('audit_events')->where('action', 'piece.renamed')->countAllResults());
        $response = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $response->assertSee('Novo título');
        $response->assertSee('nova-foto.png');
        $response->assertSee('Salvar nomes');
    }

    public function testRenameValidatesFilenameAndRejectsStaleFile(): void
    {
        $this->grantEditor();
        foreach (['../foto.png', 'pasta\\foto.png', 'foto.jpg', 'foto.png.exe', '.png', "foto\n.png", str_repeat('a', 256) . '.png'] as $filename) {
            $this->submitPeople('nome', ['title' => 'Novo título', 'filename' => $filename])->assertRedirect();
            $this->assertNotEmpty(session('error'));
            $this->assertSame($this->piece['title'], db_connect()->table('pieces')->where('id', $this->piece['id'])->get()->getRowArray()['title']);
        }
        $this->submitPeople('nome', ['title' => 'Novo título', 'filename' => 'valid.png', 'source_file_id' => 'outro-arquivo'])->assertRedirect();
        $this->assertStringContainsString('mudou', session('error'));
        $this->submitPeople('nome', ['title' => '', 'filename' => 'valid.png'])->assertRedirect();
        $this->assertStringContainsString('nome da peça', session('error'));
    }

    public function testRenameRequiresPermissionAndCsrf(): void
    {
        $this->submitPeople('nome', ['title' => 'Indevido', 'filename' => 'foto.png'])->assertRedirect();
        $this->assertStringContainsString('permissão', session('error'));
        $this->grantEditor();
        $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])
            ->withHeaders(['Referer' => 'http://example.com/index.php/pecas/' . $this->piece['id']])
            ->post('/pecas/' . $this->piece['id'] . '/nome', ['title' => 'Indevido', 'filename' => 'foto.png', 'source_file_id' => $this->fileId])->assertRedirect();
        $this->assertStringContainsString('formulário expirou', session('error'));
        $this->assertSame($this->piece['title'], db_connect()->table('pieces')->where('id', $this->piece['id'])->get()->getRowArray()['title']);
    }

    public function testCommunityAuthorityCanBeReusedAndNameVariantsDoNotDuplicateRecord(): void
    {
        $this->grantEditor();
        $id = $this->seedPeopleDetection();
        $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => ['  Ana   Silva  ']])->assertRedirect();
        $authority = db_connect()->table('person_authorities')->get()->getRowArray();
        $this->assertSame('Ana Silva', $authority['preferred_name']);
        $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => ['ana silva']])->assertRedirect();
        $this->assertSame(1, db_connect()->table('person_authorities')->countAllResults());
        $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => [''], 'authority_ids' => [$authority['id']]])->assertRedirect();
        $link = db_connect()->table('piece_person_authorities')->where('detection_id', $id)->get()->getRowArray();
        $this->assertSame($authority['id'], $link['authority_id']);
        $this->assertSame(['Ana Silva'], json_decode(db_connect()->table('piece_people_detections')->where('id', $id)->get()->getRowArray()['manual_names'], true));
        $page = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $page->assertSee('value="' . $authority['id'] . '" selected');
    }

    public function testAuthoritySelectionRejectsAnotherCommunityAndConflictingInputs(): void
    {
        $this->grantEditor();
        $id = $this->seedPeopleDetection();
        $projects = new ProjectModel();
        $projects->insert(['code' => 'OTHER', 'name' => 'Outra comunidade']);
        $other = $projects->where('code', 'OTHER')->first();
        $foreign = (new \App\Libraries\PersonAuthority())->resolve($other['id'], 'Nome de outra comunidade');
        $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => [''], 'authority_ids' => [$foreign['id']]])->assertRedirect();
        $this->assertStringContainsString('da comunidade', session('error'));
        $this->assertSame(0, db_connect()->table('piece_person_authorities')->countAllResults());
        $local = (new \App\Libraries\PersonAuthority())->resolve($this->piece['project_id'], 'Nome local');
        $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => ['Outro nome'], 'authority_ids' => [$local['id']]])->assertRedirect();
        $this->assertSame(0, db_connect()->table('piece_person_authorities')->countAllResults());
        $page = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $page->assertSee('Nome local');
        $page->assertDontSee('Nome de outra comunidade');
    }

    public function testQualifierDistinguishesPeopleWithSameName(): void
    {
        $this->grantEditor();
        $id = $this->seedPeopleDetection();
        foreach (['Biblioteca', 'Arquivo'] as $qualifier) {
            $this->submitPeople('pessoas/nomes', ['detection_id' => $id, 'names' => ['Ana Silva'], 'qualifiers' => [$qualifier]])->assertRedirect();
        }
        $this->assertSame(2, db_connect()->table('person_authorities')->where('project_id', $this->piece['project_id'])->countAllResults());
        $this->assertSame(1, db_connect()->table('piece_person_authorities')->where('detection_id', $id)->countAllResults());
        $page = $this->withSession(['user_id' => $this->userId, 'user_name' => 'Pessoa teste'])->get('/pecas/' . $this->piece['id']);
        $page->assertSee('Ana Silva — Biblioteca');
        $page->assertSee('Ana Silva — Arquivo');
    }

    public function testExistingManualAssociationsAreMigratedToCommunityAuthority(): void
    {
        $id = $this->seedPeopleDetection();
        db_connect()->table('piece_people_detections')->where('id', $id)->update(['manual_names' => '["Ana Silva"]']);
        $migration = new \App\Database\Migrations\CreatePersonAuthorities();
        $migration->down();
        $migration->up();
        $authority = db_connect()->table('person_authorities')->get()->getRowArray();
        $link = db_connect()->table('piece_person_authorities')->where('detection_id', $id)->get()->getRowArray();
        $this->assertSame($this->piece['project_id'], $authority['project_id']);
        $this->assertSame('Ana Silva', $authority['preferred_name']);
        $this->assertSame($authority['id'], $link['authority_id']);
        $this->assertSame('["Ana Silva"]', db_connect()->table('piece_people_detections')->where('id', $id)->get()->getRowArray()['manual_names']);
    }
}
