<?php

namespace App\Http\Middleware;

use App\Support\PrimaryAdministrator;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        abort_unless($user && $user->status === 'ACTIVE' && in_array($user->role, $roles, true), 403);

        if ($user->role === 'ADMIN') {
            abort_unless(PrimaryAdministrator::authenticated($user), 403, 'L’administrateur principal et une vérification MFA sont requis.');
        }

        return $next($request);
    }
}
