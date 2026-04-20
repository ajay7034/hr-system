<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\FileUploadService;
use App\Services\PassportDocumentSyncService;
use App\Services\PassportImportService;
use PDO;
use PDOException;

final class PassportController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger,
        private PassportDocumentSyncService $passportDocumentSyncService,
        private PassportImportService $passportImportService
    ) {
    }

    public function lists(Request $request): void
    {
        $status = $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $statement = $this->pdo->prepare("
            SELECT pr.*, e.full_name, e.employee_code, e.department_id, e.company_id, d.name AS department, c.name AS company
            FROM passport_records pr
            INNER JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN companies c ON c.id = e.company_id
            WHERE e.deleted_at IS NULL
              AND (:status = '' OR pr.current_status = :status)
              AND (
                :search = ''
                OR e.full_name LIKE :search_like
                OR e.employee_code LIKE :search_like
                OR pr.passport_number LIKE :search_like
              )
            ORDER BY pr.updated_at DESC
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
        $employeeId = (int) $params['employeeId'];
        $statement = $this->pdo->prepare("
            SELECT pmh.*, u.full_name AS updated_by_name
            FROM passport_movement_history pmh
            LEFT JOIN users u ON u.id = pmh.updated_by
            WHERE pmh.employee_id = :employee_id
            ORDER BY pmh.created_at DESC
        ");
        $statement->execute(['employee_id' => $employeeId]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function upsert(Request $request): void
    {
        $employeeId = (int) $request->input('employee_id');
        $movementType = trim((string) $request->input('movement_type', 'collected'));
        $toStatus = $movementType === 'given_back' ? 'outside' : 'in_hand';
        $fromStatus = $toStatus === 'in_hand' ? 'outside' : 'in_hand';
        $movementDate = $this->normalizeDate($request->input('movement_date')) ?? date('Y-m-d');
        $reason = $this->nullableString($request->input('reason'));
        $remarks = $this->nullableString($request->input('remarks'));
        $passportNumber = $this->nullableString($request->input('passport_number'));
        $issueDate = $this->normalizeDate($request->input('issue_date'));
        $expiryDate = $this->normalizeDate($request->input('expiry_date'));

        $validationMessage = $this->validateUpsertPayload($employeeId, $movementType, $movementDate, $passportNumber, $reason, $issueDate, $expiryDate);
        if ($validationMessage !== null) {
            Response::json(['success' => false, 'message' => $validationMessage], 422);
            return;
        }

        try {
            $passportFile = $this->uploadService->store($request->file('passport_file'), 'passports');
            $this->pdo->beginTransaction();
            $existingStatement = $this->pdo->prepare("SELECT * FROM passport_records WHERE employee_id = :employee_id LIMIT 1");
            $existingStatement->execute(['employee_id' => $employeeId]);
            $existing = $existingStatement->fetch();

            if ($existing) {
                $recordId = (int) $existing['id'];
                $fromStatus = $existing['current_status'];

                $statement = $this->pdo->prepare("
                    UPDATE passport_records SET
                        passport_number = :passport_number,
                        issue_date = :issue_date,
                        expiry_date = :expiry_date,
                        passport_file_path = COALESCE(:passport_file_path, passport_file_path),
                        current_status = :current_status,
                        collected_date = CASE WHEN :movement_type = 'collected' THEN :movement_date ELSE collected_date END,
                        withdrawn_date = CASE WHEN :movement_type = 'given_back' THEN :movement_date ELSE withdrawn_date END,
                        collected_reason = CASE WHEN :movement_type = 'collected' THEN :reason ELSE collected_reason END,
                        withdrawn_reason = CASE WHEN :movement_type = 'given_back' THEN :reason ELSE withdrawn_reason END,
                        remarks = :remarks,
                        last_updated_by = :last_updated_by
                    WHERE id = :id
                ");

                $statement->execute([
                    'passport_number' => $passportNumber,
                    'issue_date' => $issueDate,
                    'expiry_date' => $expiryDate,
                    'passport_file_path' => $passportFile,
                    'current_status' => $toStatus,
                    'movement_type' => $movementType,
                    'movement_date' => $movementDate,
                    'reason' => $reason,
                    'remarks' => $remarks,
                    'last_updated_by' => Auth::id(),
                    'id' => $recordId,
                ]);
            } else {
                $statement = $this->pdo->prepare("
                    INSERT INTO passport_records (
                        employee_id, passport_number, issue_date, expiry_date, passport_file_path, current_status,
                        collected_date, withdrawn_date, collected_reason, withdrawn_reason, remarks, last_updated_by
                    ) VALUES (
                        :employee_id, :passport_number, :issue_date, :expiry_date, :passport_file_path, :current_status,
                        :collected_date, :withdrawn_date, :collected_reason, :withdrawn_reason, :remarks, :last_updated_by
                    )
                ");

                $statement->execute([
                    'employee_id' => $employeeId,
                    'passport_number' => $passportNumber,
                    'issue_date' => $issueDate,
                    'expiry_date' => $expiryDate,
                    'passport_file_path' => $passportFile,
                    'current_status' => $toStatus,
                    'collected_date' => $movementType === 'collected' ? $movementDate : null,
                    'withdrawn_date' => $movementType === 'given_back' ? $movementDate : null,
                    'collected_reason' => $movementType === 'collected' ? $reason : null,
                    'withdrawn_reason' => $movementType === 'given_back' ? $reason : null,
                    'remarks' => $remarks,
                    'last_updated_by' => Auth::id(),
                ]);

                $recordId = (int) $this->pdo->lastInsertId();
            }

            $history = $this->pdo->prepare("
                INSERT INTO passport_movement_history (
                    passport_record_id, employee_id, movement_type, from_status, to_status, movement_date, reason, remarks, attachment_path, updated_by
                ) VALUES (
                    :passport_record_id, :employee_id, :movement_type, :from_status, :to_status, :movement_date, :reason, :remarks, :attachment_path, :updated_by
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
                'attachment_path' => $passportFile,
                'updated_by' => Auth::id(),
            ]);

            $this->passportDocumentSyncService->sync(
                $employeeId,
                $passportNumber,
                $issueDate,
                $expiryDate,
                $passportFile,
                $remarks,
                Auth::id()
            );

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json([
                'success' => false,
                'message' => $this->friendlyPassportError($exception),
            ], 422);
            return;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unable to update passport workflow.',
            ], 422);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'passport_record', $recordId, 'updated', 'Passport custody updated.', [
            'movement_type' => $movementType,
            'to_status' => $toStatus,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Passport workflow updated successfully.',
            'data' => ['id' => $recordId],
        ]);
    }

    public function import(Request $request): void
    {
        $summary = $this->passportImportService->import($request->file('import_file') ?? []);
        $this->activityLogger->log(Auth::id(), 'passport_import', null, 'imported', 'Passport bulk import completed.', $summary);

        Response::json([
            'success' => true,
            'message' => 'Passport import completed.',
            'data' => $summary,
        ]);
    }

    public function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        try {
            $this->pdo->beginTransaction();

            $recordStatement = $this->pdo->prepare("SELECT * FROM passport_records WHERE id = :id LIMIT 1");
            $recordStatement->execute(['id' => $id]);
            $record = $recordStatement->fetch();

            if (!$record) {
                $this->pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Passport custody record not found.'], 404);
                return;
            }

            $passportMaster = $this->pdo->query("
                SELECT id
                FROM employee_document_masters
                WHERE code = 'PASSPORT' OR LOWER(name) = 'passport'
                ORDER BY CASE WHEN code = 'PASSPORT' THEN 0 ELSE 1 END, id ASC
                LIMIT 1
            ")->fetch();

            if ($passportMaster) {
                $documentIdsStatement = $this->pdo->prepare("
                    SELECT id
                    FROM employee_documents
                    WHERE employee_id = :employee_id
                      AND document_master_id = :document_master_id
                      AND deleted_at IS NULL
                ");
                $documentIdsStatement->execute([
                    'employee_id' => (int) $record['employee_id'],
                    'document_master_id' => (int) $passportMaster['id'],
                ]);
                $documentIds = array_map(static fn (array $row) => (int) $row['id'], $documentIdsStatement->fetchAll());

                if ($documentIds) {
                    $documentCleanup = $this->pdo->prepare("
                        UPDATE employee_documents
                        SET deleted_at = NOW(), updated_by = :updated_by
                        WHERE id = :id
                    ");
                    $deleteDocNotifications = $this->pdo->prepare("
                        DELETE FROM notifications
                        WHERE related_table = 'employee_documents' AND related_id = :id
                    ");
                    $deleteDocEmails = $this->pdo->prepare("
                        DELETE FROM email_logs
                        WHERE related_table = 'employee_documents' AND related_id = :id
                    ");

                    foreach ($documentIds as $documentId) {
                        $documentCleanup->execute([
                            'id' => $documentId,
                            'updated_by' => Auth::id(),
                        ]);
                        $deleteDocNotifications->execute(['id' => $documentId]);
                        $deleteDocEmails->execute(['id' => $documentId]);
                    }
                }
            }

            $deleteNotifications = $this->pdo->prepare("DELETE FROM notifications WHERE related_table = 'passport_records' AND related_id = :id");
            $deleteNotifications->execute(['id' => $id]);

            $deleteHistory = $this->pdo->prepare("DELETE FROM passport_movement_history WHERE passport_record_id = :passport_record_id");
            $deleteHistory->execute(['passport_record_id' => $id]);

            $deleteRecord = $this->pdo->prepare("DELETE FROM passport_records WHERE id = :id");
            $deleteRecord->execute(['id' => $id]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unable to delete passport custody record.',
            ], 422);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'passport_record', $id, 'deleted', 'Passport custody record deleted.');

        Response::json([
            'success' => true,
            'message' => 'Passport custody record deleted.',
        ]);
    }

    private function validateUpsertPayload(
        int $employeeId,
        string $movementType,
        string|false|null $movementDate,
        ?string $passportNumber,
        ?string $reason,
        string|false|null $issueDate,
        string|false|null $expiryDate
    ): ?string {
        if ($employeeId <= 0) {
            return 'Select an employee before saving passport custody.';
        }

        $employeeStatement = $this->pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $employeeStatement->execute(['id' => $employeeId]);
        if (!$employeeStatement->fetch()) {
            return 'Selected employee was not found.';
        }

        if (!in_array($movementType, ['collected', 'given_back'], true)) {
            return 'Select a valid movement type.';
        }

        if ($movementDate === false || $movementDate === null) {
            return 'Enter a valid movement date.';
        }

        if ($passportNumber === null) {
            return 'Passport number is required.';
        }

        if ($reason === null) {
            return 'Reason is required.';
        }

        if ($issueDate === false || $expiryDate === false) {
            return 'Enter valid issue and expiry dates.';
        }

        return null;
    }

    private function friendlyPassportError(PDOException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'fk_passport_employee') => 'Selected employee was not found.',
            str_contains($message, 'Incorrect date value') => 'Enter a valid movement date before saving.',
            str_contains($message, "for key 'employee_id'") => 'Passport custody already exists for the selected employee.',
            default => 'Unable to save passport custody. Check the selected employee and dates, then try again.',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDate(mixed $value): string|false|null
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $normalized);
        return $date && $date->format('Y-m-d') === $normalized ? $normalized : false;
    }
}
