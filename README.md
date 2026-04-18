# HR Document & Passport Management Portal

Full-stack internal HR portal scaffold for employee records, passport custody, document expiry tracking, notifications, and reporting.

## Stack

- Frontend: React + Vite
- Backend: PHP 8.1+ REST API
- Database: MySQL
- Auth: PHP session-based authentication
- Charts/UI: Recharts + Lucide React

## Project Structure

```text
hr/
├── backend/
│   ├── public/              # API entrypoint and Apache rewrite
│   ├── scripts/             # Cron-ready reminder generation
│   ├── src/                 # Config, core router, controllers, services
│   └── storage/uploads/     # Uploaded documents and passport copies
├── database/schema.sql      # Full MySQL schema
├── seeds/sample_data.sql    # Seed data
├── docs/                    # API notes and import sample
└── frontend/                # React app
```

## Included Modules

- Login and logout
- Dashboard with live counters, charts, alerts, and recent activity
- Employee master listing and employee profile page
- Passport custody tracking with movement history
- Employee document register
- Company document register
- Settings and masters overview
- Reports for passport custody and expiry monitoring
- Notification and reminder queue scaffolding

## Setup In XAMPP

1. Copy `backend/.env.example` to `backend/.env` and update database + SMTP values.
2. Create the database and import:
   - `database/schema.sql`
   - `seeds/sample_data.sql`
3. From `frontend/`, run:
   - `npm install`
   - `npm run dev`
4. Keep Apache + MySQL running in XAMPP.
5. Open the frontend dev server, usually `http://localhost:5173`.

For production-style local serving, build the frontend with `npm run build` and serve the built output through Apache or a separate frontend host.

## Demo Credentials

- Username: `admin`
- Password: `password`

Note:

- Update the seeded admin password hash if you regenerate the seed.
- Uploaded files are stored under `backend/storage/uploads`.
- Reminder generation can be scheduled with:
  - `php /Applications/XAMPP/xamppfiles/htdocs/hr/backend/scripts/run_reminders.php`

## Import Sample

- Employee import sample: `docs/employee_import_sample.csv`
- API route for headers: `GET /employees/import-template`

## Current Scope

This scaffold is production-structured and near-production-ready, but still intended as a foundation project:

- CRUD is fully structured for extension, with key read/write flows already wired.
- Bulk import is enabled through CSV in the current scaffold, with sample format included.
- SMTP dispatch is queued via `email_logs`; actual mail transport worker implementation can be added next.
