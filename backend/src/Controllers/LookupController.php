<?php

namespace App\Controllers;

use App\Core\Response;
use App\Services\AccommodationSchemaService;
use App\Services\VehicleSchemaService;
use PDO;

final class LookupController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);
        VehicleSchemaService::ensureSchema($this->pdo);

        $data = [
            'companies' => $this->pdo->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'departments' => $this->pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'designations' => $this->pdo->query("SELECT id, name FROM designations WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'employeeDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM employee_document_masters ORDER BY sort_order, name")->fetchAll(),
            'companyDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM company_document_masters ORDER BY sort_order, name")->fetchAll(),
            'accommodationDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM accommodation_document_masters ORDER BY sort_order, name")->fetchAll(),
            'vehicleDocumentMasters' => $this->pdo->query("SELECT id, name, default_alert_days FROM vehicle_document_masters ORDER BY sort_order, name")->fetchAll(),
            'employees' => $this->pdo->query("
                SELECT id, employee_code, full_name, passport_number, email
                FROM employees
                WHERE deleted_at IS NULL
                ORDER BY full_name
            ")->fetchAll(),
            'vehicles' => $this->pdo->query("
                SELECT
                    v.id,
                    v.vehicle_name,
                    v.vehicle_number,
                    v.plate_number,
                    e.full_name AS employee_name
                FROM vehicles v
                LEFT JOIN employees e ON e.id = v.current_employee_id AND e.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                ORDER BY v.vehicle_name, v.vehicle_number
            ")->fetchAll(),
            'accommodations' => $this->pdo->query("
                SELECT
                    a.id,
                    a.accommodation_name,
                    a.room_number,
                    a.location
                FROM accommodations a
                WHERE a.deleted_at IS NULL
                ORDER BY a.accommodation_name, a.room_number
            ")->fetchAll(),
        ];

        Response::json(['success' => true, 'data' => $data]);
    }
}
