<?php

namespace App;

enum ProjectStatusEnum: string
{
    case ACTIVE = 'active';
    case ABORTED = 'aborted';
    case POSTPONED = 'postponed';
    case COMPLETED = 'completed';
    case PRESENTATION = 'presentation';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::ABORTED => __('Aborted'),
            self::POSTPONED => __('Postponed'),
            self::PRESENTATION => __('Presentation'),
            self::COMPLETED => __('Completed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'blue',
            self::COMPLETED => 'green',
            self::ABORTED => 'red',
            self::POSTPONED => 'amber',
            self::PRESENTATION => 'violet',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::ACTIVE => '#3b82f6',
            self::COMPLETED => '#22c55e',
            self::ABORTED => '#ef4444',
            self::POSTPONED => '#f59e0b',
            self::PRESENTATION => '#8b5cf6',
        };
    }
}
