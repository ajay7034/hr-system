<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class RequireAuth
{
    public function __invoke(Request $request): void
    {
        if (!Auth::check()) {
            Response::json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
            exit;
        }
    }
}
