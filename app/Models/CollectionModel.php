<?php

namespace App\Models;

final class CollectionModel extends UuidModel
{
    protected $table = 'collections';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'project_id', 'code', 'name', 'description', 'cover_image'];
    protected $validationRules = [
        'project_id' => 'required|is_not_unique[projects.id]',
        'code' => 'required|max_length[160]',
        'name' => 'required|max_length[190]',
    ];
}
