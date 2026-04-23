<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\PermissionEnum;
use App\UserRoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Permissions assigned to the web role.
     * Admin gets no rows (Gate::before handles bypass).
     * Customer gets no rows (no access).
     */
    private const WEB_PERMISSIONS = [
        // Basics
        PermissionEnum::VIEW_PROJECTS,

        // Users — view list + customer management only
        PermissionEnum::VIEW_USERS,
        PermissionEnum::CREATE_CUSTOMER,
        PermissionEnum::EDIT_CUSTOMER,
        PermissionEnum::REMOVE_CUSTOMER,

        // Project — own
        PermissionEnum::VIEW_OWNED_PROJECTS,
        PermissionEnum::VIEW_COLLAB_PROJECTS,
        PermissionEnum::CHANGE_PROJECT_STATUS,
        PermissionEnum::EDIT_PROJECT_DETAILS,
        PermissionEnum::DELETE_PROJECT,
        PermissionEnum::CREATE_PROJECT,

        // Clarity
        PermissionEnum::VIEW_CLARITY_SNAPSHOTS,
        PermissionEnum::VIEW_CLARITY_TRENDS,
        PermissionEnum::FETCH_CLARITY_DATA,

        // Report
        PermissionEnum::VIEW_REPORTS,
        PermissionEnum::CREATE_REPORT,
        PermissionEnum::EDIT_REPORT,
        PermissionEnum::DELETE_REPORT,

        // Heatmap
        PermissionEnum::UPLOAD_HEATMAP,
        PermissionEnum::VIEW_HEATMAPS,
        PermissionEnum::EDIT_HEATMAPS,
        PermissionEnum::DELETE_HEATMAPS,

        // Context
        PermissionEnum::VIEW_CONTEXTS,
        PermissionEnum::EDIT_CONTEXTS,
        PermissionEnum::ADD_CONTEXTS,
    ];

    /**
     * Collaborator permissions — same as web but scoped to the project.
     * Cannot edit project properties or delete the project.
     */
    private const COLLABORATOR_PERMISSIONS = [
        PermissionEnum::VIEW_PROJECTS,
        PermissionEnum::VIEW_COLLAB_PROJECTS,
        PermissionEnum::CHANGE_PROJECT_STATUS,
        PermissionEnum::EDIT_PROJECT_DETAILS,

        // Clarity
        PermissionEnum::VIEW_CLARITY_SNAPSHOTS,
        PermissionEnum::VIEW_CLARITY_TRENDS,
        PermissionEnum::FETCH_CLARITY_DATA,

        // Report
        PermissionEnum::VIEW_REPORTS,
        PermissionEnum::CREATE_REPORT,
        PermissionEnum::EDIT_REPORT,
        PermissionEnum::DELETE_REPORT,

        // Heatmap
        PermissionEnum::UPLOAD_HEATMAP,
        PermissionEnum::VIEW_HEATMAPS,
        PermissionEnum::EDIT_HEATMAPS,
        PermissionEnum::DELETE_HEATMAPS,
    ];

    public function run(): void
    {
        // Upsert all permissions from the enum
        foreach (PermissionEnum::cases() as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission->value],
                [
                    'group' => $permission->group(),
                    'description' => $permission->description(),
                ],
            );
        }

        $allPermissions = Permission::pluck('id', 'slug');

        // Seed web role permissions
        $this->seedRole(UserRoleEnum::WEB->value, self::WEB_PERMISSIONS, $allPermissions);

        // Seed collaborator role permissions
        $this->seedRole('collaborator', self::COLLABORATOR_PERMISSIONS, $allPermissions);
    }

    private function seedRole(string $role, array $permissions, $allPermissions): void
    {
        foreach ($permissions as $permission) {
            $id = $allPermissions[$permission->value] ?? null;
            if ($id) {
                DB::table('role_permission')->updateOrInsert(
                    ['role' => $role, 'permission_id' => $id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }
}
