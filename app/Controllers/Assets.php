<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;
use App\Models\AssetModel;
use App\Models\AssetVersionModel;

final class Assets extends BaseController
{
    public function create(string $pieceId)
    {
        $piece = db_connect()->table('pieces')->where('id', $pieceId)->get()->getRowArray();
        if (! $piece) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if ($this->request->getMethod() === 'POST') {
            $model = new AssetModel();
            if ($model->insert(['piece_id' => $pieceId, 'name' => trim((string) $this->request->getPost('name')), 'type' => (string) $this->request->getPost('type'), 'description' => trim((string) $this->request->getPost('description')), 'created_by' => session('user_id')])) {
                $id = (string) $model->getInsertID();
                (new AuditLogger())->record('asset.created', 'asset', $id, ['piece_id' => $pieceId]);
                return redirect()->to('/ativos/' . $id . '/versoes/nova')->with('success', 'Ativo criado. Registre agora a primeira versão.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('assets/form', ['title' => 'Novo ativo', 'piece' => $piece]);
    }

    public function version(string $assetId)
    {
        $db = db_connect();
        $asset = $db->table('assets')->select('assets.*, pieces.title AS piece_title')->join('pieces', 'pieces.id = assets.piece_id')->where('assets.id', $assetId)->get()->getRowArray();
        if (! $asset) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if ($this->request->getMethod() === 'POST') {
            $latest = (int) ($db->table('asset_versions')->selectMax('version_number')->where('asset_id', $assetId)->get()->getRow('version_number') ?? 0);
            $model = new AssetVersionModel();
            if ($model->insert(['asset_id' => $assetId, 'version_number' => $latest + 1, 'change_notes' => trim((string) $this->request->getPost('change_notes')), 'status' => 'draft', 'created_by' => session('user_id')])) {
                (new AuditLogger())->record('asset_version.created', 'asset_version', (string) $model->getInsertID(), ['asset_id' => $assetId, 'version' => $latest + 1]);
                return redirect()->to('/pecas/' . $asset['piece_id'])->with('success', 'Versão registrada.');
            }
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        return view('assets/version', ['title' => 'Nova versão', 'asset' => $asset]);
    }
}
