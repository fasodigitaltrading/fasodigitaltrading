<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccountActiveMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->status === 'ACTIVE', 403);

        return $next($request);
    }
}
