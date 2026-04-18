<?php

namespace App\Controllers;

use App\Core\Response;
use PDO;

final class LookupController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        $data = [
            'companies' => $this->pdo->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'departments' => $this->pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'designations' => $this->pdo->query("SELECT id, name FROM designations WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'employeeDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM employee_document_masters ORDER BY sort_order, name")->fetchAll(),
            'companyDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM company_document_masters ORDER BY sort_order, name")->fetchAll(),
            'employees' => $this->pdo->query("
                SELECT id, employee_code, full_name, passport_number, email
                FROM employees
                WHERE deleted_at IS NULL
                ORDER BY full_name
            ")->fetchAll(),
        ];

        Response::json(['success' => true, 'data' => $data]);
    }
}
