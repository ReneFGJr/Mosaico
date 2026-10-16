<?php

namespace App\Controllers;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $counts = [];
        foreach (['projects', 'collections', 'pieces', 'assets', 'asset_versions'] as $table) {
            $counts[$table] = $db->table($table)->countAllResults();
        }
        $recent = $db->table('pieces')->select('pieces.*, projects.name AS project_name')->join('projects', 'projects.id = pieces.project_id')->orderBy('pieces.created_at', 'DESC')->limit(5)->get()->getResultArray();
        return view('dashboard/index', ['title' => 'Painel', 'contentClass' => 'content-wide', 'counts' => $counts, 'recent' => $recent]);
    }
}
