<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPermission extends Model
{
    protected $fillable = [
        'project_id',
        'owner_id',
        'collaborators',
    ];

    protected function casts(): array
    {
        return [
            'collaborators' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hasAccess(User $user): bool
    {
        return $this->owner_id === $user->id
            || in_array($user->id, $this->collaborators ?? []);
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
