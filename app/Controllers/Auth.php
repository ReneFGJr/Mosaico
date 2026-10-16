<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Libraries\AuditLogger;

final class Auth extends BaseController
{
    public function login()
    {
        if (AuthFilter::hasActiveUser()) {
            return redirect()->to('/painel');
        }
        if (session()->has('user_id')) {
            session()->destroy();
        }

        if ($this->request->getMethod() === 'POST') {
            $email = mb_strtolower(trim((string) $this->request->getPost('email')));
            $user = db_connect()->table('users')->where('email', $email)->where('is_active', true)->get()->getRowArray();
            if ($user && password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
                session()->regenerate(true);
                session()->set(['user_id' => $user['id'], 'user_name' => $user['display_name']]);
                (new AuditLogger())->record('auth.login', 'user', $user['id']);
                return redirect()->to('/painel');
            }
            usleep(250000);
            return redirect()->back()->withInput()->with('error', 'E-mail ou senha inválidos.');
        }

        return view('auth/login', ['title' => 'Entrar']);
    }

    public function logout()
    {
        (new AuditLogger())->record('auth.logout', 'user', session('user_id'));
        session()->destroy();
        return redirect()->to('/login')->with('success', 'Sessão encerrada com segurança.');
    }
}
