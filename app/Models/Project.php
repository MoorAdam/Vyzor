<?php

namespace App\Models;

use App\ProjectStatusEnum;
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
