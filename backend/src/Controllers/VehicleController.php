<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\VehicleSchemaService;
use PDO;
use PDOException;

final class VehicleController
{
    public function __construct(
        private PDO $pdo,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $statement = $this->pdo->prepare("
            SELECT
                v.*,
                c.name AS company_name,
                e.full_name AS employee_name,
                e.employee_code,
                (
                    SELECT COUNT(*)
                    FROM vehicle_assignment_history vah
                    WHERE vah.vehicle_id = v.id
                ) AS history_count
            FROM vehicles v
            LEFT JOIN companies c ON c.id = v.company_id
            LEFT JOIN employees e ON e.id = v.current_employee_id AND e.deleted_at IS NULL
            WHERE v.deleted_at IS NULL
              AND (:status = '' OR v.status = :status)
              AND (
                :search = ''
                OR v.vehicle_name LIKE :search_like
                OR v.vehicle_number LIKE :search_like
                OR v.plate_number LIKE :search_like
                OR v.make_model LIKE :search_like
                OR c.name LIKE :search_like
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
              )
            ORDER BY v.updated_at DESC, v.id DESC
        ");
        $statement->execute([
            'status' => $status,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function history(Request $request, array $params): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $vehicleId = (int) $params['vehicleId'];
        $statement = $this->pdo->prepare("
            SELECT
                vah.*,
                e.full_name,
                e.employee_code,
                u.full_name AS updated_by_name
            FROM vehicle_assignment_history vah
            INNER JOIN employees e ON e.id = vah.employee_id
            LEFT JOIN users u ON u.id = vah.updated_by
            WHERE vah.vehicle_id = :vehicle_id
            ORDER BY vah.assigned_date DESC, vah.id DESC
        ");
        $statement->execute(['vehicle_id' => $vehicleId]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function store(Request $request): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $payload = $this->normalizePayload($request);
        $message = $this->validatePayload($payload);
        if ($message !== null) {
            Response::json(['success' => false, 'message' => $message], 422);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare("
                INSERT INTO vehicles (
                    company_id, vehicle_name, vehicle_number, plate_number, make_model, color,
                    current_employee_id, assigned_date, status, remarks, created_by, updated_by
                ) VALUES (
                    :company_id, :vehicle_name, :vehicle_number, :plate_number, :make_model, :color,
                    :current_employee_id, :assigned_date, :status, :remarks, :created_by, :updated_by
                )
            ");
            $statement->execute([
                'company_id' => $payload['company_id'],
                'vehicle_name' => $payload['vehicle_name'],
                'vehicle_number' => $payload['vehicle_number'],
                'plate_number' => $payload['plate_number'],
                'make_model' => $payload['make_model'],
                'color' => $payload['color'],
                'current_employee_id' => $payload['current_employee_id'],
                'assigned_date' => $payload['assigned_date'],
                'status' => $payload['status'],
                'remarks' => $payload['remarks'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $vehicleId = (int) $this->pdo->lastInsertId();
            $this->syncAssignmentHistory($vehicleId, null, null, $payload['current_employee_id'], $payload['assigned_date'], $payload['remarks']);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json(['success' => false, 'message' => $this->friendlyError($exception)], 422);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'vehicle', $vehicleId, 'created', 'Vehicle master created.');
        Response::json(['success' => true, 'message' => 'Vehicle saved.', 'data' => ['id' => $vehicleId]], 201);
    }

    public function update(Request $request, array $params): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $vehicleId = (int) $params['id'];
        $existing = $this->loadVehicle($vehicleId);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Vehicle not found.'], 404);
            return;
        }

        $payload = $this->normalizePayload($request);
        $message = $this->validatePayload($payload, $vehicleId);
        if ($message !== null) {
            Response::json(['success' => false, 'message' => $message], 422);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare("
                UPDATE vehicles SET
                    company_id = :company_id,
                    vehicle_name = :vehicle_name,
                    vehicle_number = :vehicle_number,
                    plate_number = :plate_number,
                    make_model = :make_model,
                    color = :color,
                    current_employee_id = :current_employee_id,
                    assigned_date = :assigned_date,
                    status = :status,
                    remarks = :remarks,
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $statement->execute([
                'company_id' => $payload['company_id'],
                'vehicle_name' => $payload['vehicle_name'],
                'vehicle_number' => $payload['vehicle_number'],
                'plate_number' => $payload['plate_number'],
                'make_model' => $payload['make_model'],
                'color' => $payload['color'],
                'current_employee_id' => $payload['current_employee_id'],
                'assigned_date' => $payload['assigned_date'],
                'status' => $payload['status'],
                'remarks' => $payload['remarks'],
                'updated_by' => Auth::id(),
                'id' => $vehicleId,
            ]);

            $this->syncAssignmentHistory(
                $vehicleId,
                $existing['current_employee_id'] ? (int) $existing['current_employee_id'] : null,
                $existing['assigned_date'] ?: null,
                $payload['current_employee_id'],
                $payload['assigned_date'],
                $payload['remarks']
            );

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json(['success' => false, 'message' => $this->friendlyError($exception)], 422);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'vehicle', $vehicleId, 'updated', 'Vehicle master updated.');
        Response::json(['success' => true, 'message' => 'Vehicle updated.']);
    }

    public function delete(Request $request, array $params): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $vehicleId = (int) $params['id'];

        $statement = $this->pdo->prepare("
            UPDATE vehicles
            SET deleted_at = NOW(), updated_by = :updated_by, current_employee_id = NULL
            WHERE id = :id AND deleted_at IS NULL
        ");
        $statement->execute([
            'id' => $vehicleId,
            'updated_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Vehicle not found.'], 404);
            return;
        }

        $closeHistory = $this->pdo->prepare("
            UPDATE vehicle_assignment_history
            SET released_date = COALESCE(released_date, CURDATE()), updated_by = :updated_by
            WHERE vehicle_id = :vehicle_id AND released_date IS NULL
        ");
        $closeHistory->execute([
            'vehicle_id' => $vehicleId,
            'updated_by' => Auth::id(),
        ]);

        $documentIdsStatement = $this->pdo->prepare("
            SELECT id FROM vehicle_documents WHERE vehicle_id = :vehicle_id AND deleted_at IS NULL
        ");
        $documentIdsStatement->execute(['vehicle_id' => $vehicleId]);
        $documentIds = array_map(static fn (array $row) => (int) $row['id'], $documentIdsStatement->fetchAll());

        if ($documentIds) {
            $documentDelete = $this->pdo->prepare("
                UPDATE vehicle_documents
                SET deleted_at = NOW(), updated_by = :updated_by
                WHERE id = :id
            ");
            $deleteNotifications = $this->pdo->prepare("
                DELETE FROM notifications
                WHERE related_table = 'vehicle_documents' AND related_id = :id
            ");
            $deleteEmails = $this->pdo->prepare("
                DELETE FROM email_logs
                WHERE related_table = 'vehicle_documents' AND related_id = :id
            ");

            foreach ($documentIds as $documentId) {
                $documentDelete->execute([
                    'id' => $documentId,
                    'updated_by' => Auth::id(),
                ]);
                $deleteNotifications->execute(['id' => $documentId]);
                $deleteEmails->execute(['id' => $documentId]);
            }
        }

        $this->activityLogger->log(Auth::id(), 'vehicle', $vehicleId, 'deleted', 'Vehicle master deleted.');
        Response::json(['success' => true, 'message' => 'Vehicle deleted.']);
    }

    private function normalizePayload(Request $request): array
    {
        $employeeId = $request->input('current_employee_id');
        $assignedDate = $this->normalizeDate($request->input('assigned_date'));

        if ($employeeId && !$assignedDate) {
            $assignedDate = date('Y-m-d');
        }

        return [
            'company_id' => $this->nullableInt($request->input('company_id')),
            'vehicle_name' => trim((string) $request->input('vehicle_name')),
            'vehicle_number' => trim((string) $request->input('vehicle_number')),
            'plate_number' => $this->nullableString($request->input('plate_number')),
            'make_model' => $this->nullableString($request->input('make_model')),
            'color' => $this->nullableString($request->input('color')),
            'current_employee_id' => $this->nullableInt($employeeId),
            'assigned_date' => $assignedDate,
            'status' => strtolower(trim((string) $request->input('status', 'active'))) ?: 'active',
            'remarks' => $this->nullableString($request->input('remarks')),
        ];
    }

    private function validatePayload(array $payload, ?int $vehicleId = null): ?string
    {
        if ($payload['vehicle_name'] === '') {
            return 'Vehicle name is required.';
        }

        if ($payload['vehicle_number'] === '') {
            return 'Vehicle number is required.';
        }

        if ($payload['current_employee_id'] && !$this->employeeExists($payload['current_employee_id'])) {
            return 'Selected employee was not found.';
        }

        if ($payload['company_id'] && !$this->companyExists($payload['company_id'])) {
            return 'Selected company was not found.';
        }

        if ($payload['assigned_date'] !== null && !$this->isValidDate($payload['assigned_date'])) {
            return 'Assigned date is invalid.';
        }

        $duplicateStatement = $this->pdo->prepare("
            SELECT id
            FROM vehicles
            WHERE vehicle_number = :vehicle_number
              AND deleted_at IS NULL
              AND (:id IS NULL OR id <> :id)
            LIMIT 1
        ");
        $duplicateStatement->execute([
            'vehicle_number' => $payload['vehicle_number'],
            'id' => $vehicleId,
        ]);

        if ($duplicateStatement->fetch()) {
            return 'Vehicle number already exists.';
        }

        return null;
    }

    private function syncAssignmentHistory(
        int $vehicleId,
        ?int $previousEmployeeId,
        ?string $previousAssignedDate,
        ?int $currentEmployeeId,
        ?string $currentAssignedDate,
        ?string $remarks
    ): void {
        $employeeChanged = $previousEmployeeId !== $currentEmployeeId;

        if ($employeeChanged && $previousEmployeeId) {
            $close = $this->pdo->prepare("
                UPDATE vehicle_assignment_history
                SET released_date = COALESCE(:released_date, CURDATE()), remarks = COALESCE(:remarks, remarks), updated_by = :updated_by
                WHERE vehicle_id = :vehicle_id
                  AND employee_id = :employee_id
                  AND released_date IS NULL
            ");
            $close->execute([
                'released_date' => $currentAssignedDate ?: date('Y-m-d'),
                'remarks' => $remarks,
                'updated_by' => Auth::id(),
                'vehicle_id' => $vehicleId,
                'employee_id' => $previousEmployeeId,
            ]);
        }

        if ($currentEmployeeId === null) {
            return;
        }

        if ($employeeChanged) {
            $insert = $this->pdo->prepare("
                INSERT INTO vehicle_assignment_history (
                    vehicle_id, employee_id, assigned_date, released_date, remarks, updated_by
                ) VALUES (
                    :vehicle_id, :employee_id, :assigned_date, NULL, :remarks, :updated_by
                )
            ");
            $insert->execute([
                'vehicle_id' => $vehicleId,
                'employee_id' => $currentEmployeeId,
                'assigned_date' => $currentAssignedDate ?: date('Y-m-d'),
                'remarks' => $remarks,
                'updated_by' => Auth::id(),
            ]);
            return;
        }

        $update = $this->pdo->prepare("
            UPDATE vehicle_assignment_history
            SET assigned_date = :assigned_date,
                remarks = :remarks,
                updated_by = :updated_by
            WHERE vehicle_id = :vehicle_id
              AND employee_id = :employee_id
              AND released_date IS NULL
        ");
        $update->execute([
            'assigned_date' => $currentAssignedDate ?: $previousAssignedDate ?: date('Y-m-d'),
            'remarks' => $remarks,
            'updated_by' => Auth::id(),
            'vehicle_id' => $vehicleId,
            'employee_id' => $currentEmployeeId,
        ]);

        if ($update->rowCount() === 0) {
            $insert = $this->pdo->prepare("
                INSERT INTO vehicle_assignment_history (
                    vehicle_id, employee_id, assigned_date, released_date, remarks, updated_by
                ) VALUES (
                    :vehicle_id, :employee_id, :assigned_date, NULL, :remarks, :updated_by
                )
            ");
            $insert->execute([
                'vehicle_id' => $vehicleId,
                'employee_id' => $currentEmployeeId,
                'assigned_date' => $currentAssignedDate ?: $previousAssignedDate ?: date('Y-m-d'),
                'remarks' => $remarks,
                'updated_by' => Auth::id(),
            ]);
        }
    }

    private function employeeExists(int $employeeId): bool
    {
        $statement = $this->pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute(['id' => $employeeId]);

        return (bool) $statement->fetchColumn();
    }

    private function companyExists(int $companyId): bool
    {
        $statement = $this->pdo->prepare("
            SELECT id
            FROM companies
            WHERE id = :id AND is_active = 1
            LIMIT 1
        ");
        $statement->execute(['id' => $companyId]);

        return (bool) $statement->fetchColumn();
    }

    private function loadVehicle(int $vehicleId): array|false
    {
        $statement = $this->pdo->prepare("
            SELECT *
            FROM vehicles
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute(['id' => $vehicleId]);

        return $statement->fetch();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime(str_replace('/', '-', $value));
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function isValidDate(string $value): bool
    {
        return strtotime($value) !== false;
    }

    private function friendlyError(PDOException $exception): string
    {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            return 'Vehicle number already exists.';
        }

        return 'Unable to save vehicle details.';
    }
}
