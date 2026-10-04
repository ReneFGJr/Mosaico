<?php

namespace App\Models;

final class AssetModel extends UuidModel
{
    protected $table = 'assets';
    protected $returnType = 'array';
    protected $allowedFields = ['piece_id', 'name', 'type', 'description', 'created_by'];
    protected $validationRules = [
        'piece_id' => 'required|is_not_unique[pieces.id]',
        'name' => 'required|max_length[190]',
        'type' => 'required|max_length[80]',
    ];
}
