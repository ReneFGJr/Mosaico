<?php

use App\Libraries\ProjectAccess;
use App\Models\CollectionModel;
use App\Models\ProjectModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class AdminUsersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;
    private string $admin = '10000000-0000-4000-8000-000000000001';
    private string $member = '10000000-0000-4000-8000-000000000002';
    private array $project;
    private array $collection;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([$this->admin => true, $this->member => false] as $id => $isAdmin) {
            db_connect()->table('users')->insert(['id' => $id, 'email' => $isAdmin ? 'admin@example.test' : 'member@example.test', 'display_name' => $isAdmin ? 'Admin' : 'Member', 'password_hash' => password_hash('Testpass123', PASSWORD_DEFAULT), 'is_active' => true, 'is_admin' => $isAdmin]);
        }
        $projects = new ProjectModel();
        $projects->insert(['code' => 'ADMIN', 'name' => 'Comunidade teste']);
        $this->project = $projects->first();
        $collections = new CollectionModel();
        $collections->insert(['code' => 'COL', 'name' => 'Coleção teste', 'project_id' => $this->project['id']]);
        $this->collection = $collections->first();
        $this->withSession(['user_id' => $this->admin, 'user_name' => 'Admin']);
    }

    private function submit(string $url, array $data = [])
    {
        $token = csrf_hash();
        return $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])->post($url, $data + ['csrf_test_name' => $token]);
    }

    private function data(array $changes = []): array
    {
        return $changes + ['display_name' => 'Member atualizado', 'email' => 'member@example.test', 'is_active' => '1', 'communities' => [], 'collections' => []];
    }

    public function testAdministrationRequiresGlobalAdministratorAndCsrf(): void
    {
        $this->withSession(['user_id' => $this->member, 'user_name' => 'Member'])->get('/admin/users')->assertStatus(403);
        $role = db_connect()->table('roles')->where('code', 'administrator')->get()->getRowArray();
        db_connect()->table('memberships')->insert(['project_id' => $this->project['id'], 'user_id' => $this->member, 'role_id' => $role['id']]);
        $this->get('/admin/users')->assertStatus(403);
        $this->submit('/admin/users/' . $this->admin, $this->data(['is_admin' => '1']))->assertStatus(403);
        $this->withSession(['user_id' => $this->admin, 'user_name' => 'Admin'])->post('/admin/users/' . $this->member . '/impersonate')->assertRedirect();
        $this->assertNull(session('impersonator_id'));
    }

    public function testCreateEditAndRemoveScopedAccess(): void
    {
        $this->get('/admin/users')->assertSee('Novo usuário');
        $this->get('/admin/users/new')->assertSee('Acessos às coleções');
        $role = db_connect()->table('roles')->where('code', 'consultation')->get()->getRowArray();
        $payload = $this->data(['email' => 'new@example.test', 'display_name' => 'Nova pessoa', 'password' => 'Password123', 'communities' => [$this->project['id'] => $role['id']], 'collections' => [$this->collection['id'] => 'add']]);
        $this->submit('/admin/users/new', $payload)->assertRedirect();
        $db = db_connect();
        $user = $db->table('users')->where('email', 'new@example.test')->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('Password123', $user['password_hash']));
        $this->assertSame(1, $db->table('memberships')->where(['user_id' => $user['id'], 'project_id' => $this->project['id']])->countAllResults());
        $this->assertSame('add', $db->table('collection_members')->where('user_id', $user['id'])->get()->getRowArray()['privilege']);
        $this->assertTrue((new ProjectAccess())->allows($user['id'], $this->project['id'], 'pieces.read'));
        $this->assertFalse((new ProjectAccess())->allows($user['id'], $this->project['id'], 'projects.manage'));
        $this->get('/admin/users/' . $user['id'])->assertSee('Nova pessoa');
        $this->submit('/admin/users/' . $user['id'], $this->data(['email' => 'new@example.test', 'is_admin' => '1']))->assertRedirect();
        $saved = $db->table('users')->where('id', $user['id'])->get()->getRowArray();
        $this->assertTrue((bool) $saved['is_admin']);
        $this->assertSame($user['password_hash'], $saved['password_hash']);
        $this->assertSame(0, $db->table('memberships')->where('user_id', $user['id'])->countAllResults());
        $this->assertSame(0, $db->table('collection_members')->where('user_id', $user['id'])->countAllResults());
        $this->assertTrue((new ProjectAccess())->allows($user['id'], $this->project['id'], 'projects.manage'));
    }

    public function testValidationProtectsExistingAccountsAndSelf(): void
    {
        $this->submit('/admin/users/new', $this->data(['password' => 'weak']))->assertSee('Este e-mail já está cadastrado.');
        $this->submit('/admin/users/' . $this->member, $this->data(['collections' => [$this->collection['id'] => 'owner']]))->assertSee('permissão válidas');
        $this->submit('/admin/users/' . $this->admin, $this->data(['email' => 'admin@example.test']))->assertSee('próprio perfil de administrador');
        $this->submit('/admin/users/' . $this->admin . '/delete', ['confirmed' => '1'])->assertRedirect();
        $this->assertSame(2, db_connect()->table('users')->where('is_active', true)->countAllResults());
    }

    public function testDeactivationPreservesHistoryAndCanBeReversed(): void
    {
        $this->get('/admin/users/' . $this->member . '/delete')->assertSee('O histórico e os arquivos serão preservados.');
        $this->submit('/admin/users/' . $this->member . '/delete', ['confirmed' => '1'])->assertRedirect();
        $this->assertSame(0, db_connect()->table('users')->where(['id' => $this->member, 'is_active' => true])->countAllResults());
        $this->assertSame(1, db_connect()->table('audit_events')->where('action', 'user.deactivated')->countAllResults());
        $this->submit('/admin/users/' . $this->member, $this->data())->assertRedirect();
        $this->assertSame(1, db_connect()->table('users')->where(['id' => $this->member, 'is_active' => true])->countAllResults());
    }

    public function testImpersonationUsesTargetPermissionsAndReturnsToAdministrator(): void
    {
        $this->submit('/admin/users/' . $this->member . '/impersonate')->assertRedirectTo('/painel');
        $this->assertSame($this->member, session('user_id'));
        $this->assertSame($this->admin, session('impersonator_id'));
        $this->assertFalse((new ProjectAccess())->allows($this->member, $this->project['id'], 'projects.manage'));
        $this->withSession(['user_id' => $this->member, 'user_name' => 'Member', 'impersonator_id' => $this->admin])->get('/painel')->assertSee('Voltar à minha conta');
        $this->get('/admin/users')->assertStatus(403);
        $this->submit('/admin/users/' . $this->admin . '/impersonate')->assertStatus(403);
        (new \App\Libraries\AuditLogger())->record('test.impersonated_action', 'user', $this->member);
        $event = db_connect()->table('audit_events')->where('action', 'test.impersonated_action')->get()->getRowArray();
        $this->assertSame($this->member, $event['user_id']);
        $this->assertSame($this->admin, json_decode($event['details'], true)['impersonator_id']);
        $this->submit('/admin/users/stop-impersonating')->assertRedirectTo('/admin/users');
        $this->assertSame($this->admin, session('user_id'));
        $this->assertNull(session('impersonator_id'));
        $this->assertSame(2, db_connect()->table('audit_events')->whereIn('action', ['auth.impersonation_started', 'auth.impersonation_finished'])->countAllResults());
    }

    public function testInactiveTargetsAndRevokedAdministratorsCannotImpersonate(): void
    {
        db_connect()->table('users')->where('id', $this->member)->update(['is_active' => false]);
        $this->submit('/admin/users/' . $this->member . '/impersonate')->assertRedirectTo('/admin/users');
        $this->assertNull(session('impersonator_id'));
        $this->withSession(['user_id' => $this->member, 'user_name' => 'Member', 'impersonator_id' => $this->admin]);
        db_connect()->table('users')->where('id', $this->member)->update(['is_active' => true]);
        db_connect()->table('users')->where('id', $this->admin)->update(['is_admin' => false]);
        $this->submit('/admin/users/stop-impersonating')->assertStatus(403);
        $this->assertSame($this->member, session('user_id'));
    }
}

