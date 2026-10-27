<?php

namespace App\Database\Migrations;

use App\Libraries\PersonAuthority;
use CodeIgniter\Database\Migration;

final class CreatePersonAuthorities extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'project_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'preferred_name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'qualifier' => ['type' => 'VARCHAR', 'constraint' => 190, 'default' => ''],
            'name_key' => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['project_id', 'name_key'], false, true);
        $this->forge->addForeignKey('project_id', 'projects', 'id', 'RESTRICT');
        $this->forge->createTable('person_authorities', true);
        $this->forge->addField([
            'detection_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'person_index' => ['type' => 'INT', 'unsigned' => true],
            'authority_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        ]);
        $this->forge->addKey(['detection_id', 'person_index'], true);
        $this->forge->addForeignKey('detection_id', 'piece_people_detections', 'id', 'RESTRICT');
        $this->forge->addForeignKey('authority_id', 'person_authorities', 'id', 'RESTRICT');
        $this->forge->createTable('piece_person_authorities', true);

        $rows = $this->db->table('piece_people_detections AS detections')->select('detections.id, detections.manual_names, detections.result_json, pieces.project_id')
            ->join('pieces', 'pieces.id = detections.piece_id')->where('manual_names !=', null)->get()->getResultArray();
        foreach ($rows as $row) {
            $names = json_decode($row['manual_names'], true) ?: [];
            $result = json_decode($row['result_json'], true) ?: [];
            foreach ($names as $index => $name) {
                if (! is_string($name) || trim($name) === '' || ! isset($result['people'][$index])) {
                    continue;
                }
                $authority = (new PersonAuthority())->resolve($row['project_id'], $name);
                $this->db->table('piece_person_authorities')->insert(['detection_id' => $row['id'], 'person_index' => $index, 'authority_id' => $authority['id']]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('piece_person_authorities', true);
        $this->forge->dropTable('person_authorities', true);
    }
}
