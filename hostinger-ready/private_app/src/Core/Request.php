<?php

namespace App\Core;

final class Request
{
    public function __construct(
        private array $server,
        private array $query,
        private array $body,
        private array $files
    ) {
    }

    public static function capture(): self
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $payload = [];

        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
        } else {
            $payload = $_POST;
        }

        return new self($_SERVER, $_GET, $payload, $_FILES);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $publicPos = strpos($path, '/backend/public');
        if ($publicPos !== false) {
            $path = substr($path, $publicPos + strlen('/backend/public'));
        }

        return $path ?: '/';
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function input(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }
}
