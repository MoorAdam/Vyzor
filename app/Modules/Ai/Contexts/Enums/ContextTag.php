<?php

namespace App\Modules\Ai\Contexts\Enums;

enum ContextTag: string
{
    case CLARITY = 'clarity';
    case PAGE_ANALYSER = 'page_analyser';
    case GA = 'ga';

    public function label(): string
    {
        return match ($this) {
            self::CLARITY => __('Clarity Report'),
            self::PAGE_ANALYSER => __('Page Analyser'),
            self::GA => __('Google Analytics'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CLARITY => 'blue',
            self::PAGE_ANALYSER => 'emerald',
            self::GA => 'amber',
        };
    }
}
