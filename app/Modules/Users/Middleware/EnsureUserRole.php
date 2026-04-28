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
        // to a route they can actually access — and abort 403 if no such route
        // applies, to avoid redirect loops when the request is already on the
        // fallback route.
        $target = match (true) {
            $user?->isCustomer() => 'customer.dashboard',
            $user?->isUser() => 'projects',
            default => null,
        };

        if ($target === null || $request->routeIs($target)) {
            abort(403);
        }

        return redirect()->route($target);
    }
}
