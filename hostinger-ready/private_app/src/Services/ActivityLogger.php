<?php

namespace App\Services;

use PDO;

final class ActivityLogger
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(?int $userId, string $entityType, ?int $entityId, string $action, ?string $description = null, array $meta = []): void
    {
        $statement = $this->pdo->prepare('
            INSERT INTO activity_logs (user_id, entity_type, entity_id, action, description, ip_address, user_agent, meta)
            VALUES (:user_id, :entity_type, :entity_id, :action, :description, :ip_address, :user_agent, :meta)
        ');

        $statement->execute([
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
