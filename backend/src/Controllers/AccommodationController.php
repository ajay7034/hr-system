<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccommodationSchemaService;
use App\Services\ActivityLogger;
use PDO;

final class AccommodationController
{
    public function __construct(
        private PDO $pdo,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $statement = $this->pdo->prepare("
            SELECT
                a.*,
                c.name AS company_name,
                e.full_name AS main_employee_name,
                e.employee_code AS main_employee_code
            FROM accommodations a
            LEFT JOIN companies c ON c.id = a.company_id
            LEFT JOIN employees e ON e.id = a.main_employee_id AND e.deleted_at IS NULL
            WHERE a.deleted_at IS NULL
              AND (:status = '' OR a.status = :status)
              AND (
                :search = ''
                OR a.accommodation_name LIKE :search_like
                OR a.location LIKE :search_like
                OR a.room_number LIKE :search_like
                OR c.name LIKE :search_like
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
              )
            ORDER BY a.updated_at DESC, a.id DESC
        ");
        $statement->execute([
            'status' => $status,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        $rows = $statement->fetchAll();
        $residentIds = [];

        foreach ($rows as $row) {
            foreach ($this->decodeResidentIds($row['resident_employee_ids'] ?? null) as $residentId) {
                $residentIds[$residentId] = $residentId;
            }
        }

        $residentLookup = $this->loadResidentLookup(array_values($residentIds));

        $data = array_map(function (array $row) use ($residentLookup): array {
            $residentIds = $this->decodeResidentIds($row['resident_employee_ids'] ?? null);
            $residents = array_values(array_filter(array_map(
                static fn (int $residentId) => $residentLookup[$residentId] ?? null,
                $residentIds
            )));

            $row['resident_ids'] = $residentIds;
            $row['residents'] = $residents;
            $row['resident_count'] = count($residents);

            return $row;
        }, $rows);

        Response::json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $payload = $this->normalizePayload($request);
        $message = $this->validatePayload($payload);
        if ($message !== null) {
            Response::json(['success' => false, 'message' => $message], 422);
            return;
        }

        $statement = $this->pdo->prepare("
            INSERT INTO accommodations (
                company_id, accommodation_name, location, room_number, main_employee_id,
                resident_employee_ids, status, remarks, created_by, updated_by
            ) VALUES (
                :company_id, :accommodation_name, :location, :room_number, :main_employee_id,
                :resident_employee_ids, :status, :remarks, :created_by, :updated_by
            )
        ");
        $statement->execute([
            'company_id' => $payload['company_id'],
            'accommodation_name' => $payload['accommodation_name'],
            'location' => $payload['location'],
            'room_number' => $payload['room_number'],
            'main_employee_id' => $payload['main_employee_id'],
            'resident_employee_ids' => json_encode($payload['resident_employee_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $payload['status'],
            'remarks' => $payload['remarks'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->activityLogger->log(Auth::id(), 'accommodation', $id, 'created', 'Accommodation room created.');

        Response::json(['success' => true, 'message' => 'Accommodation saved.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, array $params): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $statement = $this->pdo->prepare("
            SELECT id
            FROM accommodations
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute(['id' => $id]);
        if (!$statement->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Accommodation not found.'], 404);
            return;
        }

        $payload = $this->normalizePayload($request);
        $message = $this->validatePayload($payload);
        if ($message !== null) {
            Response::json(['success' => false, 'message' => $message], 422);
            return;
        }

        $update = $this->pdo->prepare("
            UPDATE accommodations SET
                company_id = :company_id,
                accommodation_name = :accommodation_name,
                location = :location,
                room_number = :room_number,
                main_employee_id = :main_employee_id,
                resident_employee_ids = :resident_employee_ids,
                status = :status,
                remarks = :remarks,
                updated_by = :updated_by
            WHERE id = :id
        ");
        $update->execute([
            'company_id' => $payload['company_id'],
            'accommodation_name' => $payload['accommodation_name'],
            'location' => $payload['location'],
            'room_number' => $payload['room_number'],
            'main_employee_id' => $payload['main_employee_id'],
            'resident_employee_ids' => json_encode($payload['resident_employee_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $payload['status'],
            'remarks' => $payload['remarks'],
            'updated_by' => Auth::id(),
            'id' => $id,
        ]);

        $this->activityLogger->log(Auth::id(), 'accommodation', $id, 'updated', 'Accommodation room updated.');

        Response::json(['success' => true, 'message' => 'Accommodation updated.']);
    }

    public function delete(Request $request, array $params): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $statement = $this->pdo->prepare("
            UPDATE accommodations
            SET deleted_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $statement->execute([
            'id' => $id,
            'updated_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Accommodation not found.'], 404);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'accommodation', $id, 'deleted', 'Accommodation room deleted.');
        Response::json(['success' => true, 'message' => 'Accommodation deleted.']);
    }

    private function normalizePayload(Request $request): array
    {
        $residentIds = array_values(array_unique(array_filter(array_map(
            static fn ($value) => (int) $value,
            (array) $request->input('resident_employee_ids', [])
        ))));
        $mainEmployeeId = $this->nullableInt($request->input('main_employee_id'));
        if ($mainEmployeeId && !in_array($mainEmployeeId, $residentIds, true)) {
            $residentIds[] = $mainEmployeeId;
        }

        return [
            'company_id' => $this->nullableInt($request->input('company_id')),
            'accommodation_name' => trim((string) $request->input('accommodation_name')),
            'location' => $this->nullableString($request->input('location')),
            'room_number' => trim((string) $request->input('room_number')),
            'main_employee_id' => $mainEmployeeId,
            'resident_employee_ids' => $residentIds,
            'status' => strtolower(trim((string) $request->input('status', 'active'))) ?: 'active',
            'remarks' => $this->nullableString($request->input('remarks')),
        ];
    }

    private function validatePayload(array $payload): ?string
    {
        if ($payload['accommodation_name'] === '') {
            return 'Accommodation name is required.';
        }

        if ($payload['room_number'] === '') {
            return 'Room number is required.';
        }

        if ($payload['company_id'] && !$this->companyExists($payload['company_id'])) {
            return 'Selected company was not found.';
        }

        if ($payload['main_employee_id'] && !$this->employeeExists($payload['main_employee_id'])) {
            return 'Selected main employee was not found.';
        }

        foreach ($payload['resident_employee_ids'] as $residentId) {
            if (!$this->employeeExists($residentId)) {
                return 'One or more resident members were not found.';
            }
        }

        return null;
    }

    private function loadResidentLookup(array $residentIds): array
    {
        if (!$residentIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($residentIds), '?'));
        $statement = $this->pdo->prepare("
            SELECT id, full_name, employee_code
            FROM employees
            WHERE deleted_at IS NULL AND id IN ($placeholders)
        ");
        $statement->execute($residentIds);

        $lookup = [];
        foreach ($statement->fetchAll() as $row) {
            $lookup[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'full_name' => $row['full_name'],
                'employee_code' => $row['employee_code'],
            ];
        }

        return $lookup;
    }

    private function decodeResidentIds(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($item) => (int) $item, $decoded))));
    }

    private function employeeExists(int $employeeId): bool
    {
        $statement = $this->pdo->prepare("SELECT id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $statement->execute(['id' => $employeeId]);
        return (bool) $statement->fetchColumn();
    }

    private function companyExists(int $companyId): bool
    {
        $statement = $this->pdo->prepare("SELECT id FROM companies WHERE id = :id AND is_active = 1 LIMIT 1");
        $statement->execute(['id' => $companyId]);
        return (bool) $statement->fetchColumn();
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
}
