<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$filePath = __DIR__ . $path;

if ($path !== '/' && is_file($filePath)) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = match ($extension) {
        'js' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'ico' => 'image/x-icon',
        default => mime_content_type($filePath) ?: 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

readfile(__DIR__ . '/index.html');
exit;
