<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class SettingsController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        $masterData = [
            'companies' => $this->pdo->query("SELECT * FROM companies WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'branches' => $this->pdo->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'departments' => $this->pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'designations' => $this->pdo->query("SELECT * FROM designations WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'employeeDocumentMasters' => $this->pdo->query("SELECT * FROM employee_document_masters ORDER BY sort_order, name")->fetchAll(),
            'companyDocumentMasters' => $this->pdo->query("SELECT * FROM company_document_masters ORDER BY sort_order, name")->fetchAll(),
            'roles' => $this->pdo->query("SELECT id, name, slug, description FROM roles ORDER BY name")->fetchAll(),
            'settings' => $this->pdo->query("SELECT category, setting_key, setting_value FROM settings ORDER BY category, setting_key")->fetchAll(),
        ];

        Response::json(['success' => true, 'data' => $masterData]);
    }

    public function saveSetting(Request $request): void
    {
        $statement = $this->pdo->prepare("
            INSERT INTO settings (category, setting_key, setting_value)
            VALUES (:category, :setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                setting_value = VALUES(setting_value)
        ");

        $statement->execute([
            'category' => $request->input('category'),
            'setting_key' => $request->input('setting_key'),
            'setting_value' => json_encode($request->input('setting_value', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        Response::json(['success' => true, 'message' => 'Setting saved successfully.']);
    }

    public function saveMaster(Request $request, array $params): void
    {
        $type = $params['type'] ?? '';
        $id = $request->input('id');

        $config = match ($type) {
            'companies' => [
                'table' => 'companies',
                'fields' => ['name', 'code', 'email', 'phone', 'address', 'website', 'is_active'],
            ],
            'branches' => [
                'table' => 'branches',
                'fields' => ['company_id', 'name', 'code', 'location', 'contact_email', 'is_active'],
            ],
            'departments' => [
                'table' => 'departments',
                'fields' => ['name', 'code', 'description', 'is_active'],
            ],
            'designations' => [
                'table' => 'designations',
                'fields' => ['name', 'code', 'description', 'is_active'],
            ],
            'employee-document-masters' => [
                'table' => 'employee_document_masters',
                'fields' => ['name', 'code', 'has_expiry', 'default_alert_days', 'default_mail_enabled', 'default_notification_enabled', 'sort_order'],
            ],
            'company-document-masters' => [
                'table' => 'company_document_masters',
                'fields' => ['name', 'code', 'has_expiry', 'default_alert_days', 'default_mail_enabled', 'default_notification_enabled', 'sort_order'],
            ],
            default => null,
        };

        if (!$config) {
            Response::json(['success' => false, 'message' => 'Unsupported master type.'], 422);
            return;
        }

        $fields = $config['fields'];
        $payload = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            if (in_array($field, ['is_active', 'has_expiry', 'default_mail_enabled', 'default_notification_enabled'], true)) {
                $value = (int) ($value ?? 0);
            }
            $payload[$field] = $value;
        }

        if ($id) {
            $assignments = implode(', ', array_map(static fn ($field) => "{$field} = :{$field}", $fields));
            $statement = $this->pdo->prepare("UPDATE {$config['table']} SET {$assignments} WHERE id = :id");
            $statement->execute($payload + ['id' => $id]);
        } else {
            $columns = implode(', ', $fields);
            $placeholders = implode(', ', array_map(static fn ($field) => ':' . $field, $fields));
            $statement = $this->pdo->prepare("INSERT INTO {$config['table']} ({$columns}) VALUES ({$placeholders})");
            $statement->execute($payload);
        }

        Response::json(['success' => true, 'message' => 'Master record saved successfully.']);
    }
}
