<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! self::hasActiveUser()) {
            if (session()->has('user_id')) {
                session()->destroy();
            }

            return redirect()->to('/login')->with('error', 'Entre para acessar o MOSAICO.');
        }
    }

    public static function hasActiveUser(): bool
    {
        $userId = session('user_id');

        return is_string($userId)
            && $userId !== ''
            && db_connect()->table('users')->where('id', $userId)->where('is_active', true)->countAllResults() === 1;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
