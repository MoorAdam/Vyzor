<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\Permission;
use App\Models\User;
use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Users\Enums\UserRoleEnum;
use App\Modules\Users\Models\Role;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {

    public string $activeTab = 'users'; // 'users' | 'roles'

    // ── User edit state ────────────────────────────────────────────
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $editName = '';

    #[Validate('required|email|max:255')]
    public string $editEmail = '';

    #[Validate('nullable|string|max:255')]
    public string $editCompanyName = '';

    #[Validate('nullable|string|max:255')]
    public string $editPhone = '';

    #[Validate('nullable|string|min:8|confirmed')]
    public string $editPassword = '';

    public string $editPassword_confirmation = '';

    // ── Role edit state (Roles tab) ────────────────────────────────
    public bool $showRoleForm = false;
    public ?int $editingRoleId = null;
    public string $roleSlug = '';
    public string $roleLabel = '';
    public string $roleDescription = '';
    public array $rolePermissionIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::VIEW_USERS), 403);
    }

    public function setTab(string $tab): void
    {
        if (! \in_array($tab, ['users', 'roles'], true)) {
            return;
        }

        if ($tab === 'roles' && auth()->user()->cannot('permission', PermissionEnum::VIEW_ROLES)) {
            return;
        }

        $this->activeTab = $tab;
        $this->cancelRoleForm();
    }

    // ── User editing ───────────────────────────────────────────────

    public function startEditing(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;

        if ($user->isCustomer()) {
            $this->editCompanyName = $user->customerProfile?->company_name ?? '';
            $this->editPhone = $user->customerProfile?->phone ?? '';
        }
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->editPassword = '';
        $this->editPassword_confirmation = '';
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingId);
        $perm = $user->isCustomer() ? PermissionEnum::EDIT_CUSTOMER : PermissionEnum::EDIT_USER;
        abort_unless(auth()->user()->can('permission', $perm), 403);

        $data = [
            'name' => $this->editName,
            'email' => $this->editEmail,
        ];

        if (filled($this->editPassword)) {
            $data['password'] = $this->editPassword;
        }

        $user->update($data);

        if ($user->isCustomer() && $user->customerProfile) {
            $user->customerProfile->update([
                'company_name' => $this->editCompanyName,
                'phone' => $this->editPhone ?: null,
            ]);
        }

        $this->editingId = null;
        $this->editPassword = '';
        $this->editPassword_confirmation = '';
        $this->resetValidation();
    }

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $perm = $user->isCustomer() ? PermissionEnum::REMOVE_CUSTOMER : PermissionEnum::REMOVE_USER;
        abort_unless(auth()->user()->can('permission', $perm), 403);
        $user->delete();
    }

    // ── Role assignment on user rows ───────────────────────────────

    public function toggleUserRole(int $userId, string $roleSlug): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::ASSIGN_ROLES), 403);

        // Customer role can't be toggled from this UI — it's set at creation.
        if ($roleSlug === UserRoleEnum::CUSTOMER->value) {
            return;
        }

        $user = User::findOrFail($userId);
        $current = $user->roles ?? [];

        $user->roles = \in_array($roleSlug, $current, true)
            ? array_values(array_diff($current, [$roleSlug]))
            : array_values(array_unique([...$current, $roleSlug]));

        $user->save();
    }

    // ── Role + permission management (Roles tab) ───────────────────

    public function openRoleForm(?int $roleId = null): void
    {
        $perm = $roleId ? PermissionEnum::EDIT_ROLES : PermissionEnum::ADD_ROLES;
        abort_unless(auth()->user()->can('permission', $perm), 403);

        if ($roleId) {
            $role = Role::with('permissions')->findOrFail($roleId);

            if ($role->slug === UserRoleEnum::ADMIN->value) {
                session()->flash('error', __('The admin role cannot be edited.'));
                return;
            }

            $this->editingRoleId = $role->id;
            $this->roleSlug = $role->slug;
            $this->roleLabel = $role->label;
            $this->roleDescription = $role->description ?? '';
            $this->rolePermissionIds = $role->permissions->pluck('id')->all();
        } else {
            $this->editingRoleId = null;
            $this->roleSlug = '';
            $this->roleLabel = '';
            $this->roleDescription = '';
            $this->rolePermissionIds = [];
        }

        $this->showRoleForm = true;
    }

    public function cancelRoleForm(): void
    {
        $this->showRoleForm = false;
        $this->editingRoleId = null;
        $this->roleSlug = '';
        $this->roleLabel = '';
        $this->roleDescription = '';
        $this->rolePermissionIds = [];
    }

    public function togglePermission(int $permissionId): void
    {
        $this->rolePermissionIds = \in_array($permissionId, $this->rolePermissionIds, true)
            ? array_values(array_diff($this->rolePermissionIds, [$permissionId]))
            : [...$this->rolePermissionIds, $permissionId];
    }

    public function saveRole(): void
    {
        $perm = $this->editingRoleId ? PermissionEnum::EDIT_ROLES : PermissionEnum::ADD_ROLES;
        abort_unless(auth()->user()->can('permission', $perm), 403);

        $this->validate([
            'roleSlug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                $this->editingRoleId
                    ? 'unique:roles,slug,' . $this->editingRoleId
                    : 'unique:roles,slug',
            ],
            'roleLabel' => 'required|string|max:128',
            'roleDescription' => 'nullable|string|max:500',
            'rolePermissionIds' => 'array',
            'rolePermissionIds.*' => 'integer|exists:permissions,id',
        ]);

        if ($this->editingRoleId) {
            $role = Role::findOrFail($this->editingRoleId);

            if ($role->slug === UserRoleEnum::ADMIN->value) {
                session()->flash('error', __('The admin role cannot be edited.'));
                return;
            }

            $data = [
                'label' => $this->roleLabel,
                'description' => $this->roleDescription ?: null,
            ];
            if (! $role->is_system) {
                $data['slug'] = $this->roleSlug;
            }
            $role->update($data);
        } else {
            $role = Role::create([
                'slug' => $this->roleSlug,
                'label' => $this->roleLabel,
                'description' => $this->roleDescription ?: null,
                'is_system' => false,
            ]);
        }

        $role->syncPermissions($this->rolePermissionIds);

        session()->flash('success', __('Role saved.'));
        $this->cancelRoleForm();
    }

    public function deleteRole(int $roleId): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::DELETE_ROLES), 403);

        $role = Role::findOrFail($roleId);

        if ($role->is_system) {
            session()->flash('error', __('System roles cannot be deleted.'));
            return;
        }

        // Cascade: detach the role from any user that holds it.
        foreach (User::whereJsonContains('roles', $role->slug)->get() as $user) {
            $user->roles = array_values(array_diff($user->roles ?? [], [$role->slug]));
            $user->save();
        }

        \Illuminate\Support\Facades\DB::table('role_permission')->where('role', $role->slug)->delete();
        $role->delete();

        session()->flash('success', __('Role deleted.'));
    }

    public function updatedRoleLabel(): void
    {
        if (! $this->editingRoleId && empty($this->roleSlug)) {
            $this->roleSlug = (string) Str::of($this->roleLabel)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
        }
    }

    public function with(): array
    {
        // "Users" = anyone who isn't a customer. The `web` role is an implicit
        // default for non-customers, so we filter on the absence of `customer`
        // rather than the presence of `web`.
        $nonCustomers = User::query()
            ->where(function ($q) {
                $q->whereJsonDoesntContain('roles', UserRoleEnum::CUSTOMER->value)
                    ->orWhereNull('roles');
            })
            ->get();

        return [
            'users' => $nonCustomers,
            'customers' => User::whereJsonContains('roles', UserRoleEnum::CUSTOMER->value)->with('customerProfile')->get(),
            // Chips on user rows: hide `customer` (created via /register only)
            // and `collaborator` (project-virtual). Also respect the per-row
            // `visible` flag so admins can hide structural roles from the UI.
            'assignableRoles' => Role::query()
                ->where('visible', true)
                ->whereNotIn('slug', [
                    UserRoleEnum::CUSTOMER->value,
                    'collaborator',
                ])
                ->orderBy('is_system', 'desc')
                ->orderBy('label')
                ->get(),
            'allRoles' => Role::query()
                ->where('visible', true)
                ->orderBy('is_system', 'desc')
                ->orderBy('label')
                ->get(),
            'groupedPermissions' => Permission::query()
                ->where('visible', true)
                ->orderBy('group')
                ->orderBy('slug')
                ->get()
                ->groupBy('group'),
            'canViewRoles' => auth()->user()->can('permission', PermissionEnum::VIEW_ROLES),
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Users & Customers') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Manage users, customer accounts, and access control.') }}</x-ui.description>
        </div>
        <x-ui.button href="{{ route('register') }}" variant="primary" icon="plus-circle" color="neutral"
            :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::CREATE_USER) && auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::CREATE_CUSTOMER)">
            {{ __('Add new user / customer') }}
        </x-ui.button>
    </div>

    @if (session('success'))
        <div class="p-3 rounded-md bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-900">
            <div class="flex items-center gap-2">
                <x-ui.icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                <span class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="p-3 rounded-md bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-900">
            <div class="flex items-center gap-2">
                <x-ui.icon name="warning-circle" class="size-5 text-red-600 dark:text-red-400" />
                <span class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 bg-neutral-100 dark:bg-neutral-900 rounded-lg p-1 w-fit">
        <button wire:click="setTab('users')"
            class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'users' ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
            {{ __('Users & Customers') }}
        </button>
        @if ($canViewRoles)
            <button wire:click="setTab('roles')"
                class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'roles' ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
                {{ __('Roles') }}
            </button>
        @endif
    </div>

    {{-- ── Tab 1: Users & Customers ───────────────────────────────── --}}
    @if ($activeTab === 'users')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            {{-- Users Column --}}
            <x-ui.card size="full">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="users" />
                    <x-ui.heading level="h2" size="sm">{{ __('Users') }}</x-ui.heading>
                    <x-ui.badge variant="solid" color="blue" size="sm">{{ $users->count() }}</x-ui.badge>
                </div>

                @if ($users->isNotEmpty())
                <div class="-mx-4 divide-y divide-neutral-200 dark:divide-neutral-800">
                @foreach ($users as $user)
                    <div class="px-4 py-3">
                        @if ($editingId === $user->id)
                            <form wire:submit="saveEdit" class="flex-1 space-y-3">
                                <x-ui.field>
                                    <x-ui.label>{{ __('Name') }}</x-ui.label>
                                    <x-ui.input wire:model="editName" :placeholder="__('Name...')" :invalid="$errors->has('editName')" />
                                    <x-ui.error name="editName" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('Email') }}</x-ui.label>
                                    <x-ui.input wire:model="editEmail" type="email" :placeholder="__('Email...')"
                                        :invalid="$errors->has('editEmail')" />
                                    <x-ui.error name="editEmail" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('New Password') }}</x-ui.label>
                                    <x-ui.input wire:model="editPassword" type="password" :placeholder="__('Leave blank to keep current...')"
                                        :invalid="$errors->has('editPassword')" />
                                    <x-ui.error name="editPassword" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('Confirm Password') }}</x-ui.label>
                                    <x-ui.input wire:model="editPassword_confirmation" type="password" :placeholder="__('Confirm password...')" />
                                </x-ui.field>
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" size="xs" variant="primary">{{ __('Save') }}</x-ui.button>
                                    <x-ui.button type="button" size="xs" variant="ghost"
                                        wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                                </div>
                            </form>
                        @else
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    <x-ui.avatar :name="$user->name" size="sm" />
                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        <div>
                                            <p class="font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ $user->name }}</p>
                                            <p class="text-sm text-neutral-500 dark:text-neutral-400 truncate">{{ $user->email }}</p>
                                        </div>
                                        @if ($canViewRoles)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($assignableRoles as $role)
                                                    @php $assigned = \in_array($role->slug, $user->roles ?? [], true); @endphp
                                                    <button
                                                        wire:click="toggleUserRole({{ $user->id }}, '{{ $role->slug }}')"
                                                        :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::ASSIGN_ROLES)"
                                                        class="px-2 py-0.5 rounded-full text-[11px] font-medium border transition-colors {{ $assigned
                                                            ? 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:border-blue-900'
                                                            : 'bg-transparent text-neutral-500 border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800' }}">
                                                        {{ $role->label }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <x-ui.button size="xs" variant="ghost" icon="pencil"
                                        wire:click="startEditing({{ $user->id }})" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_USER)">{{ __('Edit') }}</x-ui.button>
                                    <x-ui.modal.trigger :id="'delete-user-' . $user->id">
                                        <x-ui.button size="xs" variant="ghost" icon="trash" color="red" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::REMOVE_USER)">{{ __('Delete') }}</x-ui.button>
                                    </x-ui.modal.trigger>
                                </div>
                            </div>
                        @endif
                    </div>

                    <x-ui.modal :id="'delete-user-' . $user->id" :title="__('Delete User')" size="sm" centered>
                        <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $user->name]) !!}</x-ui.text>
                        <x-slot:footer>
                            <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button variant="danger" wire:click="deleteUser({{ $user->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>
                @endforeach
                </div>
                @else
                    <x-ui.empty>
                        <x-ui.empty.contents>
                            <x-ui.text>{{ __('No users found.') }}</x-ui.text>
                        </x-ui.empty.contents>
                    </x-ui.empty>
                @endif
            </x-ui.card>

            {{-- Customers Column --}}
            <x-ui.card size="full">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="ps:buildings" />
                    <x-ui.heading level="h2" size="sm">{{ __('Customers') }}</x-ui.heading>
                    <x-ui.badge variant="solid" color="emerald" size="sm">{{ $customers->count() }}</x-ui.badge>
                </div>

                @if ($customers->isNotEmpty())
                <div class="-mx-4 divide-y divide-neutral-200 dark:divide-neutral-800">
                @foreach ($customers as $customer)
                    <div class="px-4 flex items-center justify-between py-3">
                        @if ($editingId === $customer->id)
                            <form wire:submit="saveEdit" class="flex-1 space-y-3">
                                <x-ui.field>
                                    <x-ui.label>{{ __('Company Name') }}</x-ui.label>
                                    <x-ui.input wire:model="editCompanyName" :placeholder="__('Company name...')"
                                        :invalid="$errors->has('editCompanyName')" />
                                    <x-ui.error name="editCompanyName" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('Email') }}</x-ui.label>
                                    <x-ui.input wire:model="editEmail" type="email" :placeholder="__('Email...')"
                                        :invalid="$errors->has('editEmail')" />
                                    <x-ui.error name="editEmail" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('Phone') }}</x-ui.label>
                                    <x-ui.input wire:model="editPhone" type="tel" :placeholder="__('Phone...')" />
                                    <x-ui.error name="editPhone" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('New Password') }}</x-ui.label>
                                    <x-ui.input wire:model="editPassword" type="password" :placeholder="__('Leave blank to keep current...')"
                                        :invalid="$errors->has('editPassword')" />
                                    <x-ui.error name="editPassword" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.label>{{ __('Confirm Password') }}</x-ui.label>
                                    <x-ui.input wire:model="editPassword_confirmation" type="password" :placeholder="__('Confirm password...')" />
                                </x-ui.field>
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" size="xs" variant="primary">{{ __('Save') }}</x-ui.button>
                                    <x-ui.button type="button" size="xs" variant="ghost"
                                        wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                                </div>
                            </form>
                        @else
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$customer->name" size="sm" color="emerald" />
                                <div>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ $customer->customerProfile?->company_name ?? $customer->name }}</p>
                                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $customer->email }}</p>
                                    @if ($customer->customerProfile?->phone)
                                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                            {{ $customer->customerProfile->phone }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <x-ui.button size="xs" variant="ghost" icon="pencil"
                                    wire:click="startEditing({{ $customer->id }})" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_CUSTOMER)">{{ __('Edit') }}</x-ui.button>
                                <x-ui.modal.trigger :id="'delete-customer-' . $customer->id">
                                    <x-ui.button size="xs" variant="ghost" icon="trash" color="red" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::REMOVE_CUSTOMER)">{{ __('Delete') }}</x-ui.button>
                                </x-ui.modal.trigger>
                            </div>
                        @endif
                    </div>

                    <x-ui.modal :id="'delete-customer-' . $customer->id" :title="__('Delete Customer')" size="sm" centered>
                        <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $customer->customerProfile?->company_name ?? $customer->name]) !!}</x-ui.text>
                        <x-slot:footer>
                            <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button variant="danger" wire:click="deleteUser({{ $customer->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>
                @endforeach
                </div>
                @else
                    <x-ui.empty>
                        <x-ui.empty.contents>
                            <x-ui.text>{{ __('No customers found.') }}</x-ui.text>
                        </x-ui.empty.contents>
                    </x-ui.empty>
                @endif
            </x-ui.card>
        </div>
    @endif

    {{-- ── Tab 2: Roles ──────────────────────────────────────────── --}}
    @if ($activeTab === 'roles' && $canViewRoles)
        @if (! $showRoleForm)
            <div class="flex items-center justify-between">
                <div>
                    <x-ui.heading level="h2" size="lg">{{ __('Roles') }}</x-ui.heading>
                    <x-ui.description class="mt-1">{{ __('Manage roles and the permissions they grant. Removing a role detaches it from all users.') }}</x-ui.description>
                </div>
                <x-ui.button color="blue" icon="plus" wire:click="openRoleForm"
                    :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::ADD_ROLES)">
                    {{ __('New Role') }}
                </x-ui.button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach ($allRoles as $role)
                    @php $isAdmin = $role->slug === UserRoleEnum::ADMIN->value; @endphp
                    <div class="rounded-lg border border-black/10 dark:border-white/10 bg-white dark:bg-neutral-900 p-3 flex flex-col gap-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-sm text-neutral-900 dark:text-neutral-100 truncate">{{ $role->label }}</div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="text-[11px] font-mono text-neutral-500">{{ $role->slug }}</span>
                                    @if ($role->is_system)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500 uppercase tracking-wide">{{ __('system') }}</span>
                                    @endif
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300">
                                        {{ __(':n perms', ['n' => $role->permissions()->count()]) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-0.5 shrink-0">
                                @unless ($isAdmin)
                                    <button
                                        wire:click="openRoleForm({{ $role->id }})"
                                        :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_ROLES)"
                                        class="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors p-1.5 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                        :title="__('Edit')">
                                        <x-ui.icon name="pencil-simple" class="size-4" />
                                    </button>
                                @endunless
                                @unless ($role->is_system)
                                    <button
                                        wire:click="deleteRole({{ $role->id }})"
                                        wire:confirm="{{ __('Delete role \':name\' and remove it from all users?', ['name' => $role->label]) }}"
                                        :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::DELETE_ROLES)"
                                        class="text-neutral-400 hover:text-red-500 transition-colors p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-950/30"
                                        :title="__('Delete')">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                @endunless
                            </div>
                        </div>
                        @if ($role->description)
                            <div class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-3">{{ $role->description }}</div>
                        @endif
                        @if ($isAdmin)
                            <div class="text-[11px] text-neutral-400 italic mt-1">{{ __('Locked — bypasses all permission checks.') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.card size="full">
                <form wire:submit.prevent="saveRole" class="space-y-4">
                    <x-ui.heading level="h3" size="md">
                        {{ $editingRoleId ? __('Edit Role') : __('New Role') }}
                    </x-ui.heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui.field required>
                            <x-ui.label>{{ __('Label') }}</x-ui.label>
                            <x-ui.input wire:model.live="roleLabel" type="text" :invalid="$errors->has('roleLabel')" />
                            <x-ui.error name="roleLabel" />
                        </x-ui.field>
                        <x-ui.field required>
                            <x-ui.label>{{ __('Slug') }}</x-ui.label>
                            <x-ui.input wire:model="roleSlug" type="text" :invalid="$errors->has('roleSlug')"
                                :disabled="$editingRoleId && $allRoles->firstWhere('id', $editingRoleId)?->is_system" />
                            <x-ui.error name="roleSlug" />
                        </x-ui.field>
                    </div>

                    <x-ui.field>
                        <x-ui.label>{{ __('Description') }}</x-ui.label>
                        <x-ui.input wire:model="roleDescription" type="text" :invalid="$errors->has('roleDescription')" />
                        <x-ui.error name="roleDescription" />
                    </x-ui.field>

                    <div>
                        <x-ui.heading level="h4" size="sm" class="mb-2">{{ __('Permissions') }}</x-ui.heading>
                        <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 border border-neutral-200 dark:border-neutral-800 rounded-md p-3">
                            @foreach ($groupedPermissions as $group => $perms)
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">{{ $group }}</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
                                        @foreach ($perms as $perm)
                                            @php $checked = \in_array($perm->id, $rolePermissionIds, true); @endphp
                                            <label class="flex items-start gap-2 cursor-pointer text-sm py-1 px-2 rounded hover:bg-neutral-50 dark:hover:bg-neutral-900">
                                                <input
                                                    type="checkbox"
                                                    wire:click="togglePermission({{ $perm->id }})"
                                                    @checked($checked)
                                                    class="mt-0.5"
                                                />
                                                <span class="flex-1">
                                                    <span class="font-mono text-xs text-neutral-500">{{ $perm->slug }}</span>
                                                    @if ($perm->description)
                                                        <span class="block text-xs text-neutral-400">{{ $perm->description }}</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="outline" color="neutral" wire:click="cancelRoleForm">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" color="blue">
                            {{ __('Save') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    @endif
</div>
