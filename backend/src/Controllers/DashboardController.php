<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AccommodationSchemaService;
use App\Services\ReminderService;
use App\Services\VehicleSchemaService;
use PDO;

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function summary(Request $request): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);
        VehicleSchemaService::ensureSchema($this->pdo);
        (new ReminderService($this->pdo))->generate();
        EmployeeRequestController::ensureSchema($this->pdo);
        EmployeeRequestController::syncNotifications($this->pdo);

        $counts = [
            'totalEmployees' => (int) $this->pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn(),
            'passportsInHand' => (int) $this->pdo->query("SELECT COUNT(*) FROM passport_records pr INNER JOIN employees e ON e.id = pr.employee_id WHERE e.deleted_at IS NULL AND pr.current_status = 'in_hand'")->fetchColumn(),
            'passportsOutside' => (int) $this->pdo->query("SELECT COUNT(*) FROM passport_records pr INNER JOIN employees e ON e.id = pr.employee_id WHERE e.deleted_at IS NULL AND pr.current_status = 'outside'")->fetchColumn(),
            'employeeDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE e.deleted_at IS NULL AND ed.status = 'expiring_soon' AND ed.deleted_at IS NULL")->fetchColumn(),
            'employeeDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE e.deleted_at IS NULL AND ed.status = 'expired' AND ed.deleted_at IS NULL")->fetchColumn(),
            'companyDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM company_documents WHERE status = 'expiring_soon' AND deleted_at IS NULL")->fetchColumn(),
            'companyDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM company_documents WHERE status = 'expired' AND deleted_at IS NULL")->fetchColumn(),
            'totalVehicles' => (int) $this->pdo->query("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL")->fetchColumn(),
            'totalAccommodations' => (int) $this->pdo->query("SELECT COUNT(*) FROM accommodations WHERE deleted_at IS NULL")->fetchColumn(),
            'accommodationDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM accommodation_documents ad INNER JOIN accommodations a ON a.id = ad.accommodation_id WHERE ad.status = 'expiring_soon' AND ad.deleted_at IS NULL AND a.deleted_at IS NULL")->fetchColumn(),
            'accommodationDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM accommodation_documents ad INNER JOIN accommodations a ON a.id = ad.accommodation_id WHERE ad.status = 'expired' AND ad.deleted_at IS NULL AND a.deleted_at IS NULL")->fetchColumn(),
            'vehicleDocsExpiring' => (int) $this->pdo->query("SELECT COUNT(*) FROM vehicle_documents vd INNER JOIN vehicles v ON v.id = vd.vehicle_id WHERE vd.status = 'expiring_soon' AND vd.deleted_at IS NULL AND v.deleted_at IS NULL")->fetchColumn(),
            'vehicleDocsExpired' => (int) $this->pdo->query("SELECT COUNT(*) FROM vehicle_documents vd INNER JOIN vehicles v ON v.id = vd.vehicle_id WHERE vd.status = 'expired' AND vd.deleted_at IS NULL AND v.deleted_at IS NULL")->fetchColumn(),
            'pendingRequests' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_requests WHERE status = 'pending'")->fetchColumn(),
            'approvedRequests' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_requests WHERE status = 'approved'")->fetchColumn(),
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
            SELECT statuses.status AS label, COUNT(*) AS value
            FROM (
                SELECT ed.status FROM employee_documents ed INNER JOIN employees e ON e.id = ed.employee_id WHERE ed.deleted_at IS NULL AND e.deleted_at IS NULL
                UNION ALL
                SELECT company_documents.status FROM company_documents WHERE deleted_at IS NULL
                UNION ALL
                SELECT ad.status FROM accommodation_documents ad INNER JOIN accommodations a ON a.id = ad.accommodation_id WHERE ad.deleted_at IS NULL AND a.deleted_at IS NULL
                UNION ALL
                SELECT vd.status FROM vehicle_documents vd INNER JOIN vehicles v ON v.id = vd.vehicle_id WHERE vd.deleted_at IS NULL AND v.deleted_at IS NULL
            ) statuses
            GROUP BY statuses.status
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
            UNION ALL
            SELECT 'accommodation' AS scope, adm.name AS document_type, CONCAT(a.accommodation_name, ' (room ', a.room_number, ')') AS subject, ad.expiry_date, ad.status
            FROM accommodation_documents ad
            INNER JOIN accommodations a ON a.id = ad.accommodation_id AND a.deleted_at IS NULL
            INNER JOIN accommodation_document_masters adm ON adm.id = ad.document_master_id
            WHERE ad.deleted_at IS NULL AND ad.expiry_date IS NOT NULL
            UNION ALL
            SELECT 'vehicle' AS scope, vdm.name AS document_type, CONCAT(v.vehicle_name, ' (', v.vehicle_number, ')') AS subject, vd.expiry_date, vd.status
            FROM vehicle_documents vd
            INNER JOIN vehicles v ON v.id = vd.vehicle_id AND v.deleted_at IS NULL
            INNER JOIN vehicle_document_masters vdm ON vdm.id = vd.document_master_id
            WHERE vd.deleted_at IS NULL AND vd.expiry_date IS NOT NULL
            ORDER BY expiry_date ASC
            LIMIT 10
        ")->fetchAll();

        $statement = $this->pdo->prepare("
            SELECT id, title, message, severity, is_read, created_at
            FROM notifications
            WHERE user_id IS NULL OR user_id = :user_id
            ORDER BY is_read ASC, created_at DESC
            LIMIT 8
        ");
        $statement->execute([
            'user_id' => \App\Core\Auth::id(),
        ]);
        $notifications = $statement->fetchAll();

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
