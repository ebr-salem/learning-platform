<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Lesson;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('إجمالي الطلاب', User::query()->where('role', UserRole::Student)->count())
                ->color('primary')
                ->icon('heroicon-m-users'),
            Stat::make('إجمالي الدروس', Lesson::query()->count())
                ->color('success')
                ->icon('heroicon-m-book-open'),
            Stat::make('إجمالي الفصول', Lesson::query()->distinct()->count('chapter_name'))
                ->color('warning')
                ->icon('heroicon-m-bookmark'),
        ];
    }
}