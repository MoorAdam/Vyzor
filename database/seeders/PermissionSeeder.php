<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Users\Enums\UserRoleEnum;
use App\Modules\Users\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Permissions assigned to the web role.
     * Customer gets no rows (no access by default).
     */
    private const WEB_PERMISSIONS = [
        // Basics
        PermissionEnum::VIEW_PROJECTS,

        // Users — view list + customer management only
        PermissionEnum::VIEW_USERS,
        PermissionEnum::VIEW_CUSTOMERS,
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

        PermissionEnum::VIEW_CLARITY_SNAPSHOTS,
        PermissionEnum::VIEW_CLARITY_TRENDS,
        PermissionEnum::FETCH_CLARITY_DATA,

        PermissionEnum::VIEW_REPORTS,
        PermissionEnum::CREATE_REPORT,
        PermissionEnum::EDIT_REPORT,
        PermissionEnum::DELETE_REPORT,

        PermissionEnum::UPLOAD_HEATMAP,
        PermissionEnum::VIEW_HEATMAPS,
        PermissionEnum::EDIT_HEATMAPS,
        PermissionEnum::DELETE_HEATMAPS,
    ];

    private const CONTEXT_MANAGER_PERMISSIONS = [
        PermissionEnum::VIEW_CONTEXTS,
        PermissionEnum::ADD_CONTEXTS,
        PermissionEnum::EDIT_CONTEXTS,
        PermissionEnum::DELETE_CONTEXTS,
    ];

    private const AGENT_MANAGER_PERMISSIONS = [
        PermissionEnum::VIEW_AGENTS,
        PermissionEnum::ADD_AGENTS,
        PermissionEnum::EDIT_AGENTS,
        PermissionEnum::DELETE_AGENTS,
    ];

    /**
     * Returns every permission case from the enum. Used to grant the full
     * permission set to roles that are functionally all-powerful.
     */
    private function allPermissions(): array
    {
        return PermissionEnum::cases();
    }

    /**
     * System role definitions: slug => [label, description, visible].
     * `is_system = true` means the role cannot be renamed or deleted from the UI.
     * `visible = false` hides the role from the management UI lists.
     */
    private const SYSTEM_ROLES = [
        'admin' => ['Admin', 'Full access — bypasses all permission checks.', false],
        'overseer' => ['Overseer', 'Holds every permission explicitly. Functionally equivalent to Admin, but goes through normal permission checks instead of bypassing them.', true],
        'web' => ['Web', 'Internal web user with default permissions.', true],
        'customer' => ['Customer', 'External user; no access until granted.', false],
        'context_manager' => ['Context Manager', 'Can view, create, edit and delete AI contexts.', true],
        'agent_manager' => ['Agent Manager', 'Can view, create, edit and delete AI agent configurations.', true],
        'collaborator' => ['Collaborator', 'Virtual project-level role for project collaborators.', true],
    ];

    public function run(): void
    {
        // 1. Upsert all permissions from the enum. All permissions are visible
        //    by default; admins can hide individual ones from the UI later.
        foreach (PermissionEnum::cases() as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission->value],
                [
                    'group' => $permission->group(),
                    'description' => $permission->description(),
                    'visible' => true,
                ],
            );
        }

        // 2. Drop slugs that no longer exist in the enum.
        $validSlugs = array_map(fn ($p) => $p->value, PermissionEnum::cases());
        Permission::whereNotIn('slug', $validSlugs)->delete();

        // 3. Upsert system roles. Only label/description/visibility are touched
        //    so existing user-created roles aren't disturbed.
        foreach (self::SYSTEM_ROLES as $slug => [$label, $description, $visible]) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $label,
                    'description' => $description,
                    'is_system' => true,
                    'visible' => $visible,
                ],
            );
        }

        // 4. Bind permissions to roles via the role_permission pivot.
        $allPermissions = Permission::pluck('id', 'slug');

        $this->seedRole(UserRoleEnum::ADMIN->value, $this->allPermissions(), $allPermissions);
        $this->seedRole('overseer', $this->allPermissions(), $allPermissions);
        $this->seedRole(UserRoleEnum::WEB->value, self::WEB_PERMISSIONS, $allPermissions);
        $this->seedRole('context_manager', self::CONTEXT_MANAGER_PERMISSIONS, $allPermissions);
        $this->seedRole('agent_manager', self::AGENT_MANAGER_PERMISSIONS, $allPermissions);
        $this->seedRole('collaborator', self::COLLABORATOR_PERMISSIONS, $allPermissions);
    }

    private function seedRole(string $role, array $permissions, $allPermissions): void
    {
        $desiredIds = [];
        foreach ($permissions as $permission) {
            $id = $allPermissions[$permission->value] ?? null;
            if ($id) {
                $desiredIds[] = $id;
                DB::table('role_permission')->updateOrInsert(
                    ['role' => $role, 'permission_id' => $id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        // Drop stale grants so system role definitions stay authoritative —
        // shrinking a constant like WEB_PERMISSIONS now revokes the dropped
        // permission instead of leaving it orphaned in role_permission.
        DB::table('role_permission')
            ->where('role', $role)
            ->whereNotIn('permission_id', $desiredIds ?: [0])
            ->delete();
    }
}
