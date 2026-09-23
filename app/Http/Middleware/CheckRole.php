<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $userRole = in_array($user->role, ['biro', 'reviewer']) ? User::ROLE_ADMIN : $user->role;

        $allowedRoles = array_map(function ($role) {
            if ($role === 'super_admin') return User::ROLE_SUPERADMIN;
            if (in_array($role, ['biro', 'reviewer'])) return User::ROLE_ADMIN;
            return $role;
        }, $roles);

        if (!in_array($userRole, $allowedRoles)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
