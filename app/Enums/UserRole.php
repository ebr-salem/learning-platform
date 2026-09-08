<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Student = 'student';
    case Assistant = 'assistant';
    case Admin = 'admin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Student => 'طالب',
            self::Assistant => 'مساعد',
            self::Admin => 'مدير',
        };
    }
}
