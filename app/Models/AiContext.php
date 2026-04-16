<?php

namespace App\Models;

use App\AiContextType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiContext extends Model
{
    protected $fillable = [
        'name',
        'name_hu',
        'slug',
        'type',
        'models',
        'tags',
        'icon',
        'label_color',
        'description',
        'description_hu',
        'context',
        'is_active',
        'sort_order',
    ];

    public function localizedName(): string
    {
        if (app()->getLocale() === 'hu' && $this->name_hu) {
            return $this->name_hu;
        }

        return $this->name;
    }

    public function localizedDescription(): ?string
    {
        if (app()->getLocale() === 'hu' && $this->description_hu) {
            return $this->description_hu;
        }

        return $this->description;
    }

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
