<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReminderService;
use PDO;

final class NotificationController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        (new ReminderService($this->pdo))->generate();

        $statement = $this->pdo->prepare("
            SELECT id, user_id, notification_type, title, message, severity, is_read, created_at, related_table, related_id
            FROM notifications
            WHERE user_id IS NULL OR user_id = :user_id
            ORDER BY is_read ASC, created_at DESC
        ");
        $statement->execute(['user_id' => Auth::id()]);
        $notifications = $statement->fetchAll();

        Response::json(['success' => true, 'data' => $notifications]);
    }

    public function markRead(Request $request, array $params): void
    {
        $statement = $this->pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id
              AND (user_id IS NULL OR user_id = :user_id)
        ");
        $statement->execute([
            'id' => $params['id'],
            'user_id' => Auth::id(),
        ]);

        Response::json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(): void
    {
        $statement = $this->pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE is_read = 0
              AND (user_id IS NULL OR user_id = :user_id)
        ");
        $statement->execute(['user_id' => Auth::id()]);

        Response::json(['success' => true, 'message' => 'All notifications marked as read.']);
    }
}
