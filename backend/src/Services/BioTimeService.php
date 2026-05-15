<?php

namespace App\Services;

use RuntimeException;

final class BioTimeService
{
    public function __construct(private array $config)
    {
    }

    public function fetchEmployees(): array
    {
        return $this->fetchPaginated($this->config['employees_path'] ?? '/personnel/api/employees/');
    }

    public function fetchAttendance(array $filters = []): array
    {
        $query = [];

        if (!empty($filters['date_from'])) {
            $query['start_time'] = str_contains((string) $filters['date_from'], ':')
                ? (string) $filters['date_from']
                : $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $query['end_time'] = str_contains((string) $filters['date_to'], ':')
                ? (string) $filters['date_to']
                : $filters['date_to'] . ' 23:59:59';
        }

        return $this->fetchPaginated($this->config['attendance_path'] ?? '/iclock/api/transactions/', $query);
    }

    private function fetchPaginated(string $path, array $query = []): array
    {
        $pageSize = max(1, (int) ($this->config['page_size'] ?? 100));
        $query['page_size'] = $pageSize;
        $url = $this->buildUrl($path, $query);
        $token = $this->issueToken();
        $items = [];

        while ($url) {
            $response = $this->request('GET', $url, $token);
            $decoded = json_decode($response, true);

            if (is_array($decoded) && array_key_exists('results', $decoded)) {
                $items = array_merge($items, is_array($decoded['results']) ? $decoded['results'] : []);
                $url = $decoded['next'] ?? null;
                continue;
            }

             if (is_array($decoded) && array_key_exists('data', $decoded)) {
                $items = array_merge($items, is_array($decoded['data']) ? $decoded['data'] : []);
                $url = $decoded['next'] ?? null;
                continue;
            }

            if (is_array($decoded)) {
                $isList = array_keys($decoded) === range(0, count($decoded) - 1);
                return $isList ? $decoded : [];
            }

            return [];
        }

        return $items;
    }

    private function issueToken(): string
    {
        $url = $this->buildUrl($this->config['token_path'] ?? '/jwt-api-token-auth/');
        $payload = json_encode([
            'username' => (string) ($this->config['username'] ?? ''),
            'password' => (string) ($this->config['password'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->request('POST', $url, null, $payload, ['Content-Type: application/json']);
        $decoded = json_decode($response, true);
        $token = $decoded['token'] ?? $decoded['access'] ?? null;

        if (!$token) {
            throw new RuntimeException('BioTime token request did not return a token.');
        }

        return (string) $token;
    }

    private function request(string $method, string $url, ?string $token = null, ?string $body = null, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($token) {
            $headers[] = 'Authorization: JWT ' . $token;
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('BioTime request failed: ' . ($error ?: 'Unknown cURL error.'));
        }

        if ($statusCode >= 400) {
            throw new RuntimeException('BioTime request failed with HTTP ' . $statusCode . '.');
        }

        return (string) $response;
    }

    private function buildUrl(string $path, array $query = []): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            $url = $path;
        } else {
            $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
            if ($baseUrl === '') {
                throw new RuntimeException('BioTime base URL is not configured.');
            }

            $url = $baseUrl . '/' . ltrim($path, '/');
        }

        if ($query) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        return $url;
    }
}
