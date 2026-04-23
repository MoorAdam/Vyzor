<?php

namespace App\Http\Middleware;

use App\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        if ($user?->role !== UserRoleEnum::from($role)) {
            return $user?->isCustomer()
                ? redirect()->route('customer.dashboard')
                : redirect()->route('clarity.snapshot');
        }

        return $next($request);
    }
}
