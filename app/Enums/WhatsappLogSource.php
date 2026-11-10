<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsappLogSource: string implements HasLabel
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Test = 'test';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Auto => 'تلقائي',
            self::Manual => 'يدوي',
            self::Test => 'تجريبي',
        };
    }
}
