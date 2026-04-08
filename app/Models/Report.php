<?php

namespace App\Models;

use App\AiContextType;
use App\ReportStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'content',
        'is_ai',
        'preset',
        'custom_prompt',
        'include_heatmaps',
        'aspect_date_from',
        'aspect_date_to',
        'ai_model_name',
        'status',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'is_ai' => 'boolean',
            'include_heatmaps' => 'boolean',
            'aspect_date_from' => 'date',
            'aspect_date_to' => 'date',
            'status' => ReportStatusEnum::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function contextPreset(): BelongsTo
    {
        return $this->belongsTo(AiContext::class, 'preset', 'slug')
            ->where('type', AiContextType::PRESET);
    }

    public function scopeAiReports($query)
    {
        return $query->where('is_ai', true);
    }

    public function scopeUserReports($query)
    {
        return $query->where('is_ai', false);
    }
}
