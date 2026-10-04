<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return session('user_id') ? redirect()->to('/painel') : redirect()->to('/login');
    }
}
