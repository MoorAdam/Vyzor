<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Users\Enums\UserRoleEnum;
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

        // Context — view only by default
        PermissionEnum::VIEW_CONTEXTS,
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

    /**
     * Context manager — full read/write on AI contexts.
     * Stacks on top of the user's other roles via the multi-role system.
     */
    private const CONTEXT_MANAGER_PERMISSIONS = [
        PermissionEnum::VIEW_CONTEXTS,
        PermissionEnum::ADD_CONTEXTS,
        PermissionEnum::EDIT_CONTEXTS,
        PermissionEnum::DELETE_CONTEXTS,
    ];

    /**
     * Agent manager — full read/write on AI agent configurations.
     */
    private const AGENT_MANAGER_PERMISSIONS = [
        PermissionEnum::VIEW_AGENTS,
        PermissionEnum::ADD_AGENTS,
        PermissionEnum::EDIT_AGENTS,
        PermissionEnum::DELETE_AGENTS,
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

        // Drop slugs that no longer exist in the enum (e.g. context.edit, context.add).
        $validSlugs = array_map(fn ($p) => $p->value, PermissionEnum::cases());
        Permission::whereNotIn('slug', $validSlugs)->delete();

        $allPermissions = Permission::pluck('id', 'slug');

        $this->seedRole(UserRoleEnum::WEB->value, self::WEB_PERMISSIONS, $allPermissions);
        $this->seedRole(UserRoleEnum::CONTEXT_MANAGER->value, self::CONTEXT_MANAGER_PERMISSIONS, $allPermissions);
        $this->seedRole(UserRoleEnum::AGENT_MANAGER->value, self::AGENT_MANAGER_PERMISSIONS, $allPermissions);
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
