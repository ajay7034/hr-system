<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use PDOException;
use RuntimeException;

final class EmployeeImportService
{
    public function __construct(
        private PDO $pdo,
        private EmiratesIdDocumentSyncService $emiratesIdDocumentSyncService
    )
    {
    }

    public function import(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Import file upload failed.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new RuntimeException('Only CSV import is enabled in this scaffold.');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open import file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Import file is empty.');
        }

        $headers = array_map([$this, 'normalizeHeader'], $headers);
        $summary = [
            'success_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'failed_rows' => [],
        ];

        $lookup = [
            'company' => $this->mapLookup('companies'),
            'department' => $this->mapLookup('departments'),
            'designation' => $this->mapLookup('designations'),
        ];

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $payload = array_combine($headers, $row);

            if (
                !$payload
                || empty(trim((string) ($payload['employee_code'] ?? '')))
                || empty(trim((string) ($payload['full_name'] ?? '')))
            ) {
                $summary['failed_count']++;
                $summary['failed_rows'][] = ['row' => $row, 'reason' => 'Missing employee_code or full_name'];
                continue;
            }

            try {
                $existing = $this->findExistingEmployee($payload);
                $record = $this->buildEmployeeRecord($payload, $lookup, $existing);

                if ($existing) {
                    $this->updateEmployee((int) $existing['id'], $record);
                    $employeeId = (int) $existing['id'];
                    $summary['updated_count']++;
                } else {
                    $employeeId = $this->insertEmployee($record);
                    $summary['created_count']++;
                }

                $this->emiratesIdDocumentSyncService->sync($employeeId, $record['emirates_id'], Auth::id());

                $summary['success_count']++;
            } catch (PDOException $exception) {
                $summary['failed_count']++;
                $summary['failed_rows'][] = [
                    'row' => $row,
                    'reason' => $this->friendlyImportError($exception),
                ];
            } catch (\Throwable $exception) {
                $summary['failed_count']++;
                $summary['failed_rows'][] = [
                    'row' => $row,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        fclose($handle);

        return $summary;
    }

    private function findExistingEmployee(array $payload): ?array
    {
        $conditions = [];
        $bindings = [];

        $employeeId = $this->value($payload, 'employee_id');
        if ($employeeId !== null) {
            $conditions[] = 'employee_id = :employee_id';
            $bindings['employee_id'] = $employeeId;
        }

        $employeeCode = $this->value($payload, 'employee_code');
        if ($employeeCode !== null) {
            $conditions[] = 'employee_code = :employee_code';
            $bindings['employee_code'] = $employeeCode;
        }

        $passportNumber = $this->value($payload, 'passport_number');
        if ($passportNumber !== null) {
            $conditions[] = 'passport_number = :passport_number';
            $bindings['passport_number'] = $passportNumber;
        }

        if ($conditions === []) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM employees WHERE deleted_at IS NULL AND (' . implode(' OR ', $conditions) . ') ORDER BY id ASC LIMIT 1'
        );
        $statement->execute($bindings);

        return $statement->fetch() ?: null;
    }

