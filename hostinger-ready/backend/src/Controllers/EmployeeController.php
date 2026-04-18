<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\EmployeeImportService;
use App\Services\FileUploadService;
use PDO;

final class EmployeeController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger,
        private EmployeeImportService $employeeImportService
    ) {
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $sql = "
            SELECT e.id, e.employee_id, e.employee_code, e.full_name, e.email, e.mobile, e.joining_date, e.status, e.profile_photo_path,
                   d.name AS department, dg.name AS designation, b.name AS branch, c.name AS company,
                   pr.current_status AS passport_status
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations dg ON dg.id = e.designation_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN companies c ON c.id = e.company_id
            LEFT JOIN passport_records pr ON pr.employee_id = e.id
            WHERE e.deleted_at IS NULL
        ";

        $bindings = [];

        if ($search !== '') {
            $sql .= " AND (e.full_name LIKE :search OR e.employee_code LIKE :search OR e.passport_number LIKE :search OR e.email LIKE :search)";
            $bindings['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $sql .= " AND e.status = :status";
            $bindings['status'] = $status;
        }

        $sql .= " ORDER BY e.created_at DESC";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        Response::json([
            'success' => true,
            'data' => $statement->fetchAll(),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $employeeId = (int) $params['id'];

        $employee = $this->pdo->prepare("
            SELECT e.*, d.name AS department, dg.name AS designation, b.name AS branch, c.name AS company
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations dg ON dg.id = e.designation_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN companies c ON c.id = e.company_id
            WHERE e.id = :id AND e.deleted_at IS NULL
        ");
        $employee->execute(['id' => $employeeId]);
        $employeeRow = $employee->fetch();

        if (!$employeeRow) {
            Response::json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $passport = $this->pdo->prepare("SELECT * FROM passport_records WHERE employee_id = :id LIMIT 1");
        $passport->execute(['id' => $employeeId]);

        $documents = $this->pdo->prepare("
            SELECT ed.*, edm.name AS document_type
            FROM employee_documents ed
            INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
            WHERE ed.employee_id = :id AND ed.deleted_at IS NULL
            ORDER BY ed.expiry_date ASC
        ");
        $documents->execute(['id' => $employeeId]);

        $history = $this->pdo->prepare("
            SELECT pmh.*, u.full_name AS updated_by_name
            FROM passport_movement_history pmh
            LEFT JOIN users u ON u.id = pmh.updated_by
            WHERE pmh.employee_id = :id
            ORDER BY pmh.created_at DESC
        ");
        $history->execute(['id' => $employeeId]);

        Response::json([
            'success' => true,
            'data' => [
                'employee' => $employeeRow,
                'passport' => $passport->fetch(),
                'documents' => $documents->fetchAll(),
                'passportHistory' => $history->fetchAll(),
            ],
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->employeePayload($request);
        $profilePath = $this->uploadService->store($request->file('profile_photo'), 'employees');

        $statement = $this->pdo->prepare("
            INSERT INTO employees (
                employee_id, employee_code, company_id, branch_id, department_id, designation_id, full_name, first_name, last_name,
                email, mobile, joining_date, visa_status, emirates_id, passport_number, nationality, status, profile_photo_path,
                notes, created_by, updated_by
            ) VALUES (
                :employee_id, :employee_code, :company_id, :branch_id, :department_id, :designation_id, :full_name, :first_name, :last_name,
                :email, :mobile, :joining_date, :visa_status, :emirates_id, :passport_number, :nationality, :status, :profile_photo_path,
                :notes, :created_by, :updated_by
            )
        ");

        $statement->execute($data + [
            'profile_photo_path' => $profilePath,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->activityLogger->log(Auth::id(), 'employee', $id, 'created', 'Employee profile created.');

        Response::json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => ['id' => $id],
        ], 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $data = $this->employeePayload($request);
        $profilePath = $this->uploadService->store($request->file('profile_photo'), 'employees');

        $statement = $this->pdo->prepare("
            UPDATE employees SET
                employee_id = :employee_id,
                employee_code = :employee_code,
                company_id = :company_id,
                branch_id = :branch_id,
                department_id = :department_id,
                designation_id = :designation_id,
                full_name = :full_name,
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                mobile = :mobile,
                joining_date = :joining_date,
                visa_status = :visa_status,
                emirates_id = :emirates_id,
                passport_number = :passport_number,
                nationality = :nationality,
                status = :status,
                notes = :notes,
                updated_by = :updated_by,
                profile_photo_path = COALESCE(:profile_photo_path, profile_photo_path)
            WHERE id = :id
        ");

        $statement->execute($data + [
            'id' => $id,
            'updated_by' => Auth::id(),
            'profile_photo_path' => $profilePath,
        ]);

        $this->activityLogger->log(Auth::id(), 'employee', $id, 'updated', 'Employee profile updated.');

        Response::json([
            'success' => true,
            'message' => 'Employee updated successfully.',
        ]);
    }

    public function importTemplate(): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'headers' => [
                    'employee_id', 'employee_code', 'full_name', 'department', 'designation', 'branch',
                    'company', 'email', 'mobile', 'joining_date', 'visa_status', 'emirates_id',
                    'passport_number', 'nationality', 'status', 'notes',
                ],
            ],
        ]);
    }

    public function import(Request $request): void
    {
        $summary = $this->employeeImportService->import($request->file('import_file') ?? []);
        $this->activityLogger->log(Auth::id(), 'employee_import', null, 'imported', 'Employee bulk import completed.', $summary);

        Response::json([
            'success' => true,
            'message' => 'Employee import completed.',
            'data' => $summary,
        ]);
    }

    public function statusSummary(): void
    {
        $summary = $this->pdo->query("
            SELECT
                SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN e.status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN e.status = 'resigned' THEN 1 ELSE 0 END) AS resigned,
                SUM(CASE WHEN e.status = 'terminated' THEN 1 ELSE 0 END) AS terminated
            FROM employees e
            WHERE e.deleted_at IS NULL
        ")->fetch();

        Response::json(['success' => true, 'data' => $summary]);
    }

    public function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->pdo->beginTransaction();

        $statement = $this->pdo->prepare("DELETE FROM employees WHERE id = :id");
        $statement->execute(['id' => $id]);
        $this->pdo->commit();

        $this->activityLogger->log(Auth::id(), 'employee', $id, 'deleted', 'Employee and related records deleted.');

        Response::json([
            'success' => true,
            'message' => 'Employee and related records deleted successfully.',
        ]);
    }

    private function employeePayload(Request $request): array
    {
        return [
            'employee_id' => $request->input('employee_id'),
            'employee_code' => $request->input('employee_code'),
            'company_id' => $request->input('company_id'),
            'branch_id' => $request->input('branch_id'),
            'department_id' => $request->input('department_id'),
            'designation_id' => $request->input('designation_id'),
            'full_name' => $request->input('full_name'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'joining_date' => $request->input('joining_date'),
            'visa_status' => $request->input('visa_status'),
            'emirates_id' => $request->input('emirates_id'),
            'passport_number' => $request->input('passport_number'),
            'nationality' => $request->input('nationality'),
            'status' => $request->input('status', 'active'),
            'notes' => $request->input('notes'),
        ];
    }
}
