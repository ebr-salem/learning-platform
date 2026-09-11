<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class StudentAttendances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AttendanceResource::class;

    protected string $view = 'filament.resources.attendance-resource.pages.student-attendances';

    public User $student;

    protected ?array $cachedMonthOptions = null;

    public function mount(User $student): void
    {
        $this->student = $student;
        $this->student->loadMissing('studentProfile.group');
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'تفاصيل الحضور — ' . $this->student->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $group = $this->student->studentProfile?->group?->name ?? '—';
        $total = $this->student->attendances()->count();

        return "المجموعة: {$group} — إجمالي الحضور: {$total}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Attendance::query()->where('student_id', $this->student->id))
            ->deferFilters(false)
            ->columns([
                TextColumn::make('date')
                    ->label('التاريخ')
                    ->state(fn(Attendance $record) => Carbon::parse($record->created_at)->format('Y-m-d'))
                    ->sortable(query: fn($query, string $direction) => $query->orderBy('created_at', $direction)),
                TextColumn::make('time')
                    ->label('الوقت')
                    ->state(fn(Attendance $record) => Carbon::parse($record->created_at)->format('h:i A')),
                TextColumn::make('weekday')
                    ->label('اليوم')
                    ->state(fn(Attendance $record) => Carbon::parse($record->created_at)->translatedFormat('l')),
                TextColumn::make('scanner.name')
                    ->label('سجّل بواسطة')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('الشهر')
                    ->options(fn(): array => $this->monthOptions())
                    ->default(fn(): ?string => $this->initialMonthFilter())
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null) && preg_match('/^(\d{4})-(\d{2})$/', (string) $data['value'], $matches)) {
                            $query->whereYear('created_at', $matches[1])
                                ->whereMonth('created_at', $matches[2]);
                        }
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Month carried over from the main table (?month=YYYY-MM). Applied as
     * the initial filter so the details open with the same month selected.
     */
    protected function initialMonthFilter(): ?string
    {
        $month = request()->query('month');

        if (is_string($month) && preg_match('/^(\d{4})-(\d{2})$/', $month)) {
            return $month;
        }

        return null;
    }

    /**
     * Distinct YYYY-MM months this student has scans in (memoized: Filament
     * evaluates the options closure more than once per load).
     *
     * @return array<string, string>
     */
    protected function monthOptions(): array
    {
        return $this->cachedMonthOptions ??= AttendanceResource::studentMonthFilterOptions($this->student->id);
    }
}