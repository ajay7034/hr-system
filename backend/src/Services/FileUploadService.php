<?php

namespace App\Services;

use RuntimeException;

final class FileUploadService
{
    public function __construct(private string $uploadDir)
    {
    }

    public function store(?array $file, string $directory = 'general'): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        $targetDir = rtrim($this->uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('file_', true) . ($extension ? '.' . $extension : '');
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Unable to move uploaded file.');
        }

        return trim($directory, DIRECTORY_SEPARATOR) . '/' . $filename;
    }
}
