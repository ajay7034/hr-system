<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class RequireRole
{
    public function __construct(private array $allowed)
    {
    }

    public function __invoke(Request $request): void
    {
        $user = Auth::user();
        $roles = $user['roles'] ?? [];

        if (!$user || !array_intersect($this->allowed, $roles)) {
            Response::json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
            exit;
        }
    }
}
