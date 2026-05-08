<?php

namespace App\Services;

use PDO;

final class AccommodationSchemaService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS accommodation_document_masters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(80) NOT NULL UNIQUE,
                has_expiry TINYINT(1) NOT NULL DEFAULT 1,
                default_alert_days INT NOT NULL DEFAULT 30,
                default_mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
                default_notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS accommodations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NULL,
                accommodation_name VARCHAR(255) NOT NULL,
                location VARCHAR(255) NULL,
                room_number VARCHAR(120) NOT NULL,
                main_employee_id INT UNSIGNED NULL,
                resident_employee_ids LONGTEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                remarks TEXT NULL,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_accommodations_company (company_id),
                INDEX idx_accommodations_main_employee (main_employee_id),
                INDEX idx_accommodations_status (status),
                INDEX idx_accommodations_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS accommodation_documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                accommodation_id INT UNSIGNED NOT NULL,
                document_master_id INT UNSIGNED NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_number VARCHAR(255) NULL,
                issue_date DATE NULL,
                expiry_date DATE NULL,
                file_path VARCHAR(255) NULL,
                remarks TEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'valid',
                alert_days INT NOT NULL DEFAULT 30,
                mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
                notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_accommodation_documents_accommodation (accommodation_id),
                INDEX idx_accommodation_documents_master (document_master_id),
                INDEX idx_accommodation_documents_status (status),
                INDEX idx_accommodation_documents_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $masterCount = (int) $pdo->query("SELECT COUNT(*) FROM accommodation_document_masters")->fetchColumn();
        if ($masterCount === 0) {
            $pdo->exec("
                INSERT INTO accommodation_document_masters (
                    name, code, has_expiry, default_alert_days, default_mail_enabled, default_notification_enabled, sort_order
                ) VALUES
                    ('Tenancy', 'TENANCY', 1, 30, 1, 1, 1),
                    ('Accommodation Insurance', 'ACCOMMODATION_INSURANCE', 1, 30, 1, 1, 2)
            ");
        }
    }
}
