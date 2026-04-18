<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class NotificationController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        $notifications = $this->pdo->query("
            SELECT id, notification_type, title, message, severity, is_read, created_at, related_table, related_id
            FROM notifications
            ORDER BY is_read ASC, created_at DESC
        ")->fetchAll();

        Response::json(['success' => true, 'data' => $notifications]);
    }

    public function markRead(Request $request, array $params): void
    {
        $statement = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        $statement->execute(['id' => $params['id']]);

        Response::json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(): void
    {
        $this->pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        Response::json(['success' => true, 'message' => 'All notifications marked as read.']);
    }
}
