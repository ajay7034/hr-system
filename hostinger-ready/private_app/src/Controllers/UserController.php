<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\FileUploadService;
use PDO;
use RuntimeException;

final class UserController
{
    public function __construct(
        private PDO $pdo,
        private FileUploadService $uploadService
    ) {
    }

    public function index(): void
    {
        $users = $this->pdo->query("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.username,
                u.phone,
                u.company_id,
                u.branch_id,
                u.avatar_path,
                u.last_login_at,
                u.is_active,
                c.name AS company,
                b.name AS branch,
                GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ',') AS roles_csv
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            LEFT JOIN branches b ON b.id = u.branch_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY u.id
            ORDER BY u.full_name ASC
        ")->fetchAll();

        $roles = $this->pdo->query("SELECT id, name, slug, description FROM roles ORDER BY name ASC")->fetchAll();

        $normalized = array_map(static function (array $user): array {
            $user['roles'] = array_values(array_filter(explode(',', (string) ($user['roles_csv'] ?? ''))));
            unset($user['roles_csv']);
            return $user;
        }, $users);

        Response::json(['success' => true, 'data' => ['users' => $normalized, 'roles' => $roles]]);
    }

    public function store(Request $request): void
    {
        $password = (string) $request->input('password', '');
        if ($password === '') {
            Response::json(['success' => false, 'message' => 'Password is required.'], 422);
            return;
        }

        $avatarPath = $this->uploadService->store($request->file('avatar'), 'users');
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare("
                INSERT INTO users (
                    branch_id, company_id, full_name, email, username, password_hash, phone, avatar_path, is_active
                ) VALUES (
                    :branch_id, :company_id, :full_name, :email, :username, :password_hash, :phone, :avatar_path, :is_active
                )
            ");
            $statement->execute([
                'branch_id' => $this->nullableInt($request->input('branch_id')),
                'company_id' => $this->nullableInt($request->input('company_id')),
                'full_name' => $request->input('full_name'),
                'email' => $request->input('email'),
                'username' => $request->input('username'),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $request->input('phone'),
                'avatar_path' => $avatarPath,
                'is_active' => (int) $request->input('is_active', 1),
            ]);

            $userId = (int) $this->pdo->lastInsertId();
            $this->syncRoles($userId, (array) $request->input('roles', []));
            $this->pdo->commit();

            Response::json(['success' => true, 'message' => 'User created successfully.', 'data' => ['id' => $userId]], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(Request $request, array $params): void
    {
        $userId = (int) $params['id'];
        $avatarPath = $this->uploadService->store($request->file('avatar'), 'users');
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare("
                UPDATE users SET
                    branch_id = :branch_id,
                    company_id = :company_id,
                    full_name = :full_name,
                    email = :email,
                    username = :username,
                    phone = :phone,
                    avatar_path = COALESCE(:avatar_path, avatar_path),
                    is_active = :is_active
                WHERE id = :id
            ");
            $statement->execute([
                'branch_id' => $this->nullableInt($request->input('branch_id')),
                'company_id' => $this->nullableInt($request->input('company_id')),
                'full_name' => $request->input('full_name'),
                'email' => $request->input('email'),
                'username' => $request->input('username'),
                'phone' => $request->input('phone'),
                'avatar_path' => $avatarPath,
                'is_active' => (int) $request->input('is_active', 1),
                'id' => $userId,
            ]);

            if ($request->input('password')) {
                $passwordStatement = $this->pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
                $passwordStatement->execute([
                    'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
                    'id' => $userId,
                ]);
            }

            $this->syncRoles($userId, (array) $request->input('roles', []));
            $this->pdo->commit();

            Response::json(['success' => true, 'message' => 'User updated successfully.']);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function profile(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            return;
        }

        Response::json(['success' => true, 'data' => $this->loadUserPayload($userId)]);
    }

    public function updateProfile(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            return;
        }

        $avatarPath = $this->uploadService->store($request->file('avatar'), 'users');
        $statement = $this->pdo->prepare("
            UPDATE users SET
                full_name = :full_name,
                email = :email,
                username = :username,
                phone = :phone,
                company_id = :company_id,
                branch_id = :branch_id,
                avatar_path = COALESCE(:avatar_path, avatar_path)
            WHERE id = :id
        ");
        $statement->execute([
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'phone' => $request->input('phone'),
            'company_id' => $this->nullableInt($request->input('company_id')),
            'branch_id' => $this->nullableInt($request->input('branch_id')),
            'avatar_path' => $avatarPath,
            'id' => $userId,
        ]);

        $payload = $this->loadUserPayload($userId);
        Auth::attempt($payload);

        Response::json(['success' => true, 'message' => 'Profile updated successfully.', 'data' => $payload]);
    }

    public function updatePassword(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            return;
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $confirmPassword = (string) $request->input('confirm_password', '');

        if ($newPassword === '' || $newPassword !== $confirmPassword) {
            Response::json(['success' => false, 'message' => 'New password confirmation does not match.'], 422);
            return;
        }

        $statement = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            Response::json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
            return;
        }

        $update = $this->pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
        $update->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        Response::json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    private function loadUserPayload(int $userId): array
    {
        $statement = $this->pdo->prepare("
            SELECT u.*, GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ',') AS roles_csv
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.id = :id
            GROUP BY u.id
            LIMIT 1
        ");
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        return [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'phone' => $user['phone'],
            'company_id' => $user['company_id'] ? (int) $user['company_id'] : null,
            'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null,
            'avatar_path' => $user['avatar_path'],
            'roles' => array_values(array_filter(explode(',', (string) $user['roles_csv']))),
        ];
    }

    private function syncRoles(int $userId, array $roles): void
    {
        $roles = array_values(array_filter(array_map('strval', $roles)));
        $delete = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
        $delete->execute(['user_id' => $userId]);

        if (!$roles) {
            return;
        }

        $roleStatement = $this->pdo->prepare("SELECT id FROM roles WHERE slug = :slug LIMIT 1");
        $insert = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");

        foreach ($roles as $slug) {
            $roleStatement->execute(['slug' => $slug]);
            $role = $roleStatement->fetch();
            if (!$role) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'role_id' => (int) $role['id'],
            ]);
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }
}
