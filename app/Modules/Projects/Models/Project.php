<?php

namespace App\Modules\Projects\Models;

use App\Modules\Projects\Enums\ProjectStatusEnum;
use App\Models\User;
use App\Modules\Users\Enums\PermissionEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'customer_id',
        'status',
        'domain',
        'clarity_api_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
            'clarity_api_key' => 'encrypted',
        ];
    }

    public function permission(): HasOne
    {
        return $this->hasOne(ProjectPermission::class);
    }

    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, ProjectPermission::class, 'project_id', 'id', 'id', 'owner_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $userPermissions = User::permissionsForRoles($user->roles ?? []);
        if ($userPermissions->contains(PermissionEnum::VIEW_ALL_PROJECTS->value)
            || $userPermissions->contains(PermissionEnum::EDIT_ALL_PROJECTS->value)) {
            return $query;
        }

        return $query->whereHas('permission', function ($q) use ($user) {
            $q->where('owner_id', $user->id)
              ->orWhereJsonContains('collaborators', $user->id);
        });
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('permission', fn ($q) => $q->where('owner_id', $user->id));
    }

    public function scopeCollaboratingWith(Builder $query, User $user): Builder
    {
        return $query->whereHas('permission', fn ($q) => $q->whereJsonContains('collaborators', $user->id));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * The currently active project for the session, or null if none is set.
     * Memoized per-request via once().
     */
    public static function current(): ?self
    {
        return once(function () {
            $id = session('current_project_id');
            return $id ? self::find($id) : null;
        });
    }

    public function hasClarityKey(): bool
    {
        return filled($this->clarity_api_key);
    }
}
