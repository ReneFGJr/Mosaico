<?php

namespace App\Models;

class VocabularyModel extends UuidModel
{
    protected $table         = 'vocabularies';
    protected $returnType    = 'array';
    protected $allowedFields = ['project_id', 'code', 'name', 'description'];
    protected $validationRules = [
        'project_id' => 'required|is_not_unique[projects.id]',
        'code' => 'required|max_length[80]',
        'name' => 'required|max_length[190]',
    ];
}