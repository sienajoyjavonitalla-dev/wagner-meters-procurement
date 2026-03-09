<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public function log(
        string $eventType,
        ?int $userId = null,
        ?string $entityType = null,
        string|int|null $entityId = null,
        ?array $context = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'context' => $context ?? [],
        ]);
    }
}
