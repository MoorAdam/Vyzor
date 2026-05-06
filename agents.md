# Agent Rules

## Documentation

- Documentation lives under `docs/` — see [docs/README.md](docs/README.md) for the layout.
- When code structure changes, update `docs/dev/project-structure.md`. When a new library or dependency is introduced, update `docs/dev/tech.md`.
- New docs go into `docs/dev/` (current state), `docs/plans/` (proposals / future work), or `docs/usage/` (end-user). Don't drop loose `*.md` at the repo root.

## Git

- Never run `git push` or any variant that pushes to a remote repository.

## Backend (PHP)

- Code must follow SOLID principles:
    - **S**ingle Responsibility — each class has one reason to change
    - **O**pen/Closed — open for extension, closed for modification
    - **L**iskov Substitution — subtypes must be substitutable for their base types
    - **I**nterface Segregation — prefer small, focused interfaces over large ones
    - **D**ependency Inversion — depend on abstractions, not concretions
- Follow PHP best practices: strict types, PSR standards, meaningful naming, and no magic where clarity is preferred.

## Frontend (JavaScript)

- Use the newest ECMAScript syntax and features available (ES2024+).
- Prefer native language features over external libraries where practical.
- Keep frontend solutions concise and understandable. If needed, use previosu solutions as example
- Always look for Sheaf UI components to use. If there isnt a right one, create a new component, or install from Sheaf UI.

## Localization

- **The app is primarily Hungarian.** Default `APP_LOCALE` and `APP_FALLBACK_LOCALE` are both `hu`. `lang/hu.json` is the primary translation file.
- Translation **keys are still English** (e.g. `__('Save Report')`) — that's the convention Laravel's JSON translator uses, and it makes the code readable. Only the rendered output is Hungarian.
- **Every new user-facing string MUST get a Hungarian translation in `lang/hu.json`.** If you add `__('New Thing')` in code, immediately add `"New Thing": "Új dolog"` to `hu.json`. A missing key falls back to the English key, which is a visible bug in production.
- `lang/en.json` is intentionally empty (`{}`) — keys fall through to themselves, which IS the English text. Don't fill it in unless we're adding real English-only overrides for some reason.
- Native Hungarian content (e.g. seeded AI prompts, preset names/descriptions in `database/seeders/AiContextSeeder.php`, the markdown files in `resources/ai-prompts/`) should be written in Hungarian directly. There is no `name_hu` / `description_hu` split anymore — it was removed in `2026_04_28_160311_drop_locale_fields_from_ai_contexts_table.php`.
- The locale switcher (EN/HU buttons in the layout headers) writes `session('locale', ...)` and the `SetLocale` middleware applies it per request. The session value overrides `APP_LOCALE` when set.

## UI Components

- Use **Sheaf UI** (`resources/views/components/ui/`) for all UI elements.
- If an HTML element is needed, search for existing Sheaf UI elements in the proejct. If not present, install it.
- Never introduce external UI libraries or write raw HTML when a Sheaf UI component exists.
- Components are prefixed with `x-ui.` (e.g., `<x-ui.button>`, `<x-ui.input>`, `<x-ui.fieldset>`).

## Permissions & Roles

Permissions and roles drive every access decision in the app. There are several ways the system *could* be structured; only the way described below is correct. Don't introduce parallel mechanisms.

### Mental model

- **Permissions are code-defined.** Every permission slug is a case in `App\Modules\Users\Enums\PermissionEnum` (in `app/Modules/Users/Enums/PermissionEnum.php`). The DB `permissions` table is just an upserted projection of that enum, used for joins.
- **Roles are DB-defined.** The `roles` table is the source of truth for what roles exist. New roles can be created at runtime from the `/users` page (Roles tab). The enum `App\Modules\Users\Enums\UserRoleEnum` lists **only** the three roles with hardcoded code behavior:
    - `ADMIN` — `Gate::before` permission bypass.
    - `CUSTOMER` — separate profile model (`CustomerProfile`), registration flow, and layout.
    - `WEB` — default permission bucket seeded for new non-customer users.
- **Users hold multiple roles.** `users.roles` is a JSON array of role slugs (`["web", "context_manager"]`). Cast as `'array'` on the `User` model. There is no single-role column — never reintroduce one.
- **Role-permission binding.** `role_permission` pivot keys roles by **string slug** (not by `roles.id`), and binds them to `permissions.id`. Custom roles created via UI work because the pivot accepts any slug — no enum entry required.
- **`collaborator` is a virtual project-scoped role.** It exists in the `roles` table for permission seeding only. It is never stored on `users.roles`. The `Gate` swaps a user's effective roles to `['collaborator']` when checking a `project.*` permission against a project where the user is a collaborator (not the owner).
- **`visible` flag** on `roles` and `permissions` controls **UI display only**. Permission checks ignore it. By default `admin` and `customer` are `visible=false` so they don't clutter the management UI.

