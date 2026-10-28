<?php

use App\Models\UuidModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ControlledVocabularyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $refresh = true;
    private string $user;
    private string $project;
    private string $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UuidModel::generateUuid();
        db_connect()->table('users')->insert(['id' => $this->user, 'email' => 'vocabulary@example.test', 'display_name' => 'Gestor', 'password_hash' => 'unused', 'is_active' => 1]);
        $projects = new \App\Models\ProjectModel();
        $this->project = $projects->insert(['code' => 'VOC', 'name' => 'Vocabulário']);
        $collections = new \App\Models\CollectionModel();
        $this->collection = $collections->insert(['project_id' => $this->project, 'code' => 'COL-VOC', 'name' => 'Fotos']);
    }

    private function requestVocabulary(?array $data = null)
    {
        $this->withSession(['user_id' => $this->user, 'user_name' => 'Gestor']);
        $url = '/comunidades/' . $this->project . '/vocabulario';
        if ($data === null) return $this->get($url);
        $token = csrf_hash();
        return $this->withHeaders(['Cookie' => 'csrf_cookie_name=' . $token])->post($url, $data + ['csrf_test_name' => $token]);
    }

    private function grantManager(): void
    {
        $role = db_connect()->table('roles')->where('code', 'administrator')->get()->getRowArray();
        db_connect()->table('memberships')->insert(['project_id' => $this->project, 'user_id' => $this->user, 'role_id' => $role['id']]);
    }

    public function testCrudRequiresManagerAndRejectsDuplicates(): void
    {
        $this->requestVocabulary()->assertRedirectTo('/comunidades');
        $this->requestVocabulary(['action' => 'save', 'source' => '#I', 'replacement' => 'Indigena'])->assertRedirectTo('/comunidades');
        $this->assertSame(0, db_connect()->table('label_replacements')->countAllResults());
        $this->grantManager();
        $page = $this->requestVocabulary();
        $page->assertStatus(200);
        $page->assertSee('Vocabulário controlado');
        $data = ['action' => 'save', 'source' => '#I', 'replacement' => 'Indigena'];
        $this->requestVocabulary($data)->assertRedirect();
        $this->requestVocabulary($data)->assertRedirect();
        $this->assertSame(1, db_connect()->table('label_replacements')->countAllResults());
        $rule = db_connect()->table('label_replacements')->get()->getRowArray();
        $this->requestVocabulary(['action' => 'save', 'rule_id' => $rule['id'], 'source' => '#I', 'replacement' => 'Indígena'])->assertRedirect();
        $this->assertSame('Indígena', db_connect()->table('label_replacements')->get()->getRowArray()['replacement']);
        $this->requestVocabulary(['action' => 'delete', 'rule_id' => $rule['id']])->assertRedirect();
        $this->assertSame(0, db_connect()->table('label_replacements')->countAllResults());
    }

    public function testScopedReplacementMergesTagsAndPreservesOtherPhotos(): void
    {
        $this->grantManager();
        $db = db_connect();
        $vocab = UuidModel::generateUuid();
        $db->table('vocabularies')->insert(['id' => $vocab, 'project_id' => $this->project, 'code' => 'TAGS', 'name' => 'Tags']);
        $source = UuidModel::generateUuid();
        $target = UuidModel::generateUuid();
        foreach ([$source => '#I', $target => 'Indigena'] as $id => $label) $db->table('terms')->insert(['id' => $id, 'vocabulary_id' => $vocab, 'code' => $id, 'label' => $label, 'is_active' => 1]);
        $photo = UuidModel::generateUuid();
        $other = UuidModel::generateUuid();
        foreach ([$photo, $other] as $id) {
            $db->table('pieces')->insert(['id' => $id, 'project_id' => $this->project, 'code' => $id, 'title' => '#I e #II', 'type' => 'imagem', 'status' => 'draft']);
            $db->table('piece_terms')->insert(['piece_id' => $id, 'term_id' => $source]);
        }
        $db->table('piece_terms')->insert(['piece_id' => $photo, 'term_id' => $target]);
        $db->table('piece_collections')->insert(['piece_id' => $photo, 'collection_id' => $this->collection]);
        $this->requestVocabulary(['action' => 'save', 'source' => '#I', 'replacement' => 'Indigena']);
        $this->requestVocabulary(['action' => 'apply', 'target' => 'both', 'collection_id' => $this->collection])->assertRedirect();
        $this->assertSame('2 termo(s) substituído(s) em 1 foto(s).', session('success'));
        $this->assertSame('Indigena e #II', $db->table('pieces')->where('id', $photo)->get()->getRowArray()['title']);
        $this->assertSame('#I e #II', $db->table('pieces')->where('id', $other)->get()->getRowArray()['title']);
        $this->assertSame(1, $db->table('piece_terms')->where('piece_id', $photo)->countAllResults());
        $this->assertSame($target, $db->table('piece_terms')->where('piece_id', $photo)->get()->getRowArray()['term_id']);
        $this->assertSame('#I', $db->table('terms')->where('id', $source)->get()->getRowArray()['label']);
        $this->requestVocabulary(['action' => 'apply', 'target' => 'tags', 'collection_id' => 'invalid'])->assertRedirect();
        $this->assertSame($source, $db->table('piece_terms')->where('piece_id', $other)->get()->getRowArray()['term_id']);
        $this->requestVocabulary(['action' => 'apply', 'target' => 'tags', 'collection_id' => ''])->assertRedirect();
        $this->assertSame('1 termo(s) substituído(s) em 1 foto(s).', session('success'));
        $this->assertSame($target, $db->table('piece_terms')->where('piece_id', $other)->get()->getRowArray()['term_id']);
        $this->requestVocabulary(['action' => 'apply', 'target' => 'tags', 'collection_id' => ''])->assertRedirect();
        $this->assertSame('0 termo(s) substituído(s) em 0 foto(s).', session('success'));
    }

    public function testReplacementUsesWordBoundariesAndDoesNotCascade(): void
    {
        $replacer = new \App\Libraries\LabelReplacer();
        $rules = [['source' => '#I', 'replacement' => 'Indigena'], ['source' => 'Indigena', 'replacement' => 'Outro']];
        $this->assertSame('Indigena, #II; Outro #i', $replacer->replace('#I, #II; Indigena #i', $rules));
        $this->assertSame('Indigena e Indigena #II', $replacer->replace('#I e #I #II', $rules, $count));
        $this->assertSame(2, $count);
    }

    public function testRulesAndCollectionsFromAnotherCommunityCannotBeChanged(): void
    {
        $this->grantManager();
        $otherProject = (new \App\Models\ProjectModel())->insert(['code' => 'OTHER-VOC', 'name' => 'Outra']);
        $otherCollection = (new \App\Models\CollectionModel())->insert(['project_id' => $otherProject, 'code' => 'OTHER-COL', 'name' => 'Outra coleção']);
        $ruleId = UuidModel::generateUuid();
        db_connect()->table('label_replacements')->insert(['id' => $ruleId, 'project_id' => $otherProject, 'source' => '#I', 'replacement' => 'Original']);
        $this->requestVocabulary(['action' => 'save', 'rule_id' => $ruleId, 'source' => '#I', 'replacement' => 'Alterado'])->assertRedirect();
        $this->requestVocabulary(['action' => 'delete', 'rule_id' => $ruleId])->assertRedirect();
        $this->assertSame('Original', db_connect()->table('label_replacements')->where('id', $ruleId)->get()->getRowArray()['replacement']);
        $this->requestVocabulary(['action' => 'apply', 'target' => 'both', 'collection_id' => $otherCollection])->assertRedirect();
        $this->assertSame(0, db_connect()->table('audit_events')->where('action', 'community.labels_replaced')->countAllResults());
    }
}
