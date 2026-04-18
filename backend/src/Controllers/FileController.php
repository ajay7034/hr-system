<?php

namespace App\Controllers;

final class FileController
{
    public function __construct(private string $uploadDir)
    {
    }

    public function view(): void
    {
        $relativePath = (string) ($_GET['path'] ?? '');
        if ($relativePath === '') {
            http_response_code(404);
            exit;
        }

        $basePath = realpath($this->uploadDir);
        $fullPath = realpath(rtrim($this->uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR));

        if (!$basePath || !$fullPath || strncmp($fullPath, $basePath, strlen($basePath)) !== 0 || !is_file($fullPath)) {
            http_response_code(404);
            exit;
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        readfile($fullPath);
        exit;
    }
}
