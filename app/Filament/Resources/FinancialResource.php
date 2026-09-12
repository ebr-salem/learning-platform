<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\FinancialResource\Pages;
use App\Models\Financial;
use App\Models\Group;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use UnitEnum;

class FinancialResource extends Resource
{
    protected static ?string $model = Financial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'سجل مالي';

    protected static ?string $pluralModelLabel = 'السجلات المالية';

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('created_by')
                    ->default(fn() => auth()->id())
                    ->required()
                    ->dehydrated()
                    ->rule(
                        Rule::exists('users', 'id')->where('role', UserRole::Assistant->value),
                    ),
                Select::make('user_id')
                    ->label('الطالب')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->relationship(
                        'user',
                        'name',
                        modifyQueryUsing: fn(Builder $query): Builder => $query->where('role', UserRole::Student->value),
                    )
                    ->rule(
                        Rule::exists('users', 'id')->where('role', UserRole::Student->value),
                    ),

                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->string()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('الوصف')
                    ->required()
                    ->string()
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->modifyQueryUsing(function (Builder $query, $livewire, bool $isResolvingRecord = false): Builder {
                if ($isResolvingRecord) {
                    return $query;
                }

                if (
                    blank(static::selectedStudentId($livewire))
                    && blank(static::selectedGroupId($livewire))
                    && blank(static::selectedMonth($livewire))
                ) {
                    $query->where('financials.id', 0);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('الطالب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('أنشئ بواسطة')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->recordAction(ViewAction::class)
            ->recordUrl(null)
            ->recordActions([
                static::viewDetailsAction(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->filters([
                SelectFilter::make('group_id')
                    ->label('المجموعة')
                    ->options(fn(): array => static::groupFilterOptions())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('user.studentProfile', function (Builder $q) use ($data): void {
                                $q->where('group_id', $data['value']);
                            });
                        }

                        return $query;
                    }),
                SelectFilter::make('month')
                    ->label('الشهر')
                    ->options(fn(): array => static::monthFilterOptions())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null) && preg_match('/^(\d{4})-(\d{2})$/', (string) $data['value'], $matches)) {
                            $query->whereYear('financials.created_at', $matches[1])
                                ->whereMonth('financials.created_at', $matches[2]);
                        }

                        return $query;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->emptyStateHeading('اختر فلتر لعرض البيانات')
            ->emptyStateDescription('اختر المجموعة أو الشهر لعرض السجلات المالية')
            ->emptyStateIcon(Heroicon::OutlinedFunnel)
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Details popup: full financial record view (student + group + creator).
     * Opens from the row click or the row action.
     */
    public static function viewDetailsAction(string $name = 'view'): ViewAction
    {
        return ViewAction::make($name)
            ->modalHeading(fn (?Financial $record): string => 'السجل المالي — ' . ($record?->title ?? ''))
            ->modalContent(fn (Financial $record): \Illuminate\Contracts\View\View => view(
                'filament.resources.financial-resource.partials.financial-details',
                ['record' => $record->loadMissing(['user.studentProfile.group', 'creator'])],
            ))
            ->schema([])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth('2xl');
    }

    /**
     * Student filter options (role = student only), memoized per request:
     * Filament evaluates the options closure more than once per page load,
     * so cache the result instead of hitting the DB every time.
     *
     * @return array<int, string>
     */
    public static function studentFilterOptions(): array
    {
        static $options;

        return $options ??= User::where('role', UserRole::Student->value)->pluck('name', 'id')->all();
    }

    /**
     * Group filter options sorted by result count (most financials first),
     * memoized per request: Filament evaluates the options closure more
     * than once per page load, so cache the result instead of hitting
     * the DB every time.
     *
     * Counts financial records per group through users -> studentProfiles
     * (same path as the filter query's whereHas), so the number in each
     * label matches what selecting that group will return. Zero-count
     * groups are included last so the filter still lists every group.
     *
     * NOTE: SelectFilter options are plain strings (native + searchable
     * select), so "flexing" title vs count is done via label formatting
     * "name (N سجل)" — Filament v4 SelectFilter has no per-option
     * description/HTML slot to split them into columns.
     *
     * @return array<int, string>
     */
    public static function groupFilterOptions(): array
    {
        static $options;

        if ($options !== null) {
            return $options;
        }

        $counts = Financial::query()
            ->join('users', 'users.id', '=', 'financials.user_id')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->whereNotNull('student_profiles.group_id')
            ->selectRaw('student_profiles.group_id as group_id, COUNT(*) as aggregate')
            ->groupBy('student_profiles.group_id')
            ->pluck('aggregate', 'group_id')
            ->map(fn($value): int => (int) $value)
            ->all();

        return $options = Group::query()
            ->pluck('name', 'id')
            ->map(fn(string $name, int $id): array => [
                'id' => $id,
                'name' => $name,
                'count' => $counts[$id] ?? 0,
            ])
            ->sort(fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']))
            ->mapWithKeys(fn(array $row): array => [$row['id'] => "{$row['name']} ({$row['count']} سجل)"])
            ->all();
    }

    /**
     * Month filter options (all months with financial records), memoized
     * per request. Built in PHP (not DATE_FORMAT) so it works on any
     * database driver.
     *
     * @return array<string, string>
     */
    public static function monthFilterOptions(): array
    {
        static $options;

        if ($options !== null) {
            return $options;
        }

        return $options = Financial::query()
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn($at) => Carbon::parse($at)->format('Y-m'))
            ->unique()
            ->mapWithKeys(fn($ym) => [$ym => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y')])
            ->all();
    }

    /**
     * The selected student (user_id) filter value on the table (null when none).
     */
    public static function selectedStudentId($livewire): mixed
    {
        return static::selectedFilterValue($livewire, 'user_id');
    }

    /**
     * The selected group_id filter value on the table (null when none).
     */
    public static function selectedGroupId($livewire): mixed
    {
        return static::selectedFilterValue($livewire, 'group_id');
    }

    /**
     * The selected month filter value on the table (YYYY-MM or null).
     */
    public static function selectedMonth($livewire): mixed
    {
        return static::selectedFilterValue($livewire, 'month');
    }

    /**
     * Read a raw table filter value from the table Livewire component.
     *
     * Filament v4 notes:
     * - The canonical public API is getTableFilterState($name) which
     *   returns the per-filter form state e.g. ['value' => X] for single
     *   selects or ['values' => [...]] for multi-selects.
     * - Do NOT call getTableFilters(): it is a protected deprecated stub
     *   that always returns [] (method_exists() is true even though the
     *   call throws). Prefer getTableFilterState() /
     *   getTableFilterFormState() first, then fall back to the public
     *   $tableFilters / $tableDeferredFilters properties.
     */
    public static function selectedFilterValue($livewire, string $key): mixed
    {
        $data = null;

        if (is_object($livewire)) {
            if (method_exists($livewire, 'getTableFilterState')) {
                try {
                    $data = $livewire->getTableFilterState($key);
                } catch (\Throwable) {
                    $data = null;
                }
            }

            if ($data === null && method_exists($livewire, 'getTableFilterFormState')) {
                try {
                    $data = $livewire->getTableFilterFormState($key);
                } catch (\Throwable) {
                    $data = null;
                }
            }

            if ($data === null && isset($livewire->tableFilters) && is_array($livewire->tableFilters)) {
                $data = $livewire->tableFilters[$key] ?? null;
            }

            if ($data === null && isset($livewire->tableDeferredFilters) && is_array($livewire->tableDeferredFilters)) {
                $data = $livewire->tableDeferredFilters[$key] ?? null;
            }
        }

        if (is_array($data)) {
            if (array_key_exists('value', $data)) {
                return $data['value'];
            }

            if (array_key_exists('values', $data)) {
                return $data['values'];
            }
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
            'index' => Pages\ListFinancials::route('/'),
            'create' => Pages\CreateFinancial::route('/create'),
            'edit' => Pages\EditFinancial::route('/{record}/edit'),
        ];
    }
}