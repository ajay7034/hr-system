CREATE DATABASE IF NOT EXISTS hr_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hr_portal;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS email_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS company_documents;
DROP TABLE IF EXISTS company_document_masters;
DROP TABLE IF EXISTS employee_documents;
DROP TABLE IF EXISTS employee_document_masters;
DROP TABLE IF EXISTS passport_movement_history;
DROP TABLE IF EXISTS passport_records;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS designations;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    website VARCHAR(150) NULL,
    logo_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(40) NOT NULL UNIQUE,
    location VARCHAR(150) NULL,
    contact_email VARCHAR(150) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(40) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE designations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(40) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(80) NOT NULL,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    slug VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    avatar_path VARCHAR(255) NULL,
    last_login_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(60) NOT NULL UNIQUE,
    employee_code VARCHAR(60) NOT NULL UNIQUE,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    designation_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(160) NOT NULL,
    first_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    email VARCHAR(150) NULL UNIQUE,
    mobile VARCHAR(40) NULL,
    joining_date DATE NULL,
    visa_status VARCHAR(80) NULL,
    emirates_id VARCHAR(80) NULL,
    passport_number VARCHAR(80) NULL UNIQUE,
    nationality VARCHAR(80) NULL,
    status ENUM('active', 'inactive', 'resigned', 'terminated') NOT NULL DEFAULT 'active',
    profile_photo_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_employees_designation FOREIGN KEY (designation_id) REFERENCES designations(id),
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_employees_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_employee_status (status),
    INDEX idx_employee_name (full_name),
    INDEX idx_employee_company_branch (company_id, branch_id)
);

CREATE TABLE passport_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL UNIQUE,
    passport_number VARCHAR(80) NOT NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    passport_file_path VARCHAR(255) NULL,
    current_status ENUM('in_hand', 'outside') NOT NULL DEFAULT 'outside',
    collected_date DATE NULL,
    withdrawn_date DATE NULL,
    collected_reason VARCHAR(255) NULL,
    withdrawn_reason VARCHAR(255) NULL,
    remarks TEXT NULL,
    last_updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_passport_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_passport_updated_by FOREIGN KEY (last_updated_by) REFERENCES users(id),
    INDEX idx_passport_status (current_status),
    INDEX idx_passport_expiry (expiry_date)
);

CREATE TABLE passport_movement_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passport_record_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('collected', 'given_back') NOT NULL,
    from_status ENUM('in_hand', 'outside') NOT NULL,
    to_status ENUM('in_hand', 'outside') NOT NULL,
    movement_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    remarks TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_passport_history_record FOREIGN KEY (passport_record_id) REFERENCES passport_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_passport_history_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_passport_history_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_passport_history_employee_date (employee_id, movement_date DESC)
);

CREATE TABLE employee_document_masters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(60) NOT NULL UNIQUE,
    has_expiry TINYINT(1) NOT NULL DEFAULT 1,
    default_alert_days INT NOT NULL DEFAULT 30,
    default_mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
    default_notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE employee_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    document_master_id BIGINT UNSIGNED NOT NULL,
    document_number VARCHAR(120) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    file_path VARCHAR(255) NULL,
    remarks TEXT NULL,
    status ENUM('valid', 'expiring_soon', 'expired', 'inactive') NOT NULL DEFAULT 'valid',
    alert_days INT NOT NULL DEFAULT 30,
    mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
    notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_reminder_sent_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_documents_master FOREIGN KEY (document_master_id) REFERENCES employee_document_masters(id),
    CONSTRAINT fk_employee_documents_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_employee_documents_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_employee_documents_expiry (expiry_date, status),
    INDEX idx_employee_documents_lookup (employee_id, document_master_id)
);

CREATE TABLE company_document_masters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(60) NOT NULL UNIQUE,
    has_expiry TINYINT(1) NOT NULL DEFAULT 1,
    default_alert_days INT NOT NULL DEFAULT 30,
    default_mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
    default_notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE company_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    document_master_id BIGINT UNSIGNED NOT NULL,
    document_name VARCHAR(160) NOT NULL,
    document_number VARCHAR(120) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    file_path VARCHAR(255) NULL,
    remarks TEXT NULL,
    status ENUM('valid', 'expiring_soon', 'expired', 'inactive') NOT NULL DEFAULT 'valid',
    alert_days INT NOT NULL DEFAULT 30,
    mail_enabled TINYINT(1) NOT NULL DEFAULT 1,
    notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_reminder_sent_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_documents_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_company_documents_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_company_documents_master FOREIGN KEY (document_master_id) REFERENCES company_document_masters(id),
    CONSTRAINT fk_company_documents_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_company_documents_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_company_documents_expiry (expiry_date, status)
);

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    notification_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    related_table VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    severity ENUM('info', 'warning', 'critical', 'success') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sent_via_mail TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_notifications_user_read (user_id, is_read, created_at DESC)
);

CREATE TABLE email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    related_table VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(150) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email_cycle (related_table, related_id, recipient_email, subject(100))
);

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_user (user_id, created_at DESC)
);
