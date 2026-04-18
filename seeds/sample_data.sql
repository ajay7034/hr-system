USE hr_portal;

INSERT INTO companies (id, name, code, email, phone, address, website)
VALUES
    (1, 'Northstar Holdings', 'NSH', 'info@northstar.local', '+971501112233', 'Dubai Silicon Oasis, Dubai', 'https://northstar.local');

INSERT INTO branches (id, company_id, name, code, location, contact_email)
VALUES
    (1, 1, 'Dubai HQ', 'DXB-HQ', 'Dubai', 'dubai@northstar.local'),
    (2, 1, 'Abu Dhabi Office', 'AUH-01', 'Abu Dhabi', 'abudhabi@northstar.local');

INSERT INTO departments (id, name, code)
VALUES
    (1, 'Human Resources', 'HR'),
    (2, 'Finance', 'FIN'),
    (3, 'Operations', 'OPS'),
    (4, 'IT', 'IT');

INSERT INTO designations (id, name, code)
VALUES
    (1, 'HR Executive', 'HR-EX'),
    (2, 'Accountant', 'ACC'),
    (3, 'Operations Supervisor', 'OPS-SUP'),
    (4, 'System Administrator', 'IT-SADM');

INSERT INTO roles (id, name, slug, description)
VALUES
    (1, 'Administrator', 'admin', 'Full access'),
    (2, 'HR User', 'hr_user', 'Employee and document management'),
    (3, 'Viewer', 'viewer', 'Read-only access');

INSERT INTO users (id, branch_id, company_id, full_name, email, username, password_hash, phone)
VALUES
    (1, 1, 1, 'System Administrator', 'admin@northstar.local', 'admin', '$2y$10$bB1om3x8AaWJrQfrBn2R0.PcguSsDFBjJpaBJ2FWe/szfmSgwmFMS', '+971500000000');

INSERT INTO user_roles (user_id, role_id)
VALUES (1, 1);

INSERT INTO employee_document_masters (id, name, code, has_expiry, default_alert_days, default_mail_enabled, default_notification_enabled, sort_order)
VALUES
    (1, 'Passport', 'PASSPORT', 1, 90, 1, 1, 1),
    (2, 'Visa', 'VISA', 1, 60, 1, 1, 2),
    (3, 'Emirates ID', 'EID', 1, 45, 1, 1, 3),
    (4, 'Insurance', 'INS', 1, 30, 1, 1, 4),
    (5, 'Contract', 'CONTRACT', 0, 0, 0, 0, 5);

INSERT INTO company_document_masters (id, name, code, has_expiry, default_alert_days, default_mail_enabled, default_notification_enabled, sort_order)
VALUES
    (1, 'Trade License', 'TRADE_LICENSE', 1, 60, 1, 1, 1),
    (2, 'VAT Certificate', 'VAT', 1, 45, 1, 1, 2),
    (3, 'Insurance', 'COMP_INS', 1, 30, 1, 1, 3),
    (4, 'MOA', 'MOA', 0, 0, 0, 0, 4);

INSERT INTO employees (id, employee_id, employee_code, company_id, branch_id, department_id, designation_id, full_name, first_name, last_name, email, mobile, joining_date, visa_status, emirates_id, passport_number, nationality, status, notes, created_by, updated_by)
VALUES
    (1, 'EMP-1001', 'NSH-HR-001', 1, 1, 1, 1, 'Aisha Rahman', 'Aisha', 'Rahman', 'aisha.rahman@northstar.local', '+971501234567', '2023-04-01', 'Active', '784-1989-1234567-1', 'P1234567', 'Indian', 'active', 'Handles onboarding and renewals.', 1, 1),
    (2, 'EMP-1002', 'NSH-FN-002', 1, 1, 2, 2, 'Omar Khalid', 'Omar', 'Khalid', 'omar.khalid@northstar.local', '+971509876543', '2022-09-15', 'Active', '784-1991-7654321-1', 'P9876543', 'Pakistani', 'active', 'Finance approvals.', 1, 1),
    (3, 'EMP-1003', 'NSH-OP-003', 1, 2, 3, 3, 'Leena Joseph', 'Leena', 'Joseph', 'leena.joseph@northstar.local', '+971508887766', '2021-11-12', 'Renewal due', '784-1990-5555555-1', 'P5558888', 'Filipino', 'active', 'Site operations lead.', 1, 1);

