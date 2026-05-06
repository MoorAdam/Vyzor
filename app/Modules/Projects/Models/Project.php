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
        'ga_property_id',
        'ga_last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
            'clarity_api_key' => 'encrypted',
            'ga_property_id' => 'encrypted',
            'ga_last_verified_at' => 'datetime',
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
     * Falls back to a long-lived cookie so the first render after login already
     * knows the project — without this the navlist briefly disables every
     * project-scoped item until the client hydrates the session.
     * Memoized per-request via once().
     */
    public static function current(): ?self
    {
        return once(function () {
            $id = session('current_project_id');

            if (!$id) {
                $cookieId = (int) request()->cookie('current_project_id');
                if (!$cookieId || !auth()->check()) {
                    return null;
                }

                $project = self::accessibleBy(auth()->user())->find($cookieId);
                if (!$project) {
                    cookie()->queue(cookie()->forget('current_project_id'));
                    return null;
                }

                session(['current_project_id' => $project->id]);
                return $project;
            }

            return self::find($id);
        });
    }

    public static function setCurrent(?int $id): void
    {
        if ($id === null) {
            session()->forget('current_project_id');
            cookie()->queue(cookie()->forget('current_project_id'));
            return;
        }

        session(['current_project_id' => $id]);
        cookie()->queue('current_project_id', (string) $id, 60 * 24 * 365);
    }

    public function hasClarityKey(): bool
    {
        return filled($this->clarity_api_key);
    }

    public function hasGoogleAnalytics(): bool
    {
        return filled($this->ga_property_id);
    }

    public function gaPropertyResource(): ?string
    {
        if (!$this->hasGoogleAnalytics()) {
            return null;
        }

        $id = $this->ga_property_id;
        return str_starts_with($id, 'properties/') ? $id : 'properties/' . $id;
    }
}
