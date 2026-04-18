<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

final class EmployeeImportService
{
    public function __construct(private PDO $pdo)
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

        $headers = array_map(static fn ($header) => trim((string) $header), $headers);
        $summary = [
            'success_count' => 0,
            'failed_count' => 0,
            'failed_rows' => [],
        ];

        $lookup = [
            'company' => $this->mapLookup('companies'),
            'department' => $this->mapLookup('departments'),
            'designation' => $this->mapLookup('designations'),
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $payload = array_combine($headers, $row);

            if (!$payload || empty($payload['employee_code']) || empty($payload['full_name'])) {
                $summary['failed_count']++;
                $summary['failed_rows'][] = ['row' => $row, 'reason' => 'Missing employee_code or full_name'];
                continue;
            }

            try {
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

                $statement->execute([
                    'employee_id' => $payload['employee_id'] ?: strtoupper($payload['employee_code']),
                    'employee_code' => $payload['employee_code'],
                    'company_id' => $lookup['company'][strtolower((string) ($payload['company'] ?? ''))] ?? null,
                    'branch_id' => null,
                    'department_id' => $lookup['department'][strtolower((string) ($payload['department'] ?? ''))] ?? null,
                    'designation_id' => $lookup['designation'][strtolower((string) ($payload['designation'] ?? ''))] ?? null,
                    'full_name' => $payload['full_name'],
                    'email' => $payload['email'] ?: null,
                    'mobile' => $payload['mobile'] ?: null,
                    'joining_date' => $payload['joining_date'] ?: null,
                    'visa_status' => $payload['visa_status'] ?: null,
                    'emirates_id' => $payload['emirates_id'] ?: null,
                    'passport_number' => $payload['passport_number'] ?: null,
                    'nationality' => $payload['nationality'] ?: null,
                    'status' => $payload['status'] ?: 'active',
                    'notes' => $payload['notes'] ?: null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $summary['success_count']++;
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

    private function mapLookup(string $table): array
    {
        $rows = $this->pdo->query("SELECT id, name FROM {$table}")->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $map[strtolower($row['name'])] = (int) $row['id'];
        }

        return $map;
    }
}
