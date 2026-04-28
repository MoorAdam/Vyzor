<?php

namespace App\Modules\Ai\Contexts\Models;

use App\Modules\Ai\Contexts\Enums\AiContextType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiContext extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'models',
        'tags',
        'icon',
        'label_color',
        'description',
        'context',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiContextType::class,
            'models' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $context) {
            if (empty($context->slug)) {
                $context->slug = Str::slug($context->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeOfType($query, AiContextType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForModel($query, ?string $model = null)
    {
        $model = $model ?? config('ai.default', 'openai');

        return $query->where(function ($q) use ($model) {
            $q->whereNull('models')
              ->orWhereJsonContains('models', 'all')
              ->orWhereJsonContains('models', $model);
        });
    }

    public function appliesToModel(?string $model = null): bool
    {
        $model = $model ?? config('ai.default', 'openai');

        if (empty($this->models) || in_array('all', $this->models)) {
            return true;
        }

        return in_array($model, $this->models);
    }
}
