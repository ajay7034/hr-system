<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\FileUploadService;
use App\Services\VehicleSchemaService;
use PDO;

use function expiry_status;

final class VehicleDocumentController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $allowedStatuses = ['expiring_soon', 'expired', 'valid'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $statement = $this->pdo->prepare("
            SELECT
                vd.*,
                v.vehicle_name,
                v.vehicle_number,
                v.plate_number,
                e.full_name AS employee_name,
                vdm.name AS document_type
            FROM vehicle_documents vd
            INNER JOIN vehicles v ON v.id = vd.vehicle_id AND v.deleted_at IS NULL
            INNER JOIN vehicle_document_masters vdm ON vdm.id = vd.document_master_id
            LEFT JOIN employees e ON e.id = v.current_employee_id AND e.deleted_at IS NULL
            WHERE vd.deleted_at IS NULL
              AND (:status = '' OR vd.status = :status)
              AND (
                :search = ''
                OR vd.document_name LIKE :search_like
                OR vd.document_number LIKE :search_like
                OR vdm.name LIKE :search_like
                OR v.vehicle_name LIKE :search_like
                OR v.vehicle_number LIKE :search_like
                OR v.plate_number LIKE :search_like
                OR e.full_name LIKE :search_like
              )
            ORDER BY vd.expiry_date ASC, vd.id DESC
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
        VehicleSchemaService::ensureSchema($this->pdo);

        $filePath = $this->uploadService->store($request->file('document_file'), 'vehicle-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            INSERT INTO vehicle_documents (
                vehicle_id, document_master_id, document_name, document_number, issue_date, expiry_date,
                file_path, remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by
            ) VALUES (
                :vehicle_id, :document_master_id, :document_name, :document_number, :issue_date, :expiry_date,
                :file_path, :remarks, :status, :alert_days, :mail_enabled, :notification_enabled, :created_by, :updated_by
            )
        ");
        $statement->execute([
            'vehicle_id' => $request->input('vehicle_id'),
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
        $this->activityLogger->log(Auth::id(), 'vehicle_document', $id, 'created', 'Vehicle document added.');

        Response::json(['success' => true, 'message' => 'Vehicle document saved.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, array $params): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $filePath = $this->uploadService->store($request->file('document_file'), 'vehicle-documents');
        $alertDays = (int) $request->input('alert_days', 30);
        $status = expiry_status($request->input('expiry_date'), $alertDays);

        $statement = $this->pdo->prepare("
            UPDATE vehicle_documents SET
                vehicle_id = :vehicle_id,
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
            'vehicle_id' => $request->input('vehicle_id'),
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

        $this->activityLogger->log(Auth::id(), 'vehicle_document', $id, 'updated', 'Vehicle document updated.');
        Response::json(['success' => true, 'message' => 'Vehicle document updated.']);
    }

    public function delete(Request $request, array $params): void
    {
        VehicleSchemaService::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $statement = $this->pdo->prepare("
            UPDATE vehicle_documents
            SET deleted_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL
        ");
        $statement->execute([
            'id' => $id,
            'updated_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Vehicle document not found.'], 404);
            return;
        }

        $cleanup = $this->pdo->prepare("DELETE FROM notifications WHERE related_table = 'vehicle_documents' AND related_id = :id");
        $cleanup->execute(['id' => $id]);

        $emailCleanup = $this->pdo->prepare("DELETE FROM email_logs WHERE related_table = 'vehicle_documents' AND related_id = :id");
        $emailCleanup->execute(['id' => $id]);

        $this->activityLogger->log(Auth::id(), 'vehicle_document', $id, 'deleted', 'Vehicle document deleted.');
        Response::json(['success' => true, 'message' => 'Vehicle document deleted.']);
    }
}
