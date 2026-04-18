<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function summary(Request $request): void
    {
        $counts = [
            'totalEmployees' => (int) $this->pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn(),
            'passportsInHand' => (int) $this->pdo->query("SELECT COUNT(*) FROM passport_records pr INNER JOIN employees e ON e.id = pr.employee_id WHERE e.deleted_at IS NULL AND pr.current_status = 'in_hand'")->fetchColumn(),
            'passportsOutside' => (int) $this->pdo->query("SELECT COUNT(*) FROM passport_records pr INNER JOIN employees e ON e.id = pr.employee_id WHERE e.deleted_at IS NULL AND pr.current_status = 'outside'")->fetchColumn(),
            'employeeDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE e.deleted_at IS NULL AND ed.status = 'expiring_soon' AND ed.deleted_at IS NULL")->fetchColumn(),
            'employeeDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE e.deleted_at IS NULL AND ed.status = 'expired' AND ed.deleted_at IS NULL")->fetchColumn(),
            'companyDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM company_documents WHERE status = 'expiring_soon' AND deleted_at IS NULL")->fetchColumn(),
            'companyDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM company_documents WHERE status = 'expired' AND deleted_at IS NULL")->fetchColumn(),
            'mailQueueCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'queued'")->fetchColumn(),
        ];

        $passportStatus = $this->pdo->query("
            SELECT current_status AS label, COUNT(*) AS value
            FROM passport_records pr
            INNER JOIN employees e ON e.id = pr.employee_id
            WHERE e.deleted_at IS NULL
            GROUP BY current_status
        ")->fetchAll();

        $documentStatus = $this->pdo->query("
            SELECT status AS label, COUNT(*) AS value
            FROM (
                SELECT ed.status FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE ed.deleted_at IS NULL AND e.deleted_at IS NULL
                UNION ALL
                SELECT status FROM company_documents WHERE deleted_at IS NULL
            ) statuses
            GROUP BY status
        ")->fetchAll();

        $employeesByDepartment = $this->pdo->query("
            SELECT d.name AS label, COUNT(e.id) AS value
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.deleted_at IS NULL
            GROUP BY d.id
            ORDER BY value DESC, d.name ASC
        ")->fetchAll();

        $recentMovements = $this->pdo->query("
            SELECT pmh.id, pmh.movement_type, pmh.movement_date, pmh.reason, e.full_name, e.employee_code
            FROM passport_movement_history pmh
            INNER JOIN employees e ON e.id = pmh.employee_id AND e.deleted_at IS NULL
            ORDER BY pmh.created_at DESC
            LIMIT 6
        ")->fetchAll();

        $upcomingExpiries = $this->pdo->query("
            SELECT 'employee' AS scope, edm.name AS document_type, e.full_name AS subject, ed.expiry_date, ed.status
            FROM employee_documents ed
            INNER JOIN employees e ON e.id = ed.employee_id AND e.deleted_at IS NULL
            INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
            WHERE ed.deleted_at IS NULL AND ed.expiry_date IS NOT NULL
            UNION ALL
            SELECT 'company' AS scope, cdm.name AS document_type, cd.document_name AS subject, cd.expiry_date, cd.status
            FROM company_documents cd
            INNER JOIN company_document_masters cdm ON cdm.id = cd.document_master_id
            WHERE cd.deleted_at IS NULL AND cd.expiry_date IS NOT NULL
            ORDER BY expiry_date ASC
            LIMIT 10
        ")->fetchAll();

        $notifications = $this->pdo->query("
            SELECT id, title, message, severity, is_read, created_at
            FROM notifications
            ORDER BY is_read ASC, created_at DESC
            LIMIT 8
        ")->fetchAll();

        Response::json([
            'success' => true,
            'data' => [
                'counts' => $counts,
                'passportStatusChart' => $passportStatus,
                'documentStatusChart' => $documentStatus,
                'employeesByDepartmentChart' => $employeesByDepartment,
                'recentMovements' => $recentMovements,
                'upcomingExpiries' => $upcomingExpiries,
                'notifications' => $notifications,
            ],
        ]);
    }
}
