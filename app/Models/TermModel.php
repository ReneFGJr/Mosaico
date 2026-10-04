<?php

namespace App\Models;

class TermModel extends UuidModel
{
    protected $table         = 'terms';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'vocabulary_id',
        'code',
        'label',
        'description',
        'parent_term_id',
        'replaced_by_id',
        'is_active',
    ];
    protected $validationRules = [
        'vocabulary_id' => 'required|is_not_unique[vocabularies.id]',
        'code'          => 'required|max_length[80]',
        'label'         => 'required|max_length[190]',
    ];
}