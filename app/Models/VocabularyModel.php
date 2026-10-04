<?php

namespace App\Models;

class VocabularyModel extends UuidModel
{
    protected $table         = 'vocabularies';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'description'];
    protected $validationRules = [
        'code' => 'required|max_length[80]',
        'name' => 'required|max_length[190]',
    ];
}