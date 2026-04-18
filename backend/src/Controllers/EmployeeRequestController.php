<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use PDO;

final class EmployeeRequestController
{
    public function __construct(
        private PDO $pdo,
        private ActivityLogger $activityLogger
    ) {
    }

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                request_type VARCHAR(64) NOT NULL,
                request_title VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                summary TEXT NULL,
                details_json LONGTEXT NULL,
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee_requests_employee_id (employee_id),
                INDEX idx_employee_requests_status (status),
                INDEX idx_employee_requests_type (request_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function employeeSearch(Request $request): void
    {
        self::ensureSchema($this->pdo);

        $query = trim((string) $request->query('q', ''));
        $statement = $this->pdo->prepare("
            SELECT id, employee_code, full_name
            FROM employees
            WHERE deleted_at IS NULL
              AND status = 'active'
              AND (
                :query = ''
                OR full_name LIKE :query_like
                OR employee_code LIKE :query_like
              )
            ORDER BY full_name ASC
            LIMIT 8
        ");
        $statement->execute([
            'query' => $query,
            'query_like' => '%' . $query . '%',
        ]);

        Response::json([
            'success' => true,
            'data' => $statement->fetchAll(),
        ]);
    }

    public function submit(Request $request): void
    {
        self::ensureSchema($this->pdo);

        $employeeId = (int) $request->input('employee_id');
        $requestType = trim((string) $request->input('request_type'));

        $employeeStatement = $this->pdo->prepare("
            SELECT id, employee_code, full_name
            FROM employees
            WHERE id = :id AND deleted_at IS NULL AND status = 'active'
            LIMIT 1
        ");
        $employeeStatement->execute(['id' => $employeeId]);
        $employee = $employeeStatement->fetch();

        if (!$employee) {
            Response::json(['success' => false, 'message' => 'Employee not found.'], 422);
            return;
        }

        $payload = match ($requestType) {
            'leave' => $this->buildLeavePayload($request),
            'loan' => $this->buildLoanPayload($request),
            'salary_certificate' => $this->buildSalaryCertificatePayload($request),
            default => null,
        };

        if (!$payload) {
            Response::json(['success' => false, 'message' => 'Invalid request details.'], 422);
            return;
        }

        $statement = $this->pdo->prepare("
            INSERT INTO employee_requests (
                employee_id, request_type, request_title, status, summary, details_json
            ) VALUES (
                :employee_id, :request_type, :request_title, 'pending', :summary, :details_json
            )
        ");
        $statement->execute([
            'employee_id' => $employeeId,
            'request_type' => $requestType,
            'request_title' => $payload['title'],
            'summary' => $payload['summary'],
            'details_json' => json_encode($payload['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $requestId = (int) $this->pdo->lastInsertId();
        $this->activityLogger->log(
            null,
            'employee_request',
            $requestId,
            'created',
            'Employee service request submitted.',
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee['full_name'],
                'request_type' => $requestType,
            ]
        );

        Response::json([
            'success' => true,
            'message' => 'Request submitted successfully.',
            'data' => ['id' => $requestId],
        ], 201);
    }

    public function index(Request $request): void
    {
        self::ensureSchema($this->pdo);

        $status = trim((string) $request->query('status', 'pending'));
        $search = trim((string) $request->query('search', ''));

        $statement = $this->pdo->prepare("
            SELECT
                er.*,
                e.full_name,
                e.employee_code,
                approver.full_name AS approved_by_name
            FROM employee_requests er
            INNER JOIN employees e ON e.id = er.employee_id AND e.deleted_at IS NULL
            LEFT JOIN users approver ON approver.id = er.approved_by
            WHERE
                (:status = '' OR er.status = :status)
                AND (
                    :search = ''
                    OR e.full_name LIKE :search_like
                    OR e.employee_code LIKE :search_like
                    OR er.request_title LIKE :search_like
                    OR er.summary LIKE :search_like
                )
            ORDER BY
                CASE WHEN er.status = 'pending' THEN 0 ELSE 1 END,
                er.created_at DESC
        ");
        $statement->execute([
            'status' => $status,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        $summary = [
            'pending' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_requests WHERE status = 'pending'")->fetchColumn(),
            'approved' => (int) $this->pdo->query("SELECT COUNT(*) FROM employee_requests WHERE status = 'approved'")->fetchColumn(),
        ];

        Response::json([
            'success' => true,
            'data' => $statement->fetchAll(),
            'summary' => $summary,
        ]);
    }

    public function approve(Request $request, array $params): void
    {
        self::ensureSchema($this->pdo);

        $id = (int) $params['id'];
        $statement = $this->pdo->prepare("
            UPDATE employee_requests
            SET status = 'approved', approved_by = :approved_by, approved_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        $statement->execute([
            'id' => $id,
            'approved_by' => Auth::id(),
        ]);

        if ($statement->rowCount() === 0) {
            Response::json(['success' => false, 'message' => 'Pending request not found.'], 404);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'employee_request', $id, 'approved', 'Employee request approved.');

        Response::json([
            'success' => true,
            'message' => 'Request approved successfully.',
        ]);
    }

    private function buildLeavePayload(Request $request): ?array
    {
        $fromDate = trim((string) $request->input('from_date'));
        $toDate = trim((string) $request->input('to_date'));
        $fromDestination = trim((string) $request->input('from_destination'));
        $toDestination = trim((string) $request->input('to_destination'));
        $lineStaffEmployeeId = (int) $request->input('line_staff_employee_id');
        $lineStaffName = trim((string) $request->input('line_staff_name'));

        if ($fromDate === '' || $toDate === '' || $fromDestination === '' || $toDestination === '' || $lineStaffEmployeeId <= 0 || $lineStaffName === '') {
            return null;
        }

        return [
            'title' => 'Leave Request',
            'summary' => sprintf('%s to %s • %s to %s', $fromDate, $toDate, $fromDestination, $toDestination),
            'details' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'from_destination' => $fromDestination,
                'to_destination' => $toDestination,
                'line_staff_name' => $lineStaffName,
                'line_staff_employee_id' => $lineStaffEmployeeId,
            ],
        ];
    }

    private function buildLoanPayload(Request $request): ?array
    {
        $amount = trim((string) $request->input('amount'));
        $purpose = trim((string) $request->input('purpose'));

        if ($amount === '' || $purpose === '') {
            return null;
        }

        return [
            'title' => 'Loan Request',
            'summary' => sprintf('Amount: %s • %s', $amount, $purpose),
            'details' => [
                'amount' => $amount,
                'purpose' => $purpose,
            ],
        ];
    }

    private function buildSalaryCertificatePayload(Request $request): ?array
    {
        $purpose = trim((string) $request->input('purpose'));
        $language = trim((string) $request->input('language', 'English'));

        if ($purpose === '') {
            return null;
        }

        return [
            'title' => 'Salary Certificate Request',
            'summary' => sprintf('%s • %s', $language, $purpose),
            'details' => [
                'purpose' => $purpose,
                'language' => $language,
            ],
        ];
    }
}
