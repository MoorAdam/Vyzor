<?php

namespace App;

enum ReportStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PENDING => __('Pending'),
            self::GENERATING => __('Generating'),
            self::COMPLETED => __('Completed'),
            self::FAILED => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::PENDING => 'amber',
            self::GENERATING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
        };
    }
}
