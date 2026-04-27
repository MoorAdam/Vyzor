<?php

namespace App\Modules\Users\Middleware;

use App\Modules\Users\Enums\UserRoleEnum;
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

        if (! $user?->hasRole(UserRoleEnum::from($role))) {
            return $user?->isCustomer()
                ? redirect()->route('customer.dashboard')
                : redirect()->route('projects');
        }

        return $next($request);
    }
}
