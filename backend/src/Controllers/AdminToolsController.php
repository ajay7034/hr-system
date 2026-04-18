<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class AdminToolsController
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function activityLogs(Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $userId = (int) $request->query('user_id', 0);
        $entityType = trim((string) $request->query('entity_type', ''));
        $action = trim((string) $request->query('action', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $statement = $this->pdo->prepare("
            SELECT
                al.id,
                al.user_id,
                al.entity_type,
                al.entity_id,
                al.action,
                al.description,
                al.ip_address,
                al.user_agent,
                al.meta,
                al.created_at,
                u.full_name AS user_name,
                u.username
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE (:user_id = 0 OR al.user_id = :user_id)
              AND (:entity_type = '' OR al.entity_type = :entity_type)
              AND (:action = '' OR al.action = :action)
              AND (:date_from = '' OR DATE(al.created_at) >= :date_from)
              AND (:date_to = '' OR DATE(al.created_at) <= :date_to)
              AND (
                :search = ''
                OR al.entity_type LIKE :search_like
                OR al.action LIKE :search_like
                OR al.description LIKE :search_like
                OR al.ip_address LIKE :search_like
                OR u.full_name LIKE :search_like
                OR u.username LIKE :search_like
              )
            ORDER BY al.created_at DESC, al.id DESC
        ");
        $statement->execute([
            'user_id' => $userId,
            'entity_type' => $entityType,
            'action' => $action,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        $rows = $statement->fetchAll();
        $users = $this->pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll();
        $entityTypes = $this->pdo->query("SELECT DISTINCT entity_type FROM activity_logs ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);
        $actions = $this->pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

        Response::json([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'lookups' => [
                    'users' => $users,
                    'entityTypes' => $entityTypes,
                    'actions' => $actions,
                ],
            ],
        ]);
    }

    public function downloadDatabaseBackup(): void
    {
        $db = $this->config['db'];
        $binary = '/Applications/XAMPP/xamppfiles/bin/mysqldump';

        if (!is_file($binary)) {
            Response::json(['success' => false, 'message' => 'mysqldump binary not found.'], 500);
            return;
        }

        $args = [
            $binary,
            '--host',
            (string) $db['host'],
            '--port',
            (string) $db['port'],
            '--user',
            (string) $db['username'],
            '--single-transaction',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            (string) $db['database'],
        ];

        $command = implode(' ', array_map('escapeshellarg', $args));
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = $_ENV;

        if (($db['password'] ?? '') !== '') {
            $env['MYSQL_PWD'] = (string) $db['password'];
        }

        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            Response::json(['success' => false, 'message' => 'Unable to start backup process.'], 500);
            return;
        }

        $dump = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $dump === false || $dump === '') {
            Response::json([
                'success' => false,
                'message' => trim($error) !== '' ? trim($error) : 'Backup generation failed.',
            ], 500);
            return;
        }

        $safeName = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string) ($this->config['app']['name'] ?? 'hr-backup')));
        $filename = sprintf('%s-%s.sql', trim((string) $safeName, '-'), date('Y-m-d-His'));

        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($dump));
        echo $dump;
        exit;
    }
}
