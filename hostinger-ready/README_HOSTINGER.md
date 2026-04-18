# Hostinger-Ready Package

This folder is a separate deployment copy of the HR portal prepared for shared hosting.

## Structure

- `public_html/`
  - upload this into Hostinger `public_html`
- `private_app/`
  - keep this outside `public_html` if your hosting plan allows it
- `storage/uploads/`
  - writable upload directory used by the API
- `database/`
  - SQL schema and seed files

## Important Files

- `private_app/.env`
  - set your real Hostinger domain and database credentials here
- `public_html/api/index.php`
  - production API entrypoint for `/api`
- `public_html/.htaccess`
  - React SPA routing
- `public_html/api/.htaccess`
  - API routing

## What To Edit Before Upload

Update `private_app/.env`:

- `APP_URL=https://media.cybercodix.com/hr/api`
- `FRONTEND_URL=https://media.cybercodix.com/hr`
- `DB_DATABASE=u171750300_hr`
- `DB_USERNAME=u171750300_hr`
- `DB_PASSWORD=Codix@2k25`
- `UPLOAD_DIR=/home/u171750300/domains/media.cybercodix.com/public_html/hr-storage/uploads`

## Suggested Hostinger Layout

- `/public_html/hr` -> contents of `public_html/`
- `/public_html/hr-storage/uploads` -> writable upload directory
- keep a copy of `private_app/` outside web-access if your plan allows it; if not, place it in a non-browsable folder and keep `.htaccess` protection

The current deployment copy is already prepared for:

- frontend path: `https://media.cybercodix.com/hr`
- API path: `https://media.cybercodix.com/hr/api`

## Database

Import:

1. `database/schema.sql`
2. optional: `seeds/sample_data.sql` if you want demo data

## Local Test Of This Deployment Copy

You can test this separate Hostinger-ready copy locally with:

```bash
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8090 /Applications/XAMPP/xamppfiles/htdocs/hr/hostinger-ready/router.php
```

Open:

```text
http://127.0.0.1:8090
```

For local testing, you may temporarily replace the values in `private_app/.env` with local database settings.
