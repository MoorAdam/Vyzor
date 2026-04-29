<?php

namespace App\Providers;

use App\Modules\Users\Enums\PermissionEnum;
use App\Models\User;
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

        // Generic permission gate — unions permissions across all of the user's roles.
        Gate::define('permission', function (User $user, PermissionEnum $permission, $project = null) {
            $effectiveRoles = $user->roles ?? [];

            if ($project && str_starts_with($permission->value, 'project.')) {
                $perm = $project->permission;
                if (!$perm) return false;

                if ($perm->isOwner($user)) {
                    // Owner uses their full role list.
                } elseif (\in_array($user->id, $perm->collaborators ?? [])) {
                    $effectiveRoles = ['collaborator'];
                } else {
                    // Not owner or collaborator — fall back to elevated all-projects permissions.
                    $userPermissions = User::permissionsForRoles($effectiveRoles);

                    if ($userPermissions->contains(PermissionEnum::EDIT_ALL_PROJECTS->value)) {
                        // edit-all bypass — user acts as owner on any project.
                    } elseif ($userPermissions->contains(PermissionEnum::VIEW_ALL_PROJECTS->value)
                        && str_contains($permission->value, 'view')) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }

            return User::permissionsForRoles($effectiveRoles)->contains($permission->value);
        });
    }
}
