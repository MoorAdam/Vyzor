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
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::GENERATING => 'Generating',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
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
