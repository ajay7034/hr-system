<?php

namespace App\Config;

final class AppConfig
{
    public static function all(): array
    {
        return [
            'app' => [
                'name' => Env::get('APP_NAME', 'HR Portal'),
                'env' => Env::get('APP_ENV', 'local'),
                'url' => Env::get('APP_URL', 'http://localhost/hr/backend/public'),
                'frontend_url' => Env::get('FRONTEND_URL', 'http://localhost:5173'),
                'session_name' => Env::get('SESSION_NAME', 'hr_portal_session'),
                'upload_dir' => Env::get('UPLOAD_DIR', dirname(__DIR__, 2) . '/storage/uploads'),
            ],
            'db' => [
                'host' => Env::get('DB_HOST', '127.0.0.1'),
                'port' => (int) Env::get('DB_PORT', 3306),
                'database' => Env::get('DB_DATABASE', 'hr_portal'),
                'username' => Env::get('DB_USERNAME', 'root'),
                'password' => Env::get('DB_PASSWORD', ''),
            ],
            'smtp' => [
                'host' => Env::get('SMTP_HOST', ''),
                'port' => (int) Env::get('SMTP_PORT', 25),
                'username' => Env::get('SMTP_USERNAME', ''),
                'password' => Env::get('SMTP_PASSWORD', ''),
                'encryption' => Env::get('SMTP_ENCRYPTION', 'tls'),
                'from_name' => Env::get('SMTP_FROM_NAME', 'HR Portal'),
                'from_email' => Env::get('SMTP_FROM_EMAIL', 'hr@example.local'),
            ],
        ];
    }
}
