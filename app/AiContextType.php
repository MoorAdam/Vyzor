<?php

namespace App;

enum AiContextType: string
{
    case PRESET = 'preset';
    case SYSTEM = 'system';
    case INSTRUCTION = 'instruction';

    public function label(): string
    {
        return match ($this) {
            self::PRESET => __('Preset'),
            self::SYSTEM => __('System'),
            self::INSTRUCTION => __('Instruction'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRESET => 'blue',
            self::SYSTEM => 'violet',
            self::INSTRUCTION => 'amber',
        };
    }
}
