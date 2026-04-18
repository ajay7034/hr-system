<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\FileUploadService;
use PDO;

use function expiry_status;

final class EmployeeDocumentController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        $statement = $this->pdo->query("
            SELECT ed.*, e.full_name, e.employee_code, edm.name AS document_type
            FROM employee_documents ed
            INNER JOIN employees e ON e.id = ed.employee_id
            INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
            WHERE ed.deleted_at IS NULL AND e.deleted_at IS NULL
            ORDER BY ed.expiry_date ASC
        ");

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function store(Request $request): void
    {
        $filePath = $this->uploadService->store($request->file('document_file'), 'employee-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            INSERT INTO employee_documents (
                employee_id, document_master_id, document_number, issue_date, expiry_date, file_path,
                remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by
            ) VALUES (
                :employee_id, :document_master_id, :document_number, :issue_date, :expiry_date, :file_path,
                :remarks, :status, :alert_days, :mail_enabled, :notification_enabled, :created_by, :updated_by
            )
        ");
        $statement->execute([
            'employee_id' => $request->input('employee_id'),
            'document_master_id' => $request->input('document_master_id'),
            'document_number' => $request->input('document_number'),
            'issue_date' => $request->input('issue_date'),
            'expiry_date' => $request->input('expiry_date'),
            'file_path' => $filePath,
            'remarks' => $request->input('remarks'),
            'status' => $status,
            'alert_days' => $alertDays,
            'mail_enabled' => (int) $request->input('mail_enabled', 1),
            'notification_enabled' => (int) $request->input('notification_enabled', 1),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->activityLogger->log(Auth::id(), 'employee_document', $id, 'created', 'Employee document added.');

        Response::json(['success' => true, 'message' => 'Employee document saved.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $filePath = $this->uploadService->store($request->file('document_file'), 'employee-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            UPDATE employee_documents SET
                employee_id = :employee_id,
                document_master_id = :document_master_id,
                document_number = :document_number,
                issue_date = :issue_date,
                expiry_date = :expiry_date,
                file_path = COALESCE(:file_path, file_path),
                remarks = :remarks,
                status = :status,
                alert_days = :alert_days,
                mail_enabled = :mail_enabled,
                notification_enabled = :notification_enabled,
                updated_by = :updated_by
            WHERE id = :id
        ");
        $statement->execute([
            'employee_id' => $request->input('employee_id'),
            'document_master_id' => $request->input('document_master_id'),
            'document_number' => $request->input('document_number'),
            'issue_date' => $request->input('issue_date'),
            'expiry_date' => $request->input('expiry_date'),
            'file_path' => $filePath,
            'remarks' => $request->input('remarks'),
            'status' => $status,
            'alert_days' => $alertDays,
            'mail_enabled' => (int) $request->input('mail_enabled', 1),
            'notification_enabled' => (int) $request->input('notification_enabled', 1),
            'updated_by' => Auth::id(),
            'id' => $id,
        ]);

        $this->activityLogger->log(Auth::id(), 'employee_document', $id, 'updated', 'Employee document updated.');

        Response::json(['success' => true, 'message' => 'Employee document updated.']);
    }
}
