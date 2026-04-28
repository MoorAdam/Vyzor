<?php

namespace App\Modules\Users\Models;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'description',
        'is_system',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'visible' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'role',
            'permission_id',
            'slug',
            'id',
        )->withTimestamps();
    }

    public function syncPermissions(array $permissionIds): void
    {
        DB::transaction(function () use ($permissionIds) {
            DB::table('role_permission')->where('role', $this->slug)->delete();

            $now = now();
            $rows = collect($permissionIds)
                ->unique()
                ->map(fn ($id) => [
                    'role' => $this->slug,
                    'permission_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if (! empty($rows)) {
                DB::table('role_permission')->insert($rows);
            }
        });
    }
}
