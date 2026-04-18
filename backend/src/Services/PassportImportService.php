<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

final class PassportImportService
{
    public function __construct(
        private PDO $pdo,
        private PassportDocumentSyncService $passportDocumentSyncService
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

        $headers = array_map(static fn ($header) => trim((string) $header), $headers);
        $summary = [
            'success_count' => 0,
            'failed_count' => 0,
            'failed_rows' => [],
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $payload = array_combine($headers, $row);

            if (!$payload || empty($payload['employee_code']) || empty($payload['passport_number'])) {
                $summary['failed_count']++;
                $summary['failed_rows'][] = ['row' => $row, 'reason' => 'Missing employee_code or passport_number'];
                continue;
            }

            try {
                $this->pdo->beginTransaction();
                $employeeStmt = $this->pdo->prepare("SELECT id FROM employees WHERE employee_code = :employee_code AND deleted_at IS NULL LIMIT 1");
                $employeeStmt->execute(['employee_code' => $payload['employee_code']]);
                $employee = $employeeStmt->fetch();

                if (!$employee) {
                    throw new RuntimeException('Employee not found for code ' . $payload['employee_code']);
                }

                $employeeId = (int) $employee['id'];
                $movementType = ($payload['movement_type'] ?? 'collected') === 'given_back' ? 'given_back' : 'collected';
                $toStatus = $movementType === 'given_back' ? 'outside' : 'in_hand';
                $movementDate = $payload['movement_date'] ?: date('Y-m-d');
                $reason = $payload['reason'] ?? null;
                $remarks = $payload['remarks'] ?? null;

                $recordStmt = $this->pdo->prepare("SELECT * FROM passport_records WHERE employee_id = :employee_id LIMIT 1");
                $recordStmt->execute(['employee_id' => $employeeId]);
                $existing = $recordStmt->fetch();

                if ($existing) {
                    $recordId = (int) $existing['id'];
                    $fromStatus = $existing['current_status'];

                    $update = $this->pdo->prepare("
                        UPDATE passport_records SET
                            passport_number = :passport_number,
                            issue_date = :issue_date,
                            expiry_date = :expiry_date,
                            current_status = :current_status,
                            collected_date = CASE WHEN :movement_type = 'collected' THEN :movement_date ELSE collected_date END,
                            withdrawn_date = CASE WHEN :movement_type = 'given_back' THEN :movement_date ELSE withdrawn_date END,
                            collected_reason = CASE WHEN :movement_type = 'collected' THEN :reason ELSE collected_reason END,
                            withdrawn_reason = CASE WHEN :movement_type = 'given_back' THEN :reason ELSE withdrawn_reason END,
                            remarks = :remarks,
                            last_updated_by = :updated_by
                        WHERE id = :id
                    ");
                    $update->execute([
                        'passport_number' => $payload['passport_number'],
                        'issue_date' => $payload['issue_date'] ?: null,
                        'expiry_date' => $payload['expiry_date'] ?: null,
                        'current_status' => $toStatus,
                        'movement_type' => $movementType,
                        'movement_date' => $movementDate,
                        'reason' => $reason,
                        'remarks' => $remarks,
                        'updated_by' => Auth::id(),
                        'id' => $recordId,
                    ]);
                } else {
                    $fromStatus = $movementType === 'given_back' ? 'in_hand' : 'outside';
                    $insert = $this->pdo->prepare("
                        INSERT INTO passport_records (
                            employee_id, passport_number, issue_date, expiry_date, current_status,
                            collected_date, withdrawn_date, collected_reason, withdrawn_reason, remarks, last_updated_by
                        ) VALUES (
                            :employee_id, :passport_number, :issue_date, :expiry_date, :current_status,
                            :collected_date, :withdrawn_date, :collected_reason, :withdrawn_reason, :remarks, :updated_by
                        )
                    ");
                    $insert->execute([
                        'employee_id' => $employeeId,
                        'passport_number' => $payload['passport_number'],
                        'issue_date' => $payload['issue_date'] ?: null,
                        'expiry_date' => $payload['expiry_date'] ?: null,
                        'current_status' => $toStatus,
                        'collected_date' => $movementType === 'collected' ? $movementDate : null,
                        'withdrawn_date' => $movementType === 'given_back' ? $movementDate : null,
                        'collected_reason' => $movementType === 'collected' ? $reason : null,
                        'withdrawn_reason' => $movementType === 'given_back' ? $reason : null,
                        'remarks' => $remarks,
                        'updated_by' => Auth::id(),
                    ]);
                    $recordId = (int) $this->pdo->lastInsertId();
                }

                $history = $this->pdo->prepare("
                    INSERT INTO passport_movement_history (
                        passport_record_id, employee_id, movement_type, from_status, to_status, movement_date, reason, remarks, updated_by
                    ) VALUES (
                        :passport_record_id, :employee_id, :movement_type, :from_status, :to_status, :movement_date, :reason, :remarks, :updated_by
                    )
                ");
                $history->execute([
                    'passport_record_id' => $recordId,
                    'employee_id' => $employeeId,
                    'movement_type' => $movementType,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'movement_date' => $movementDate,
                    'reason' => $reason,
                    'remarks' => $remarks,
                    'updated_by' => Auth::id(),
                ]);

                $this->passportDocumentSyncService->sync(
                    $employeeId,
                    $payload['passport_number'],
                    $payload['issue_date'] ?: null,
                    $payload['expiry_date'] ?: null,
                    null,
                    $remarks,
                    Auth::id()
                );

                $this->pdo->commit();

                $summary['success_count']++;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
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
}
