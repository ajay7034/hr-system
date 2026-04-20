<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\EmployeeImportService;
use App\Services\FileUploadService;
use PDO;
use PDOException;

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
                   d.name AS department, dg.name AS designation, c.name AS company,
                   pr.current_status AS passport_status
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations dg ON dg.id = e.designation_id
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
            SELECT e.*, d.name AS department, dg.name AS designation, c.name AS company, COALESCE(b.name, c.name) AS branch
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations dg ON dg.id = e.designation_id
            LEFT JOIN companies c ON c.id = e.company_id
            LEFT JOIN branches b ON b.id = e.branch_id
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
        $validationMessage = $this->validateEmployeePayload($data);
        if ($validationMessage !== null) {
            Response::json(['success' => false, 'message' => $validationMessage], 422);
            return;
        }

        try {
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
        } catch (PDOException $exception) {
            Response::json([
                'success' => false,
                'message' => $this->friendlyEmployeeError($exception),
            ], 422);
            return;
        }

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
        $validationMessage = $this->validateEmployeePayload($data, $id);
        if ($validationMessage !== null) {
            Response::json(['success' => false, 'message' => $validationMessage], 422);
            return;
        }

        try {
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
        } catch (PDOException $exception) {
            Response::json([
                'success' => false,
                'message' => $this->friendlyEmployeeError($exception),
            ], 422);
            return;
        }

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
                    'employee_id', 'employee_code', 'full_name', 'department', 'designation',
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
            'employee_id' => trim((string) $request->input('employee_id')),
            'employee_code' => trim((string) $request->input('employee_code')),
            'company_id' => $this->nullableInt($request->input('company_id')),
            'branch_id' => null,
            'department_id' => $this->nullableInt($request->input('department_id')),
            'designation_id' => $this->nullableInt($request->input('designation_id')),
            'full_name' => trim((string) $request->input('full_name')),
            'first_name' => $this->nullableString($request->input('first_name')),
            'last_name' => $this->nullableString($request->input('last_name')),
            'email' => $this->nullableString($request->input('email')),
            'mobile' => $this->nullableString($request->input('mobile')),
            'joining_date' => $this->nullableDate($request->input('joining_date')),
            'visa_status' => $this->nullableString($request->input('visa_status')),
            'emirates_id' => $this->nullableString($request->input('emirates_id')),
            'passport_number' => $this->nullableString($request->input('passport_number')),
            'nationality' => $this->nullableString($request->input('nationality')),
            'status' => trim((string) $request->input('status', 'active')),
            'notes' => $this->nullableString($request->input('notes')),
        ];
    }

    private function validateEmployeePayload(array $data, ?int $ignoreId = null): ?string
    {
        if ($data['employee_id'] === '') {
            return 'Employee ID is required.';
        }

        if ($data['employee_code'] === '') {
            return 'Employee code is required.';
        }

        if ($data['full_name'] === '') {
            return 'Full name is required.';
        }

        if (!in_array($data['status'], ['active', 'inactive', 'resigned', 'terminated'], true)) {
            return 'Selected employee status is invalid.';
        }

        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }

        $duplicates = [
            'employee_id' => 'Employee ID already exists.',
            'employee_code' => 'Employee code already exists.',
            'passport_number' => 'Passport number already exists.',
        ];

        foreach ($duplicates as $field => $message) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $statement = $this->pdo->prepare("
                SELECT id
                FROM employees
                WHERE {$field} = :value
                  AND (:ignore_id IS NULL OR id <> :ignore_id)
                LIMIT 1
            ");
            $statement->execute([
                'value' => $value,
                'ignore_id' => $ignoreId,
            ]);

            if ($statement->fetch()) {
                return $message;
            }
        }

        return null;
    }

    private function friendlyEmployeeError(PDOException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, "for key 'employee_id'") => 'Employee ID already exists.',
            str_contains($message, "for key 'employee_code'") => 'Employee code already exists.',
            str_contains($message, "for key 'passport_number'") => 'Passport number already exists.',
            default => 'Unable to save employee. Check the entered values and try again.',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : (int) $normalized;
    }

    private function nullableDate(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $normalized);
        return $date && $date->format('Y-m-d') === $normalized ? $normalized : null;
    }
}
