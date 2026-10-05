<?php

namespace App\Models;

class PieceModel extends UuidModel
{
    protected $table         = 'pieces';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'project_id',
        'code',
        'title',
        'summary',
        'type',
        'original_directory',
        'original_filename',
        'pedagogical_purpose',
        'audience',
        'status',
        'visibility',
        'access_condition',
        'created_by',
    ];
    protected $validationRules = [
        'project_id' => 'required|is_not_unique[projects.id]',
        'code'       => 'required|max_length[80]',
        'title'      => 'required|max_length[255]',
        'type'       => 'required|max_length[80]',
        'status'     => 'permit_empty|in_list[draft,in_production,in_review,changes_requested,approved,published,withdrawn,archived]',
        'visibility' => 'permit_empty|in_list[private,internal,public]',
    ];
}
