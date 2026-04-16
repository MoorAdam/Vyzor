<?php

namespace App;

enum ContextTag: string
{
    case CLARITY = 'clarity';
    case PAGE_ANALYSER = 'page_analyser';

    public function label(): string
    {
        return match ($this) {
            self::CLARITY => __('Clarity Report'),
            self::PAGE_ANALYSER => __('Page Analyser'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CLARITY => 'blue',
            self::PAGE_ANALYSER => 'emerald',
        };
    }
}
