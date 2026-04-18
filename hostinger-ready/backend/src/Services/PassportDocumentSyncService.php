<?php

namespace App\Services;

use PDO;

use function expiry_status;

final class PassportDocumentSyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function sync(
        int $employeeId,
        ?string $passportNumber,
        ?string $issueDate,
        ?string $expiryDate,
        ?string $filePath,
        ?string $remarks,
        ?int $userId
    ): int {
        $master = $this->ensurePassportMaster();
        $documentStatement = $this->pdo->prepare("
            SELECT *
            FROM employee_documents
            WHERE employee_id = :employee_id
              AND document_master_id = :document_master_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $documentStatement->execute([
            'employee_id' => $employeeId,
            'document_master_id' => (int) $master['id'],
        ]);
        $existing = $documentStatement->fetch();

        if ($existing) {
            $alertDays = (int) ($existing['alert_days'] ?: $master['default_alert_days']);
            $status = expiry_status($expiryDate, $alertDays);

            $update = $this->pdo->prepare("
                UPDATE employee_documents SET
                    document_number = :document_number,
                    issue_date = :issue_date,
                    expiry_date = :expiry_date,
                    file_path = COALESCE(:file_path, file_path),
                    remarks = :remarks,
                    status = :status,
                    alert_days = :alert_days,
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $update->execute([
                'document_number' => $passportNumber,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'file_path' => $filePath,
                'remarks' => $remarks ?: ($existing['remarks'] ?? null),
                'status' => $status,
                'alert_days' => $alertDays,
                'updated_by' => $userId,
                'id' => (int) $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $alertDays = (int) $master['default_alert_days'];
        $status = expiry_status($expiryDate, $alertDays);

        $insert = $this->pdo->prepare("
            INSERT INTO employee_documents (
                employee_id, document_master_id, document_number, issue_date, expiry_date, file_path,
                remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by
            ) VALUES (
                :employee_id, :document_master_id, :document_number, :issue_date, :expiry_date, :file_path,
                :remarks, :status, :alert_days, :mail_enabled, :notification_enabled, :created_by, :updated_by
            )
        ");
        $insert->execute([
            'employee_id' => $employeeId,
            'document_master_id' => (int) $master['id'],
            'document_number' => $passportNumber,
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'file_path' => $filePath,
            'remarks' => $remarks,
            'status' => $status,
            'alert_days' => $alertDays,
            'mail_enabled' => (int) $master['default_mail_enabled'],
            'notification_enabled' => (int) $master['default_notification_enabled'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensurePassportMaster(): array
    {
        $statement = $this->pdo->query("
            SELECT *
            FROM employee_document_masters
            WHERE code = 'PASSPORT' OR LOWER(name) = 'passport'
            ORDER BY CASE WHEN code = 'PASSPORT' THEN 0 ELSE 1 END, id ASC
            LIMIT 1
        ");
        $master = $statement->fetch();

        if ($master) {
            return $master;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO employee_document_masters (
                name, code, has_expiry, default_alert_days, default_mail_enabled, default_notification_enabled, sort_order
            ) VALUES (
                'Passport', 'PASSPORT', 1, 90, 1, 1, 1
            )
        ");
        $insert->execute();

        $id = (int) $this->pdo->lastInsertId();
        $reload = $this->pdo->prepare("SELECT * FROM employee_document_masters WHERE id = :id LIMIT 1");
        $reload->execute(['id' => $id]);

        return $reload->fetch();
    }
}
