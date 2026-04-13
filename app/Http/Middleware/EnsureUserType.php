<?php

namespace App\Http\Middleware;

use App\UserTypeEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        if ($user?->type !== UserTypeEnum::from($type)) {
            return $user?->isCustomer()
                ? redirect()->route('customer.dashboard')
                : redirect()->route('clarity.snapshot');
        }

        return $next($request);
    }
}
