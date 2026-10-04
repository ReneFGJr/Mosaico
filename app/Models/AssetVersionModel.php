<?php

namespace App\Models;

final class AssetVersionModel extends UuidModel
{
    protected $table = 'asset_versions';
    protected $returnType = 'array';
    protected $updatedField = '';
    protected $allowedFields = ['asset_id', 'version_number', 'change_notes', 'status', 'submitted_at', 'created_by'];
    protected $validationRules = [
        'asset_id' => 'required|is_not_unique[assets.id]',
        'version_number' => 'required|is_natural_no_zero',
        'change_notes' => 'required',
        'status' => 'permit_empty|in_list[draft,submitted,approved,published,archived,deleted]',
    ];
}
