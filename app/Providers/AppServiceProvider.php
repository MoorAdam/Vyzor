<?php

namespace App\Providers;

use App\Models\User;
use App\PermissionEnum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin bypass — runs before any gate check
        Gate::before(function (User $user) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        // Generic permission gate
        Gate::define('permission', function (User $user, PermissionEnum $permission, $project = null) {
            // Determine the effective role for this check
            $effectiveRole = $user->role->value;

            if ($project && str_starts_with($permission->value, 'project.')) {
                $perm = $project->permission;
                if (!$perm) return false;

                if ($perm->isOwner($user)) {
                    $effectiveRole = $user->role->value;
                } elseif (in_array($user->id, $perm->collaborators ?? [])) {
                    $effectiveRole = 'collaborator';
                } else {
                    return false;
                }
            }

            return User::permissionsForRole($effectiveRole)->contains($permission->value);
        });
    }
}
