<?php

namespace App\Models;

use App\UserRoleEnum;
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
        'role',
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
            'role' => UserRoleEnum::class,
        ];
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRoleEnum::CUSTOMER;
    }

    public function isUser(): bool
    {
        return $this->role === UserRoleEnum::WEB;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRoleEnum::ADMIN;
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
     * Get cached permission slugs for a given role string.
     * Defaults to the user's own role.
     */
    public static function permissionsForRole(string $role): Collection
    {
        static $cache = [];

        return $cache[$role] ??= DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role', $role)
            ->pluck('permissions.slug');
    }

    public function rolePermissions(): Collection
    {
        return static::permissionsForRole($this->role->value);
    }
}
