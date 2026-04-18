# API Overview

Base URL:

`http://localhost/hr/backend/public/api`

Core endpoints:

- `POST /auth/login`
- `GET /auth/me`
- `POST /auth/logout`
- `GET /dashboard/summary`
- `GET /employees`
- `GET /employees/import-template`
- `POST /employees/import`
- `GET /employees/{id}`
- `POST /employees`
- `PUT /employees/{id}`
- `GET /passports`
- `GET /passports/history/{employeeId}`
- `POST /passports`
- `GET /employee-documents`
- `POST /employee-documents`
- `GET /company-documents`
- `POST /company-documents`
- `GET /settings`
- `POST /settings`
- `GET /notifications`
- `POST /notifications/{id}/read`
- `GET /reports/passports`
- `GET /reports/expiry`

Authentication:

- Session-based via PHP session cookie.
- Frontend must send credentials with every request.

Notes:

- `POST /passports`, `POST /employee-documents`, and `POST /company-documents` accept `multipart/form-data`.
- Reminder generation is designed for cron usage through `backend/scripts/run_reminders.php`.
