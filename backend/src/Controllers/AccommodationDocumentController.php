<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccommodationSchemaService;
use App\Services\ActivityLogger;
use App\Services\FileUploadService;
use PDO;

use function expiry_status;

final class AccommodationDocumentController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $allowedStatuses = ['expiring_soon', 'expired', 'valid'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $statement = $this->pdo->prepare("
            SELECT
                ad.*,
                a.accommodation_name,
                a.room_number,
                a.location,
                adm.name AS document_type
            FROM accommodation_documents ad
            INNER JOIN accommodations a ON a.id = ad.accommodation_id AND a.deleted_at IS NULL
            INNER JOIN accommodation_document_masters adm ON adm.id = ad.document_master_id
            WHERE ad.deleted_at IS NULL
              AND (:status = '' OR ad.status = :status)
              AND (
                :search = ''
                OR ad.document_name LIKE :search_like
                OR ad.document_number LIKE :search_like
                OR adm.name LIKE :search_like
                OR a.accommodation_name LIKE :search_like
                OR a.room_number LIKE :search_like
                OR a.location LIKE :search_like
              )
            ORDER BY ad.expiry_date ASC, ad.id DESC
        ");
        $statement->execute([
            'search' => $search,
            'status' => $status,
            'search_like' => '%' . $search . '%',
        ]);

        Response::json(['success' => true, 'data' => $statement->fetchAll()]);
    }

    public function store(Request $request): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $filePath = $this->uploadService->store($request->file('document_file'), 'accommodation-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            INSERT INTO accommodation_documents (
                accommodation_id, document_master_id, document_name, document_number, issue_date, expiry_date,
                file_path, remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by
            ) VALUES (
                :accommodation_id, :document_master_id, :document_name, :document_number, :issue_date, :expiry_date,
                :file_path, :remarks, :status, :alert_days, :mail_enabled, :notification_enabled, :created_by, :updated_by
            )
        ");
        $statement->execute([
            'accommodation_id' => $request->input('accommodation_id'),
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
        $this->activityLogger->log(Auth::id(), 'accommodation_document', $id, 'created', 'Accommodation document added.');

        Response::json(['success' => true, 'message' => 'Accommodation document saved.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, array $params): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $filePath = $this->uploadService->store($request->file('document_file'), 'accommodation-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            UPDATE accommodation_documents SET
                accommodation_id = :accommodation_id,
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
            'accommodation_id' => $request->input('accommodation_id'),
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

        $this->activityLogger->log(Auth::id(), 'accommodation_document', $id, 'updated', 'Accommodation document updated.');
        Response::json(['success' => true, 'message' => 'Accommodation document updated.']);
    }

    public function delete(Request $request, array $params): void
    {
        AccommodationSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $statement = $this->pdo->prepare("
            UPDATE accommodation_documents
            SET deleted_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $statement->execute([
            'id' => $id,
            'updated_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Accommodation document not found.'], 404);
            return;
        }

        $cleanup = $this->pdo->prepare("DELETE FROM notifications WHERE related_table = 'accommodation_documents' AND related_id = :id");
        $cleanup->execute(['id' => $id]);

        $emailCleanup = $this->pdo->prepare("DELETE FROM email_logs WHERE related_table = 'accommodation_documents' AND related_id = :id");
        $emailCleanup->execute(['id' => $id]);

        $this->activityLogger->log(Auth::id(), 'accommodation_document', $id, 'deleted', 'Accommodation document deleted.');
        Response::json(['success' => true, 'message' => 'Accommodation document deleted.']);
    }
}