    private function buildEmployeeRecord(array $payload, array $lookup, ?array $existing = null): array
    {
        $companyValue = $this->value($payload, 'company');
        $departmentValue = $this->value($payload, 'department');
        $designationValue = $this->value($payload, 'designation');

        $companyId = $companyValue !== null
            ? ($lookup['company'][$this->normalizeLookupKey($companyValue)] ?? ($existing['company_id'] ?? null))
            : ($existing['company_id'] ?? null);
        $departmentId = $departmentValue !== null
            ? ($lookup['department'][$this->normalizeLookupKey($departmentValue)] ?? ($existing['department_id'] ?? null))
            : ($existing['department_id'] ?? null);
        $designationId = $designationValue !== null
            ? ($lookup['designation'][$this->normalizeLookupKey($designationValue)] ?? ($existing['designation_id'] ?? null))
            : ($existing['designation_id'] ?? null);

        return [
            'employee_id' => $this->value($payload, 'employee_id')
                ?: ($existing['employee_id'] ?? strtoupper((string) $this->value($payload, 'employee_code'))),
            'employee_code' => $this->value($payload, 'employee_code') ?: ($existing['employee_code'] ?? null),
            'company_id' => $companyId,
            'branch_id' => null,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'full_name' => $this->value($payload, 'full_name') ?: ($existing['full_name'] ?? null),
            'email' => $this->value($payload, 'email') ?: ($existing['email'] ?? null),
            'mobile' => $this->value($payload, 'mobile') ?: ($existing['mobile'] ?? null),
            'joining_date' => $this->normalizeDate($this->value($payload, 'joining_date')) ?: ($existing['joining_date'] ?? null),
            'visa_status' => $this->value($payload, 'visa_status') ?: ($existing['visa_status'] ?? null),
            'emirates_id' => $this->value($payload, 'emirates_id') ?: ($existing['emirates_id'] ?? null),
            'passport_number' => $this->value($payload, 'passport_number') ?: ($existing['passport_number'] ?? null),
            'nationality' => $this->value($payload, 'nationality') ?: ($existing['nationality'] ?? null),
            'status' => strtolower((string) ($this->value($payload, 'status') ?: ($existing['status'] ?? 'active'))),
            'notes' => $this->value($payload, 'notes') ?: ($existing['notes'] ?? null),
            'created_by' => $existing['created_by'] ?? Auth::id(),
            'updated_by' => Auth::id(),
        ];
    }

    private function insertEmployee(array $record): int
    {
        $statement = $this->pdo->prepare("
            INSERT INTO employees (
                employee_id, employee_code, company_id, branch_id, department_id, designation_id,
                full_name, email, mobile, joining_date, visa_status, emirates_id,
                passport_number, nationality, status, notes, created_by, updated_by
            ) VALUES (
                :employee_id, :employee_code, :company_id, :branch_id, :department_id, :designation_id,
                :full_name, :email, :mobile, :joining_date, :visa_status, :emirates_id,
                :passport_number, :nationality, :status, :notes, :created_by, :updated_by
            )
        ");

        $statement->execute($record);
        return (int) $this->pdo->lastInsertId();
    }

    private function updateEmployee(int $id, array $record): void
    {
        unset($record['created_by']);

        $statement = $this->pdo->prepare("
            UPDATE employees SET
                employee_id = :employee_id,
                employee_code = :employee_code,
                company_id = :company_id,
                branch_id = :branch_id,
                department_id = :department_id,
                designation_id = :designation_id,
                full_name = :full_name,
                email = :email,
                mobile = :mobile,
                joining_date = :joining_date,
                visa_status = :visa_status,
                emirates_id = :emirates_id,
                passport_number = :passport_number,
                nationality = :nationality,
                status = :status,
                notes = :notes,
                updated_by = :updated_by
            WHERE id = :id
        ");

        $statement->execute($record + ['id' => $id]);
    }

    private function mapLookup(string $table): array
    {
        $rows = $this->pdo->query("SELECT id, name FROM {$table}")->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $map[$this->normalizeLookupKey($row['name'])] = (int) $row['id'];
        }

        return $map;
    }

    private function normalizeHeader(mixed $header): string
    {
        $normalized = trim((string) $header);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        return strtolower(str_replace(' ', '_', $normalized));
    }

    private function normalizeLookupKey(mixed $value): string
    {
        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        return strtolower($normalized);
    }

    private function value(array $payload, string $key): ?string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        return $value === '' ? null : $value;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        if (is_numeric($value)) {
            $serial = (int) $value;
            if ($serial > 0) {
                return gmdate('Y-m-d', ($serial - 25569) * 86400);
            }
        }

        return null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function friendlyImportError(PDOException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, "for key 'employee_id'") => 'Employee ID already exists.',
            str_contains($message, "for key 'employee_code'") => 'Employee code already exists.',
            str_contains($message, "for key 'passport_number'") => 'Passport number already exists.',
            default => 'Row could not be imported.',
        };
    }
}
