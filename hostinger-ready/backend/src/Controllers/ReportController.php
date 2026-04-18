<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class ReportController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function passportCustody(Request $request): void
    {
        $status = $request->query('status', '');
        $employeeId = (int) $request->query('employee_id', 0);
        $search = trim((string) $request->query('search', ''));
        $companyId = (int) $request->query('company_id', 0);
        $branchId = (int) $request->query('branch_id', 0);
        $departmentId = (int) $request->query('department_id', 0);
        $employeeStatus = trim((string) $request->query('employee_status', ''));

        $statement = $this->pdo->prepare("
            SELECT
                pr.id,
                e.id AS employee_record_id,
                e.employee_id,
                e.employee_code,
                e.full_name,
                e.status AS employee_status,
                e.mobile,
                e.email,
                d.name AS department,
                dg.name AS designation,
                b.name AS branch,
                c.name AS company,
                pr.passport_number,
                pr.issue_date,
                pr.expiry_date,
                pr.current_status,
                pr.collected_date,
                pr.withdrawn_date,
                pr.collected_reason,
                pr.withdrawn_reason,
                pr.remarks
            FROM passport_records pr
            INNER JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN companies c ON c.id = e.company_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations dg ON dg.id = e.designation_id
            LEFT JOIN branches b ON b.id = e.branch_id
            WHERE e.deleted_at IS NULL
              AND (:status = '' OR pr.current_status = :status)
              AND (:employee_id = 0 OR e.id = :employee_id)
              AND (:company_id = 0 OR e.company_id = :company_id)
              AND (:branch_id = 0 OR e.branch_id = :branch_id)
              AND (:department_id = 0 OR e.department_id = :department_id)
              AND (:employee_status = '' OR e.status = :employee_status)
              AND (
                :search = ''
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
                OR e.employee_id LIKE :search_like
                OR pr.passport_number LIKE :search_like
              )
            ORDER BY e.full_name ASC
        ");
        $statement->execute([
            'status' => $status,
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'employee_status' => $employeeStatus,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function expiryReport(Request $request): void
    {
        $scope = $request->query('scope', 'all');
        $employeeId = (int) $request->query('employee_id', 0);
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $companyId = (int) $request->query('company_id', 0);
        $branchId = (int) $request->query('branch_id', 0);
        $departmentId = (int) $request->query('department_id', 0);
        $employeeDocumentMasterId = (int) $request->query('employee_document_master_id', 0);
        $companyDocumentMasterId = (int) $request->query('company_document_master_id', 0);
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $data = [];

        if ($scope === 'all' || $scope === 'employee') {
            $employeeStatement = $this->pdo->prepare("
                SELECT
                    ed.id,
                    e.id AS employee_record_id,
                    e.employee_id,
                    e.employee_code,
                    e.full_name,
                    e.status AS employee_status,
                    c.name AS company,
                    b.name AS branch,
                    d.name AS department,
                    edm.name AS document_type,
                    ed.document_number,
                    ed.issue_date,
                    ed.expiry_date,
                    ed.status,
                    ed.alert_days,
                    ed.mail_enabled,
                    ed.notification_enabled
                FROM employee_documents ed
                INNER JOIN employees e ON e.id = ed.employee_id
                LEFT JOIN companies c ON c.id = e.company_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN departments d ON d.id = e.department_id
                INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
                WHERE ed.deleted_at IS NULL
                  AND e.deleted_at IS NULL
                  AND (:employee_id = 0 OR e.id = :employee_id)
                  AND (:status = '' OR ed.status = :status)
                  AND (:company_id = 0 OR e.company_id = :company_id)
                  AND (:branch_id = 0 OR e.branch_id = :branch_id)
                  AND (:department_id = 0 OR e.department_id = :department_id)
                  AND (:employee_document_master_id = 0 OR ed.document_master_id = :employee_document_master_id)
                  AND (:date_from = '' OR ed.expiry_date >= :date_from)
                  AND (:date_to = '' OR ed.expiry_date <= :date_to)
                  AND (
                    :search = ''
                    OR e.full_name LIKE :search_like
                    OR e.employee_code LIKE :search_like
                    OR e.employee_id LIKE :search_like
                    OR edm.name LIKE :search_like
                    OR ed.document_number LIKE :search_like
                  )
                ORDER BY ed.expiry_date ASC
            ");
            $employeeStatement->execute([
                'status' => $status,
                'employee_id' => $employeeId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'employee_document_master_id' => $employeeDocumentMasterId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'search_like' => '%' . $search . '%',
            ]);
            $data['employee'] = $employeeStatement->fetchAll();
        }

        if ($scope === 'all' || $scope === 'company') {
            $companyStatement = $this->pdo->prepare("
                SELECT
                    cd.id,
                    cd.document_name,
                    cd.document_number,
                    cdm.name AS document_type,
                    c.name AS company,
                    b.name AS branch,
                    cd.issue_date,
                    cd.expiry_date,
                    cd.status,
                    cd.alert_days,
                    cd.mail_enabled,
                    cd.notification_enabled
                FROM company_documents cd
                LEFT JOIN companies c ON c.id = cd.company_id
                LEFT JOIN branches b ON b.id = cd.branch_id
                INNER JOIN company_document_masters cdm ON cdm.id = cd.document_master_id
                WHERE cd.deleted_at IS NULL
                  AND (:status = '' OR cd.status = :status)
                  AND (:company_id = 0 OR cd.company_id = :company_id)
                  AND (:branch_id = 0 OR cd.branch_id = :branch_id)
                  AND (:company_document_master_id = 0 OR cd.document_master_id = :company_document_master_id)
                  AND (:date_from = '' OR cd.expiry_date >= :date_from)
                  AND (:date_to = '' OR cd.expiry_date <= :date_to)
                  AND (
                    :search = ''
                    OR cd.document_name LIKE :search_like
                    OR cdm.name LIKE :search_like
                    OR cd.document_number LIKE :search_like
                  )
                ORDER BY cd.expiry_date ASC
            ");
            $companyStatement->execute([
                'status' => $status,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'company_document_master_id' => $companyDocumentMasterId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'search_like' => '%' . $search . '%',
            ]);
            $data['company'] = $companyStatement->fetchAll();
        }

        Response::json(['success' => true, 'data' => $data]);
    }

    public function passportMovements(Request $request): void
    {
        $employeeId = (int) $request->query('employee_id', 0);
        $search = trim((string) $request->query('search', ''));
        $companyId = (int) $request->query('company_id', 0);
        $branchId = (int) $request->query('branch_id', 0);
        $departmentId = (int) $request->query('department_id', 0);
        $movementType = trim((string) $request->query('movement_type', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $statement = $this->pdo->prepare("
            SELECT
                pmh.id,
                pmh.movement_type,
                pmh.from_status,
                pmh.to_status,
                pmh.movement_date,
                pmh.reason,
                pmh.remarks,
                e.id AS employee_record_id,
                e.employee_id,
                e.employee_code,
                e.full_name,
                c.name AS company,
                b.name AS branch,
                d.name AS department,
                pr.passport_number,
                u.full_name AS updated_by_name
            FROM passport_movement_history pmh
            INNER JOIN employees e ON e.id = pmh.employee_id
            LEFT JOIN passport_records pr ON pr.id = pmh.passport_record_id
            LEFT JOIN companies c ON c.id = e.company_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u ON u.id = pmh.updated_by
            WHERE e.deleted_at IS NULL
              AND (:employee_id = 0 OR e.id = :employee_id)
              AND (:company_id = 0 OR e.company_id = :company_id)
              AND (:branch_id = 0 OR e.branch_id = :branch_id)
              AND (:department_id = 0 OR e.department_id = :department_id)
              AND (:movement_type = '' OR pmh.movement_type = :movement_type)
              AND (:date_from = '' OR pmh.movement_date >= :date_from)
              AND (:date_to = '' OR pmh.movement_date <= :date_to)
              AND (
                :search = ''
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
                OR e.employee_id LIKE :search_like
                OR pr.passport_number LIKE :search_like
                OR pmh.reason LIKE :search_like
              )
            ORDER BY pmh.movement_date DESC, pmh.id DESC
        ");
        $statement->execute([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'movement_type' => $movementType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function employeeDocumentSummary(Request $request): void
    {
        $employeeId = (int) $request->query('employee_id', 0);
        $search = trim((string) $request->query('search', ''));
        $companyId = (int) $request->query('company_id', 0);
        $branchId = (int) $request->query('branch_id', 0);
        $departmentId = (int) $request->query('department_id', 0);
        $employeeStatus = trim((string) $request->query('employee_status', ''));

        $statement = $this->pdo->prepare("
            SELECT
                e.id AS employee_record_id,
                e.employee_id,
                e.employee_code,
                e.full_name,
                e.status AS employee_status,
                c.name AS company,
                b.name AS branch,
                d.name AS department,
                COUNT(ed.id) AS total_documents,
                SUM(CASE WHEN ed.status = 'valid' THEN 1 ELSE 0 END) AS valid_documents,
                SUM(CASE WHEN ed.status = 'expiring_soon' THEN 1 ELSE 0 END) AS expiring_documents,
                SUM(CASE WHEN ed.status = 'expired' THEN 1 ELSE 0 END) AS expired_documents,
                MIN(CASE WHEN ed.expiry_date IS NOT NULL THEN ed.expiry_date END) AS nearest_expiry_date
            FROM employees e
            LEFT JOIN companies c ON c.id = e.company_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employee_documents ed ON ed.employee_id = e.id AND ed.deleted_at IS NULL
            WHERE e.deleted_at IS NULL
              AND (:employee_id = 0 OR e.id = :employee_id)
              AND (:company_id = 0 OR e.company_id = :company_id)
              AND (:branch_id = 0 OR e.branch_id = :branch_id)
              AND (:department_id = 0 OR e.department_id = :department_id)
              AND (:employee_status = '' OR e.status = :employee_status)
              AND (
                :search = ''
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
                OR e.employee_id LIKE :search_like
              )
            GROUP BY e.id, e.employee_id, e.employee_code, e.full_name, e.status, c.name, b.name, d.name
            ORDER BY e.full_name ASC
        ");
        $statement->execute([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'employee_status' => $employeeStatus,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }
}
