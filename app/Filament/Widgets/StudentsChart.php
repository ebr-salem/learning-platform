<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class StudentsChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'تسجيل الطلاب';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $registrations = User::query()
            ->where('role', UserRole::Student)
            ->whereYear('created_at', now()->year)
            ->selectRaw('MONTH(created_at) as month')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $labels = [];
        $data = [];

        foreach (range(1, 12) as $month) {
            $labels[] = now()->startOfYear()->addMonths($month - 1)->format('M');
            $data[] = $registrations[$month] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $data,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}