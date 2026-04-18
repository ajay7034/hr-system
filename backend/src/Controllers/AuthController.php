<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class AuthController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function login(Request $request): void
    {
        $identifier = trim((string) $request->input('identifier', ''));
        $password = (string) $request->input('password', '');

        $statement = $this->pdo->prepare('
            SELECT u.*, GROUP_CONCAT(r.slug) AS roles_csv
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE (u.email = :identifier OR u.username = :identifier) AND u.is_active = 1
            GROUP BY u.id
            LIMIT 1
        ');
        $statement->execute(['identifier' => $identifier]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
            return;
        }

        $payload = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'phone' => $user['phone'],
            'company_id' => $user['company_id'] ? (int) $user['company_id'] : null,
            'avatar_path' => $user['avatar_path'],
            'roles' => array_values(array_filter(explode(',', (string) $user['roles_csv']))),
        ];

        Auth::attempt($payload);

        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

        Response::json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => $payload,
        ]);
    }

    public function me(): void
    {
        Response::json([
            'success' => true,
            'data' => Auth::user(),
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        Response::json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
