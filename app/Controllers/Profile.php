<?php

namespace App\Controllers;

use App\Libraries\AuditLogger;

final class Profile extends BaseController
{
    public function index()
    {
        $user = db_connect()->table('users')->select('display_name, email')
            ->where('id', session('user_id'))->get()->getRowArray();

        $db = db_connect();
        $communities = $db->table('projects')->distinct()->select('projects.id, projects.name')
            ->join('memberships', 'memberships.project_id = projects.id')
            ->where('memberships.user_id', session('user_id'))
            ->where('projects.status !=', 'excluded')->orderBy('projects.name')->get()->getResultArray();
        $collections = [];
        if ($communities !== []) {
            $collections = $db->table('collections')->select('id, name, project_id')
                ->whereIn('project_id', array_column($communities, 'id'))
                ->where('deleted_at', null)->orderBy('name')->get()->getResultArray();
        }

        return view('profile/index', [
            'title' => 'Meu perfil', 'user' => $user,
            'communities' => $communities, 'collections' => $collections,
        ]);
    }

    public function password()
    {
        $current = (string) $this->request->getPost('current_password');
        $password = (string) $this->request->getPost('new_password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        $user = db_connect()->table('users')->where('id', session('user_id'))->get()->getRowArray();

        if (! password_verify($current, $user['password_hash'])) {
            usleep(250000);
            return redirect()->to('/profile')->with('error', 'A senha atual está incorreta.')->with('password_form_open', true);
        }
        if (mb_strlen($password) < 12 || strlen($password) > 72) {
            return redirect()->to('/profile')->with('error', 'A nova senha deve ter pelo menos 12 caracteres e no máximo 72 bytes.')->with('password_form_open', true);
        }
        if ($password !== $confirmation) {
            return redirect()->to('/profile')->with('error', 'A confirmação da nova senha não confere.')->with('password_form_open', true);
        }
        if (str_contains($password, "\0") || password_verify($password, $user['password_hash'])) {
            return redirect()->to('/profile')->with('error', 'Escolha uma nova senha válida e diferente da senha atual.')->with('password_form_open', true);
        }

        db_connect()->table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        session()->regenerate(true);
        (new AuditLogger())->record('auth.password_changed', 'user', $user['id']);

        return redirect()->to('/profile')->with('success', 'Senha alterada com sucesso.');
    }
}