INSERT INTO passport_records (id, employee_id, passport_number, issue_date, expiry_date, current_status, collected_date, withdrawn_date, collected_reason, withdrawn_reason, remarks, last_updated_by)
VALUES
    (1, 1, 'P1234567', '2019-05-20', '2029-05-19', 'in_hand', '2026-03-01', NULL, 'Renewal documentation', NULL, 'Stored in locker A-12', 1),
    (2, 2, 'P9876543', '2018-01-01', '2028-01-01', 'outside', '2026-01-10', '2026-04-01', 'Visa stamping', 'Family travel', 'Expected return by 2026-04-20', 1),
    (3, 3, 'P5558888', '2017-07-15', '2027-07-14', 'in_hand', '2026-04-06', NULL, 'Bank KYC update', NULL, 'Pending labor card renewal', 1);

INSERT INTO passport_movement_history (passport_record_id, employee_id, movement_type, from_status, to_status, movement_date, reason, remarks, updated_by)
VALUES
    (1, 1, 'collected', 'outside', 'in_hand', '2026-03-01', 'Renewal documentation', 'Collected at HR front desk', 1),
    (2, 2, 'collected', 'outside', 'in_hand', '2026-01-10', 'Visa stamping', 'Received by HR executive', 1),
    (2, 2, 'given_back', 'in_hand', 'outside', '2026-04-01', 'Family travel', 'Returned to employee after approval', 1),
    (3, 3, 'collected', 'outside', 'in_hand', '2026-04-06', 'Bank KYC update', 'Locker B-02', 1);

INSERT INTO employee_documents (employee_id, document_master_id, document_number, issue_date, expiry_date, remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by)
VALUES
    (1, 2, 'VISA-AR-001', '2024-04-01', '2026-05-15', 'Residence visa', 'expiring_soon', 45, 1, 1, 1, 1),
    (1, 3, 'EID-AR-001', '2024-04-01', '2026-04-18', 'EID renewal initiated', 'expiring_soon', 20, 1, 1, 1, 1),
    (2, 4, 'INS-OK-002', '2025-01-01', '2026-03-30', 'Corporate policy', 'expired', 15, 1, 1, 1, 1),
    (3, 2, 'VISA-LJ-003', '2024-05-10', '2026-06-05', 'Work visa', 'valid', 30, 1, 1, 1, 1);

INSERT INTO company_documents (company_id, branch_id, document_master_id, document_name, document_number, issue_date, expiry_date, remarks, status, alert_days, mail_enabled, notification_enabled, created_by, updated_by)
VALUES
    (1, 1, 1, 'Northstar Trade License', 'TL-2026-001', '2025-06-01', '2026-05-01', 'Main corporate trade license', 'expiring_soon', 45, 1, 1, 1, 1),
    (1, 1, 2, 'Northstar VAT Certificate', 'VAT-884455', '2024-01-10', '2026-04-12', 'VAT renewal pending finance sign-off', 'expiring_soon', 15, 1, 1, 1, 1),
    (1, 2, 3, 'Abu Dhabi Insurance', 'INS-AUH-09', '2025-05-20', '2026-03-28', 'Expired branch policy', 'expired', 15, 1, 1, 1, 1);

INSERT INTO notifications (user_id, notification_type, title, message, related_table, related_id, severity)
VALUES
    (1, 'employee_document_expiring', 'Emirates ID expiring', 'Aisha Rahman Emirates ID expires in 8 days.', 'employee_documents', 2, 'warning'),
    (1, 'company_document_expired', 'Company insurance expired', 'Abu Dhabi branch insurance expired 13 days ago.', 'company_documents', 3, 'critical'),
    (1, 'passport_outside', 'Passport still outside', 'Omar Khalid passport remains outside with employee.', 'passport_records', 2, 'info');

INSERT INTO settings (category, setting_key, setting_value)
VALUES
    ('company', 'company_profile', JSON_OBJECT('name', 'Northstar Holdings', 'supportEmail', 'hr@northstar.local', 'timezone', 'Asia/Dubai')),
    ('notifications', 'default_alert_days', JSON_OBJECT('employeeDocuments', 30, 'companyDocuments', 30, 'passport', 60)),
    ('mail', 'smtp', JSON_OBJECT('host', 'smtp.mailtrap.io', 'port', 2525, 'username', 'change-me', 'encryption', 'tls')),
    ('theme', 'appearance', JSON_OBJECT('defaultTheme', 'dark', 'allowToggle', true));
