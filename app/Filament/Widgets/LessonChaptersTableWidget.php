<?php

namespace App\Filament\Widgets;

use App\Models\Lesson;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LessonChaptersTableWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    public function getTableRecordKey(Model | array $record): string
    {
        return (string) $record->chapter_name;
    }

    protected function getTableQuery(): Builder
    {
        return Lesson::query()
            ->selectRaw('chapter_name')
            ->selectRaw('COUNT(*) as total_lessons')
            ->selectRaw('COALESCE(SUM(duration_minutes), 0) as total_duration')
            ->groupBy('chapter_name')
            ->orderBy('chapter_name');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('الدروس حسب الفصل')
            ->query($this->getTableQuery())
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('chapter_name')
                    ->label('الفصل')
                    ->sortable(),
                TextColumn::make('total_lessons')
                    ->label('عدد الدروس')
                    ->sortable(),
                TextColumn::make('total_duration')
                    ->label('المدة')
                    ->formatStateUsing(fn (int $state): string => $this->formatDuration($state))
                    ->sortable(),
            ]);
    }

    protected function formatDuration(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours === 0) {
            return "$minutes دقيقة";
        }

        $hoursLabel = $hours === 1 ? 'ساعة' : 'ساعات';

        if ($minutes === 0) {
            return "$hours $hoursLabel";
        }

        return "$hours $hoursLabel و $minutes دقيقة";
    }
}