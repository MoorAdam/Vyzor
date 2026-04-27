<?php

namespace App\Models;

use App\Modules\Users\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'roles',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
        ];
    }

    public function hasRole(UserRoleEnum $role): bool
    {
        return \in_array($role->value, $this->roles ?? [], true);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(UserRoleEnum::CUSTOMER);
    }

    public function isUser(): bool
    {
        return $this->hasRole(UserRoleEnum::WEB);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRoleEnum::ADMIN);
    }

    public function profile(): HasOne
    {
        return $this->isCustomer()
            ? $this->hasOne(CustomerProfile::class)
            : $this->hasOne(UserProfile::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Cached permission slugs for a single role.
     */
    public static function permissionsForRole(string $role): Collection
    {
        static $cache = [];

        return $cache[$role] ??= DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role', $role)
            ->pluck('permissions.slug');
    }

    /**
     * Union of permission slugs across multiple roles.
     */
    public static function permissionsForRoles(array $roles): Collection
    {
        return collect($roles)
            ->flatMap(fn (string $role) => static::permissionsForRole($role)->all())
            ->unique()
            ->values();
    }

    public function rolePermissions(): Collection
    {
        return static::permissionsForRoles($this->roles ?? []);
    }
}
