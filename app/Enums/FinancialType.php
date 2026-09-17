<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FinancialType: string implements HasLabel
{
    case Monthly = 'monthly';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monthly => 'الشهرية',
            self::Other => 'أخرى',
        };
    }

    public function isMonthly(): bool
    {
        return $this === self::Monthly;
    }

    /**
     * Options for the monthly title: months 1 to 12.
     * Keys (stored in financials.title) are the month numbers
     * as strings; labels show the number + Arabic month name.
     *
     * To add a new process type later, just add a new case here
     * (e.g. `case Quarterly = 'quarterly';`) with its Arabic label —
     * the Resource form/table pick it up automatically. If the new
     * type needs a manual title, add it to typesWithManualTitle().
     *
     * @return array<string, string>
     */
    public static function monthTitleOptions(): array
    {
        $months = [
            '1' => 'يناير',
            '2' => 'فبراير',
            '3' => 'مارس',
            '4' => 'أبريل',
            '5' => 'مايو',
            '6' => 'يونيو',
            '7' => 'يوليو',
            '8' => 'أغسطس',
            '9' => 'سبتمبر',
            '10' => 'أكتوبر',
            '11' => 'نوفمبر',
            '12' => 'ديسمبر',
        ];

        $options = [];

        foreach ($months as $number => $name) {
            $options[(string) $number] = "{$number} - {$name}";
        }

        return $options;
    }

    /**
     * Resolve a stored monthly title ("1".."12") to its display label.
     */
    public static function monthTitleLabel(?string $title): ?string
    {
        if (blank($title)) {
            return null;
        }

        return static::monthTitleOptions()[(string) $title] ?? (string) $title;
    }

    /**
     * Types that require a manually entered title.
     * Everything else uses the 1-12 month select.
     *
     * @return array<string>
     */
    public static function typesWithManualTitle(): array
    {
        return [self::Other->value];
    }
}