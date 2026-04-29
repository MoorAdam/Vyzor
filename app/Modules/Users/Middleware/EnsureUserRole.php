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

        if ($user?->hasRole(UserRoleEnum::from($role))) {
            return $next($request);
        }

        // The user is logged in but doesn't hold the required role. Send them
        // to a route they can actually access — falling back to /no-access for
        // users with no usable role so they can at least log out.
        $target = match (true) {
            $user?->isCustomer() => 'customer.dashboard',
            $user?->isUser() => 'projects',
            default => 'no-access',
        };

        if ($request->routeIs($target)) {
            return redirect()->route('no-access');
        }

        return redirect()->route($target);
    }
}
