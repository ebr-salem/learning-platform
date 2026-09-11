<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Group;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'تسجيل حضور';

    protected static ?string $pluralModelLabel = 'سجل الحضور';

    protected static string|UnitEnum|null $navigationGroup = 'الحضور';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->columns([
                TextColumn::make('student.name')
                    ->label('الطالب')
                    ->searchable(),
                TextColumn::make('student.studentProfile.group.name')
                    ->label('المجموعة'),
                TextColumn::make('attendance_count')
                    ->label('عدد مرات الحضور')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->sortable()
                    ->url(fn(Attendance $record, $livewire) => static::studentDetailsUrl($record, $livewire)),
            ])
            ->filters([
                SelectFilter::make('group_id')
                    ->label('المجموعة')
                    ->options(fn(): array => static::groupFilterOptions())
                    ->preload()
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('student', function ($q) use ($data) {
                                $q->whereHas('studentProfile', function ($q) use ($data) {
                                    $q->where('group_id', $data['value']);
                                });
                            });
                        }
                    }),
                SelectFilter::make('month')
                    ->label('الشهر')
                    ->options(fn(): array => static::mainMonthFilterOptions())
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null) && preg_match('/^(\d{4})-(\d{2})$/', (string) $data['value'], $matches)) {
                            $query->whereYear('created_at', $matches[1])
                                ->whereMonth('created_at', $matches[2]);
                        }
                    })
            ], layout: FiltersLayout::AboveContent)
            ->recordUrl(fn(Attendance $record, $livewire) => static::studentDetailsUrl($record, $livewire))
            ->recordActions([
                Action::make('details')
                    ->label('عرض التفاصيل')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn(Attendance $record, $livewire) => static::studentDetailsUrl($record, $livewire)),
            ])
            ->defaultSort('attendance_count', 'desc');
    }

    /**
     * URL of a student's details page. Carries over the main table's
     * selected month (if any) so the details open with the same filter.
     */
    public static function studentDetailsUrl(Attendance $record, $livewire = null): string
    {
        $params = ['student' => $record->student_id];

        if ($livewire !== null && filled($month = static::selectedMonth($livewire))) {
            $params['month'] = $month;
        }

        return Pages\StudentAttendances::getUrl($params);
    }

    /**
     * Group filter options, memoized per request: Filament evaluates the
     * options closure more than once per page load, so cache the result
     * instead of hitting the DB every time.
     *
     * @return array<int, string>
     */
    public static function groupFilterOptions(): array
    {
        static $options;

        return $options ??= Group::pluck('name', 'id')->all();
    }

    /**
     * Month filter options for a single student's details page, memoized per
     * page instance (Filament evaluates the options closure more than once).
     * Built in PHP (not DATE_FORMAT) so it works on any database driver.
     *
     * @return array<string, string>
     */
    public static function studentMonthFilterOptions(int $studentId): array
    {
        return Attendance::query()
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn($at) => Carbon::parse($at)->format('Y-m'))
            ->unique()
            ->mapWithKeys(fn($ym) => [$ym => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y')])
            ->all();
    }

    /**
     * Month filter options for the main table (all months with scans),
     * memoized per request. Built in PHP (not DATE_FORMAT) so it works
     * on any database driver.
     *
     * @return array<string, string>
     */
    public static function mainMonthFilterOptions(): array
    {
        static $options;

        if ($options !== null) {
            return $options;
        }

        return $options = Attendance::query()
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn($at) => Carbon::parse($at)->format('Y-m'))
            ->unique()
            ->mapWithKeys(fn($ym) => [$ym => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y')])
            ->all();
    }

    /**
     * The selected group_id filter value on the main table (null when none).
     */
    public static function selectedGroupId($livewire): mixed
    {
        return static::selectedFilterValue($livewire, 'group_id');
    }

    /**
     * The selected month filter value on the main table (YYYY-MM or null).
     */
    public static function selectedMonth($livewire): mixed
    {
        return static::selectedFilterValue($livewire, 'month');
    }

    /**
     * Read a raw table filter value from the table Livewire component.
     */
    public static function selectedFilterValue($livewire, string $key): mixed
    {
        $filters = [];

        if (is_object($livewire)) {
            if (method_exists($livewire, 'getTableFilters')) {
                try {
                    $filters = $livewire->getTableFilters() ?? [];
                } catch (\Throwable) {
                    $filters = [];
                }
            }

            if (empty($filters) && isset($livewire->tableFilters) && is_array($livewire->tableFilters)) {
                $filters = $livewire->tableFilters;
            }
        }

        $data = $filters[$key] ?? null;

        if (is_array($data) && array_key_exists('value', $data)) {
            return $data['value'];
        }

        return $data;
    }


    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'details' => Pages\StudentAttendances::route('/students/{student}'),
        ];
    }
}