<?php

use App\Libraries\ProjectAccess;
use App\Database\Seeds\AdminUserSeeder;
use App\Models\PieceModel;
use App\Models\ProjectModel;
use App\Models\TermModel;
use App\Models\VocabularyModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class MosaicoFoundationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $refresh   = true;

    public function testAdminUserSeederUsesConfiguredPasswordAndIsIdempotent(): void
    {
        $previousPassword = getenv('MOSAICO_BOOTSTRAP_PASSWORD');
        putenv('MOSAICO_BOOTSTRAP_PASSWORD=senha-admin-de-teste');
        $_ENV['MOSAICO_BOOTSTRAP_PASSWORD'] = 'senha-admin-de-teste';

        try {
            $this->seed(AdminUserSeeder::class);

            $user = db_connect()->table('users')->where('email', 'admin@mosaico.local')->get()->getRowArray();

            $this->assertNotNull($user);
            $this->assertSame('Administrador', $user['display_name']);
            $this->assertTrue(password_verify('senha-admin-de-teste', $user['password_hash']));

            $passwordHash = $user['password_hash'];
            putenv('MOSAICO_BOOTSTRAP_PASSWORD=outra-senha-admin');
            $_ENV['MOSAICO_BOOTSTRAP_PASSWORD'] = 'outra-senha-admin';
            $this->seed(AdminUserSeeder::class);

            $this->assertSame(1, db_connect()->table('users')->where('email', 'admin@mosaico.local')->countAllResults());
            $this->assertSame($passwordHash, db_connect()->table('users')->where('email', 'admin@mosaico.local')->get()->getRowArray()['password_hash']);
        } finally {
            if ($previousPassword === false) {
                putenv('MOSAICO_BOOTSTRAP_PASSWORD');
                unset($_ENV['MOSAICO_BOOTSTRAP_PASSWORD']);
            } else {
                putenv('MOSAICO_BOOTSTRAP_PASSWORD=' . $previousPassword);
                $_ENV['MOSAICO_BOOTSTRAP_PASSWORD'] = $previousPassword;
            }
        }
    }

    public function testPieceHasStableIdentityAndUniqueProjectCode(): void
    {
        $projectModel = new ProjectModel();
        $projectModel->insert(['code' => 'PROJ-1', 'name' => 'Projeto de teste']);
        $project = $projectModel->first();

        $pieceModel = new PieceModel();
        $pieceModel->insert([
            'project_id' => $project['id'],
            'code'       => 'PECA-1',
            'title'      => 'Roteiro introdutório',
            'type'       => 'script',
        ]);
        $piece = $pieceModel->first();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $piece['id'],
        );
        $this->assertSame('draft', $piece['status']);
        $this->assertSame('private', $piece['visibility']);
        try {
            $pieceModel->insert([
                'project_id' => $project['id'],
                'code'       => 'PECA-1',
                'title'      => 'Código duplicado',
                'type'       => 'script',
            ]);
            $this->fail('O banco deve impedir códigos repetidos dentro do projeto.');
        } catch (DatabaseException $exception) {
            $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
        }
    }

    public function testControlledTermCanBeAssignedToPiece(): void
    {
        $projectModel = new ProjectModel();
        $projectModel->insert(['code' => 'PROJ-2', 'name' => 'Projeto de teste']);
        $project = $projectModel->first();

        $pieceModel = new PieceModel();
        $pieceModel->insert([
            'project_id' => $project['id'],
            'code'       => 'PECA-2',
            'title'      => 'Peça classificada',
            'type'       => 'video',
        ]);
        $piece = $pieceModel->first();

        $vocabularyModel = new VocabularyModel();
        $vocabularyModel->insert(['project_id' => $project['id'], 'code' => 'assunto', 'name' => 'Assunto']);
        $vocabulary = $vocabularyModel->first();

        $termModel = new TermModel();
        $termModel->insert([
            'vocabulary_id' => $vocabulary['id'],
            'code'          => 'saude-digital',
            'label'         => 'Saúde digital',
        ]);
        $term = $termModel->first();

        db_connect()->table('piece_terms')->insert([
            'piece_id' => $piece['id'],
            'term_id'  => $term['id'],
        ]);

        $this->seeInDatabase('piece_terms', [
            'piece_id' => $piece['id'],
            'term_id'  => $term['id'],
        ]);
    }

    public function testProjectPermissionsAreScopedAndDeniedByDefault(): void
    {
        $projectModel = new ProjectModel();
        $projectModel->insert(['code' => 'PROJ-3', 'name' => 'Projeto autorizado']);
        $project = $projectModel->first();
        $userId = '9d4a7707-ffab-4ca2-b93f-cb1554341baa';

        db_connect()->table('users')->insert([
            'id'            => $userId,
            'email'         => 'produtor@example.test',
            'display_name'  => 'Pessoa de teste',
            'password_hash' => password_hash('teste-nao-reutilizar', PASSWORD_DEFAULT),
        ]);
        $role = db_connect()->table('roles')->where('code', 'producer_editor')->get()->getRowArray();
        db_connect()->table('memberships')->insert([
            'project_id' => $project['id'],
            'user_id'    => $userId,
            'role_id'    => $role['id'],
        ]);

        $access = new ProjectAccess(db_connect());

        $this->assertTrue($access->allows($userId, $project['id'], 'pieces.create'));
        $this->assertFalse($access->allows($userId, $project['id'], 'vocabularies.manage'));
        $this->assertFalse($access->allows($userId, '00000000-0000-4000-8000-000000000000', 'pieces.read'));
        $this->assertFalse($access->allows('00000000-0000-4000-8000-000000000000', $project['id'], 'pieces.read'));
    }
}