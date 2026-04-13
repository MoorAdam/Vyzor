<?php

namespace App\Models;

use App\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'owner_id',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
