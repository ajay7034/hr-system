<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\FileUploadService;
use PDO;

use function expiry_status;

final class CompanyDocumentController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->query('search', ''));

        $statement = $this->pdo->prepare("
            SELECT cd.*, c.name AS company_name, cdm.name AS document_type
            FROM company_documents cd
            LEFT JOIN companies c ON c.id = cd.company_id
            INNER JOIN company_document_masters cdm ON cdm.id = cd.document_master_id
            WHERE cd.deleted_at IS NULL
              AND (
                :search = ''
                OR cd.document_name LIKE :search_like
                OR cd.document_number LIKE :search_like
                OR cdm.name LIKE :search_like
                OR c.name LIKE :search_like
              )
            ORDER BY cd.expiry_date ASC
        ");
        $statement->execute([
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function store(Request $request): void
    {
        $filePath = $this->uploadService->store($request->file('document_file'), 'company-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            INSERT INTO company_documents (
                company_id, branch_id, document_master_id, document_name, document_number,
                issue_date, expiry_date, file_path, remarks, status, alert_days,
                mail_enabled, notification_enabled, created_by, updated_by
            ) VALUES (
                :company_id, :branch_id, :document_master_id, :document_name, :document_number,
                :issue_date, :expiry_date, :file_path, :remarks, :status, :alert_days,
                :mail_enabled, :notification_enabled, :created_by, :updated_by
            )
        ");
        $statement->execute([
            'company_id' => $request->input('company_id'),
            'branch_id' => null,
            'document_master_id' => $request->input('document_master_id'),
            'document_name' => $request->input('document_name'),
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
        $this->activityLogger->log(Auth::id(), 'company_document', $id, 'created', 'Company document added.');

        Response::json(['success' => true, 'message' => 'Company document saved.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $filePath = $this->uploadService->store($request->file('document_file'), 'company-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            UPDATE company_documents SET
                company_id = :company_id,
                branch_id = :branch_id,
                document_master_id = :document_master_id,
                document_name = :document_name,
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
            'company_id' => $request->input('company_id'),
            'branch_id' => null,
            'document_master_id' => $request->input('document_master_id'),
            'document_name' => $request->input('document_name'),
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

        $this->activityLogger->log(Auth::id(), 'company_document', $id, 'updated', 'Company document updated.');

        Response::json(['success' => true, 'message' => 'Company document updated.']);
    }

    public function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        $statement = $this->pdo->prepare("
            UPDATE company_documents
            SET deleted_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $statement->execute([
            'id' => $id,
            'updated_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Company document not found.'], 404);
            return;
        }

        $cleanup = $this->pdo->prepare("DELETE FROM notifications WHERE related_table = 'company_documents' AND related_id = :id");
        $cleanup->execute(['id' => $id]);

        $emailCleanup = $this->pdo->prepare("DELETE FROM email_logs WHERE related_table = 'company_documents' AND related_id = :id");
        $emailCleanup->execute(['id' => $id]);

        $this->activityLogger->log(Auth::id(), 'company_document', $id, 'deleted', 'Company document deleted.');

        Response::json(['success' => true, 'message' => 'Company document deleted.']);
    }
}
