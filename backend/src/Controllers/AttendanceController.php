<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\AttendanceSchemaService;
use App\Services\BioTimeService;
use PDO;
use RuntimeException;

final class AttendanceController
{
    public function __construct(
        private PDO $pdo,
        private ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): void
    {
        AttendanceSchemaService::ensureSchema($this->pdo);

        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $employeeStatement = $this->pdo->prepare("
            SELECT *
            FROM attendance_biotime_employees
            WHERE (
                :search = ''
                OR employee_code LIKE :search_like
                OR full_name LIKE :search_like
                OR department_name LIKE :search_like
                OR position_name LIKE :search_like
            )
            ORDER BY full_name ASC, employee_code ASC
        ");
        $employeeStatement->execute([
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        $attendanceStatement = $this->pdo->prepare("
            SELECT *
            FROM attendance_logs
            WHERE (:date_from = '' OR DATE(punch_time) >= :date_from)
              AND (:date_to = '' OR DATE(punch_time) <= :date_to)
              AND (
                :search = ''
                OR employee_code LIKE :search_like
                OR employee_name LIKE :search_like
                OR terminal_alias LIKE :search_like
                OR area_alias LIKE :search_like
              )
            ORDER BY punch_time DESC, id DESC
            LIMIT 500
        ");
        $attendanceStatement->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search,
            'search_like' => '%' . $search . '%',
        ]);

        $counts = [
            'employees' => (int) $this->pdo->query("SELECT COUNT(*) FROM attendance_biotime_employees")->fetchColumn(),
            'logs' => (int) $this->pdo->query("SELECT COUNT(*) FROM attendance_logs")->fetchColumn(),
            'todayLogs' => (int) $this->pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(punch_time) = CURDATE()")->fetchColumn(),
        ];

        Response::json([
            'success' => true,
            'data' => [
                'employees' => $employeeStatement->fetchAll(),
                'logs' => $attendanceStatement->fetchAll(),
                'counts' => $counts,
                'config' => $this->loadConfig(),
            ],
        ]);
    }

    public function saveConfig(Request $request): void
    {
        AttendanceSchemaService::ensureSchema($this->pdo);

        $config = [
            'base_url' => trim((string) $request->input('base_url', '')),
            'username' => trim((string) $request->input('username', '')),
            'password' => trim((string) $request->input('password', '')),
            'token_path' => trim((string) $request->input('token_path', '/jwt-api-token-auth/')),
            'employees_path' => trim((string) $request->input('employees_path', '/personnel/api/employees/')),
            'attendance_path' => trim((string) $request->input('attendance_path', '/iclock/api/transactions/')),
            'page_size' => max(1, (int) $request->input('page_size', 100)),
        ];

        $statement = $this->pdo->prepare("
            INSERT INTO settings (category, setting_key, setting_value)
            VALUES ('attendance', 'biotime_config', :setting_value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $statement->execute([
            'setting_value' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $this->activityLogger->log(Auth::id(), 'attendance_config', null, 'updated', 'BioTime attendance configuration updated.');
        Response::json(['success' => true, 'message' => 'Attendance configuration saved.']);
    }

    public function sync(Request $request): void
    {
        AttendanceSchemaService::ensureSchema($this->pdo);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        $config = $this->loadConfig();
        if (empty($config['base_url']) || empty($config['username']) || empty($config['password'])) {
            Response::json(['success' => false, 'message' => 'Configure the BioTime API credentials first.'], 422);
            return;
        }

        $type = trim((string) $request->input('type', 'all'));
        $rawDateFrom = trim((string) $request->input('date_from', ''));
        $rawDateTo = trim((string) $request->input('date_to', ''));
        $dateFrom = $this->normalizeBoundaryDateTime($request->input('date_from', ''), false);
        $dateTo = $this->normalizeBoundaryDateTime($request->input('date_to', ''), true);

        if (($type === 'all' || $type === 'attendance') && !$dateFrom && !$dateTo) {
            $latestPunch = $this->pdo->query("SELECT MAX(punch_time) FROM attendance_logs")->fetchColumn();
            if ($latestPunch) {
                $timestamp = strtotime((string) $latestPunch);
                $dateFrom = $timestamp !== false
                    ? date('Y-m-d H:i:s', max(0, $timestamp - 300))
                    : date('Y-m-d 00:00:00');
            } else {
                $dateFrom = date('Y-m-d 00:00:00');
            }
            $dateTo = null;
        } elseif ($rawDateFrom !== '' && $rawDateTo === '' && $dateFrom) {
            $dateTo = $this->normalizeBoundaryDateTime($rawDateFrom, true);
        } elseif ($rawDateFrom === '' && $rawDateTo !== '' && $dateTo) {
            $dateFrom = $this->normalizeBoundaryDateTime($rawDateTo, false);
        }

        $service = new BioTimeService($config);
        $summary = [
            'employees_synced' => 0,
            'attendance_synced' => 0,
        ];

        try {
            if ($type === 'all' || $type === 'employees') {
                $employees = $service->fetchEmployees();
                $summary['employees_synced'] = $this->syncEmployees($employees);
            }

            if ($type === 'all' || $type === 'attendance') {
                $logs = $service->fetchAttendance([
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]);
                $summary['attendance_synced'] = $this->syncLogs($logs);
            }
        } catch (RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
            return;
        }

        $this->activityLogger->log(Auth::id(), 'attendance_sync', null, 'synced', 'BioTime attendance data synced.', $summary + [
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Attendance sync completed.',
            'data' => $summary,
        ]);
    }

    public function report(Request $request): void
    {
        AttendanceSchemaService::ensureSchema($this->pdo);

        $employeeCode = trim((string) $request->query('employee_code', ''));
        $department = trim((string) $request->query('department', ''));
        $branch = trim((string) $request->query('branch', ''));
        $dateFrom = $this->normalizeDate($request->query('date_from', '')) ?? date('Y-m-d');
        $dateTo = $this->normalizeDate($request->query('date_to', '')) ?? $dateFrom;

        $rowsStatement = $this->pdo->prepare("
            SELECT
                l.employee_code,
                COALESCE(
                    MAX(NULLIF(TRIM(l.employee_name), '')),
                    MAX(NULLIF(TRIM(e.full_name), '')),
                    MAX(NULLIF(TRIM(CONCAT_WS(' ', NULLIF(e.first_name, ''), NULLIF(e.last_name, ''))), '')),
                    '-'
                ) AS employee_name,
                COALESCE(MAX(NULLIF(TRIM(e.department_name), '')), '-') AS department_name,
                COALESCE(MAX(NULLIF(TRIM(e.area_name), '')), '-') AS branch_name,
                DATE(l.punch_time) AS attendance_date,
                MIN(l.punch_time) AS in_time,
                MAX(l.punch_time) AS out_time,
                COUNT(*) AS punch_count
            FROM attendance_logs l
            LEFT JOIN attendance_biotime_employees e
                ON e.employee_code = l.employee_code
            WHERE l.punch_time IS NOT NULL
              AND DATE(l.punch_time) >= :date_from
              AND DATE(l.punch_time) <= :date_to
              AND (:employee_code = '' OR l.employee_code = :employee_code)
              AND (:department = '' OR e.department_name = :department)
              AND (:branch = '' OR e.area_name = :branch)
            GROUP BY l.employee_code, DATE(l.punch_time)
            ORDER BY attendance_date DESC, employee_name ASC, l.employee_code ASC
        ");
        $rowsStatement->execute([
            'employee_code' => $employeeCode,
            'department' => $department,
            'branch' => $branch,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        $rows = $rowsStatement->fetchAll();

        $employees = $this->pdo->query("
            SELECT employee_code, full_name, department_name, area_name
            FROM attendance_biotime_employees
            ORDER BY full_name ASC, employee_code ASC
        ")->fetchAll();

        $departments = $this->pdo->query("
            SELECT DISTINCT department_name
            FROM attendance_biotime_employees
            WHERE department_name IS NOT NULL AND department_name <> ''
            ORDER BY department_name ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        $branches = $this->pdo->query("
            SELECT DISTINCT area_name
            FROM attendance_biotime_employees
            WHERE area_name IS NOT NULL AND area_name <> ''
            ORDER BY area_name ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        Response::json([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'lookups' => [
                    'employees' => $employees,
                    'departments' => $departments,
                    'branches' => $branches,
                ],
                'summary' => [
                    'rows' => count($rows),
                    'employees' => count(array_unique(array_filter(array_column($rows, 'employee_code')))),
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ],
        ]);
    }

    private function syncEmployees(array $rows): int
    {
        $statement = $this->pdo->prepare("
            INSERT INTO attendance_biotime_employees (
                remote_id, employee_code, full_name, first_name, last_name,
                department_name, position_name, area_name, is_active, payload_json
            ) VALUES (
                :remote_id, :employee_code, :full_name, :first_name, :last_name,
                :department_name, :position_name, :area_name, :is_active, :payload_json
            )
            ON DUPLICATE KEY UPDATE
                employee_code = VALUES(employee_code),
                full_name = VALUES(full_name),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                department_name = VALUES(department_name),
                position_name = VALUES(position_name),
                area_name = VALUES(area_name),
                is_active = VALUES(is_active),
                payload_json = VALUES(payload_json)
        ");

        $count = 0;
        foreach ($rows as $row) {
            $remoteId = (string) ($row['id'] ?? '');
            if ($remoteId === '') {
                continue;
            }

            $firstName = $this->stringOrNull($row['first_name'] ?? null);
            $lastName = $this->stringOrNull($row['last_name'] ?? null);
            $fullName = trim((string) ($row['full_name'] ?? $row['format_name'] ?? $row['name'] ?? ($firstName . ' ' . $lastName)));
            $statement->execute([
                'remote_id' => $remoteId,
                'employee_code' => $this->stringOrNull($row['emp_code'] ?? $row['code'] ?? null),
                'full_name' => $fullName !== '' ? $fullName : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'department_name' => $this->extractNestedName($row['department'] ?? null, $row['department_name'] ?? null),
                'position_name' => $this->extractNestedName($row['position'] ?? null, $row['position_name'] ?? null),
                'area_name' => $this->extractNestedName($row['area'] ?? null, $row['area_name'] ?? null),
                'is_active' => !isset($row['is_active']) ? 1 : (int) (bool) $row['is_active'],
                'payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $count++;
        }

        return $count;
    }

    private function syncLogs(array $rows): int
    {
        $statement = $this->pdo->prepare("
            INSERT INTO attendance_logs (
                remote_id, remote_employee_id, employee_code, employee_name, punch_time, punch_state,
                verify_type, terminal_alias, area_alias, payload_json
            ) VALUES (
                :remote_id, :remote_employee_id, :employee_code, :employee_name, :punch_time, :punch_state,
                :verify_type, :terminal_alias, :area_alias, :payload_json
            )
            ON DUPLICATE KEY UPDATE
                remote_employee_id = VALUES(remote_employee_id),
                employee_code = VALUES(employee_code),
                employee_name = VALUES(employee_name),
                punch_time = VALUES(punch_time),
                punch_state = VALUES(punch_state),
                verify_type = VALUES(verify_type),
                terminal_alias = VALUES(terminal_alias),
                area_alias = VALUES(area_alias),
                payload_json = VALUES(payload_json)
        ");

        $count = 0;
        foreach ($rows as $row) {
            $remoteId = (string) ($row['id'] ?? '');
            if ($remoteId === '') {
                continue;
            }

            $employeePayload = is_array($row['emp'] ?? null) ? $row['emp'] : [];
            $firstName = $this->stringOrNull($row['first_name'] ?? $employeePayload['first_name'] ?? null);
            $lastName = $this->stringOrNull($row['last_name'] ?? $employeePayload['last_name'] ?? null);
            $employeeName = trim((string) (
                $row['employee_name']
                ?? $row['name']
                ?? $row['full_name']
                ?? ($firstName . ' ' . $lastName)
            ));

            $statement->execute([
                'remote_id' => $remoteId,
                'remote_employee_id' => $this->stringOrNull($employeePayload['id'] ?? $row['emp'] ?? $row['emp_id'] ?? null),
                'employee_code' => $this->stringOrNull($employeePayload['emp_code'] ?? $row['emp_code'] ?? $row['code'] ?? null),
                'employee_name' => $employeeName !== '' ? $employeeName : null,
                'punch_time' => $this->normalizeDateTime($row['punch_time'] ?? $row['checktime'] ?? null),
                'punch_state' => $this->stringOrNull($row['punch_state'] ?? $row['state'] ?? null),
                'verify_type' => $this->stringOrNull($row['verify_type'] ?? null),
                'terminal_alias' => $this->stringOrNull($row['terminal_alias'] ?? null),
                'area_alias' => $this->stringOrNull($row['area_alias'] ?? null),
                'payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $count++;
        }

        return $count;
    }

    private function loadConfig(): array
    {
        $statement = $this->pdo->prepare("
            SELECT setting_value
            FROM settings
            WHERE category = 'attendance' AND setting_key = 'biotime_config'
            LIMIT 1
        ");
        $statement->execute();
        $raw = $statement->fetchColumn();
        if (!$raw) {
            return [
                'base_url' => '',
                'username' => '',
                'password' => '',
                'token_path' => '/jwt-api-token-auth/',
                'employees_path' => '/personnel/api/employees/',
                'attendance_path' => '/iclock/api/transactions/',
                'page_size' => 100,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function extractNestedName(mixed $nested, mixed $fallback = null): ?string
    {
        if (is_array($nested) && array_keys($nested) === range(0, count($nested) - 1)) {
            $values = [];

            foreach ($nested as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = $item['name'] ?? $item['alias'] ?? $item['dept_name'] ?? $item['area_name'] ?? $item['position_name'] ?? null;
                $label = $this->stringOrNull($label);
                if ($label !== null) {
                    $values[] = $label;
                }
            }

            return $values ? implode(', ', $values) : $this->stringOrNull($fallback);
        }

        if (is_array($nested)) {
            return $this->stringOrNull(
                $nested['name']
                ?? $nested['alias']
                ?? $nested['dept_name']
                ?? $nested['area_name']
                ?? $nested['position_name']
                ?? $fallback
            );
        }

        return $this->stringOrNull($nested ?? $fallback);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeBoundaryDateTime(mixed $value, bool $endOfDay): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (!str_contains($value, ':')) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        return $this->normalizeDateTime($value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