### Checking a permission

Always go through the Gate. Never check role strings/enums in business logic.

```php
// Non-project permission
auth()->user()->can('permission', PermissionEnum::VIEW_USERS)

// Project-scoped permission (slugs starting with `project.`)
auth()->user()->can('permission', [PermissionEnum::VIEW_REPORTS, $project])
```

In Blade:
```blade
@can('permission', App\Modules\Users\Enums\PermissionEnum::VIEW_USERS)
:disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_USER)"
```

The Gate (`AppServiceProvider::boot`) handles admin bypass, owner/collaborator resolution for `project.*` perms, and unions permissions across all of the user's roles.

### Identifying user types

- `$user->isAdmin()` — has the `admin` role.
- `$user->isCustomer()` — has the `customer` role. Use this for layout/profile/registration branching, not for permission checks.
- `$user->isUser()` — convenience for "any non-customer logged-in account" (`!isCustomer()`). Used by `EnsureUserRole` middleware redirects.
- `$user->hasRole(UserRoleEnum::X)` / `$user->hasAnyRole([...])` — only when you need a specific role check (rare).

### Querying users by role

- "Internal users" = non-customers:
  ```php
  User::query()->where(function ($q) {
      $q->whereJsonDoesntContain('roles', UserRoleEnum::CUSTOMER->value)
        ->orWhereNull('roles');
  })
  ```
- "Customers":
  ```php
  User::whereJsonContains('roles', UserRoleEnum::CUSTOMER->value)
  ```
- **Never** use `User::where('role', ...)` — that column doesn't exist. Always `whereJsonContains` / `whereJsonDoesntContain` against `roles`.

### Adding a permission

1. Add a new case to `PermissionEnum` with a `value` slug like `<group>.<action>` (e.g. `agent.view`).
2. Map its `group()` (string-prefix match) and `description()`.
3. Decide which roles get it by editing the appropriate `*_PERMISSIONS` constant in `database/seeders/PermissionSeeder.php` (or wire it through the Roles tab UI for non-system roles).
4. Run `php artisan db:seed --class=PermissionSeeder`. The seeder upserts permissions, prunes slugs no longer in the enum, and re-binds role↔permission pivot rows.
5. The Roles tab perm-checkbox list reads from `Permission::where('visible', true)`, so the new permission appears automatically.

### Adding a role

- **System role** (always seeded, hardcoded label/description, can't be deleted): add to `SYSTEM_ROLES` in `PermissionSeeder.php` with `[label, description, visible]`. Add a permission constant if it should have a fixed perm set, then call `seedRole(...)` in `run()`. Only add a `UserRoleEnum` case if you're going to write **code that branches on it** — otherwise leave it as a plain DB-only role.
- **Custom role**: created from `/users` → Roles tab → New Role. Stored with `is_system=false`.

### Cascading role deletion

When a role is deleted (via `deleteRole()` in `⚡users.blade.php`), every user holding that slug has it stripped from their `roles` array, and the `role_permission` pivot rows for that slug are removed. Always perform both steps if you delete a role outside of that flow.

### Migrations

The user-role storage was migrated from a single `users.role` column to a `users.roles` JSON array. The conversion lives in `2026_04_27_000000_convert_users_role_to_roles_json.php` and must run before any new code that reads `roles`. The `roles` table itself is in `2026_04_27_000001_create_roles_table.php`. `visible` was added in `2026_04_28_000000_add_visible_to_roles_and_permissions.php`.

### Common pitfalls — don't do these

- Don't re-add a single-role column or a single-role enum check (`$user->role === UserRoleEnum::WEB`).
- Don't introduce a parallel role registry (e.g. config files, hardcoded arrays). The DB `roles` table is the only one.
- Don't gate page or component access on raw role checks (`isAdmin`, `isUser`) when a permission would do — perms are why the system exists.
- Don't enumerate `UserRoleEnum::cases()` to render role lists in the UI; query `Role::all()` (filtered by `visible` if it's a UI list).
- Don't let the seeder reset user-created roles. Only `SYSTEM_ROLES` are upserted; custom roles in the `roles` table are left untouched.
- Don't reuse `EDIT_CONTEXTS` / `ADD_CONTEXTS` / `MANAGE_CONTEXTS` etc. — the canonical set is the four-action split (`VIEW_*`, `ADD_*`, `EDIT_*`, `DELETE_*`) for `context.*` and `agent.*`, with `roles.*` and `permissions.*` for access management itself.
