<?php

namespace App\Libraries;

final class SiteAdmin
{
    public static function isAdmin(?string $id): bool
    {
        return $id !== null && $id !== '' && db_connect()->table('users')
            ->where(['id' => $id, 'is_active' => true, 'is_admin' => true])->countAllResults() === 1;
    }

    public static function current(): bool
    {
        return ! session('impersonator_id') && self::isAdmin(session('user_id'));
    }
}
