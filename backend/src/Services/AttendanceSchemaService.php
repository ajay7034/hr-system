<?php

namespace App\Services;

use PDO;

final class AttendanceSchemaService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_biotime_employees (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                remote_id VARCHAR(120) NOT NULL,
                employee_code VARCHAR(120) NULL,
                full_name VARCHAR(255) NULL,
                first_name VARCHAR(120) NULL,
                last_name VARCHAR(120) NULL,
                department_name VARCHAR(255) NULL,
                position_name VARCHAR(255) NULL,
                area_name VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_attendance_biotime_employees_remote_id (remote_id),
                INDEX idx_attendance_biotime_employees_code (employee_code),
                INDEX idx_attendance_biotime_employees_name (full_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                remote_id VARCHAR(120) NOT NULL,
                remote_employee_id VARCHAR(120) NULL,
                employee_code VARCHAR(120) NULL,
                employee_name VARCHAR(255) NULL,
                punch_time DATETIME NULL,
                punch_state VARCHAR(120) NULL,
                verify_type VARCHAR(120) NULL,
                terminal_alias VARCHAR(255) NULL,
                area_alias VARCHAR(255) NULL,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_attendance_logs_remote_id (remote_id),
                INDEX idx_attendance_logs_employee_code (employee_code),
                INDEX idx_attendance_logs_punch_time (punch_time),
                INDEX idx_attendance_logs_remote_employee_id (remote_employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
