<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsappLogStatus: string implements HasColor, HasLabel
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Sent => 'تم الإرسال',
            self::Failed => 'فشل',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
