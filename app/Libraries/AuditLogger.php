<?php

namespace App\Libraries;

use App\Models\UuidModel;

final class AuditLogger
{
    public function record(string $action, string $entityType, ?string $entityId = null, array $details = []): void
    {
        db_connect()->table('audit_events')->insert([
            'id' => UuidModel::generateUuid(),
            'user_id' => session('user_id'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
