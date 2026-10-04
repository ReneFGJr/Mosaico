<?php

namespace App\Models;

class ProjectModel extends UuidModel
{
    protected $table         = 'projects';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'description', 'status', 'visibility'];
    protected $validationRules = [
        'code' => 'required|max_length[80]',
        'name' => 'required|max_length[190]',
    ];
}
