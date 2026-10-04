<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

class ProjectAccess
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function allows(string $userId, string $projectId, string $permissionCode): bool
    {
        $grant = $this->db->table('memberships')
            ->select('permissions.id')
            ->join('roles', 'roles.id = memberships.role_id')
            ->join('role_permissions', 'role_permissions.role_id = roles.id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('memberships.user_id', $userId)
            ->where('memberships.project_id', $projectId)
            ->where('permissions.code', $permissionCode)
            ->get()
            ->getRowArray();

        return $grant !== null;
    }
}