<?php

namespace App\Libraries;

use App\Models\UuidModel;

final class PersonAuthority
{
    public function resolve(string $projectId, string $name, string $qualifier = ''): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $qualifier = trim(preg_replace('/\s+/u', ' ', $qualifier) ?? $qualifier);
        $key = hash('sha256', mb_strtolower(\Normalizer::normalize($name . "\n" . $qualifier, \Normalizer::FORM_C)));
        $db = db_connect();
        $existing = $db->table('person_authorities')->where('project_id', $projectId)->where('name_key', $key)->get()->getRowArray();
        if ($existing) {
            return $existing;
        }
        $record = ['id' => UuidModel::generateUuid(), 'project_id' => $projectId, 'preferred_name' => $name,
            'qualifier' => $qualifier, 'name_key' => $key, 'created_at' => date('Y-m-d H:i:s')];
        $db->table('person_authorities')->insert($record);
        return $record;
    }
}
