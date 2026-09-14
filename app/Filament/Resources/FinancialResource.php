<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\FinancialResource\Pages;
use App\Models\Financial;
use App\Models\Group;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
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
            ->columns(1)
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
                    ->searchDebounce(500)
                    ->live()
                    ->relationship(
                        'user',
                        'name',
                        fn(Builder $query): Builder => $query
                            ->where('role', UserRole::Student->value)
                            ->select(['users.id', 'users.name'])
                            ->with(static::studentEagerLoad()),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn(User $record): string => static::studentOptionLabel($record),
                    )
                    ->getSearchResultsUsing(
                        fn(string $search): array => static::searchStudentOptions($search),
                    )
                    ->getOptionLabelUsing(
                        fn(mixed $value): ?string => static::resolveStudentOptionLabel($value),
                    )
                    ->rule(
                        Rule::exists('users', 'id')->where('role', UserRole::Student->value),
                    ),

                Section::make('بيانات الطالب')
                    ->visible(fn(Get $get): bool => filled($get('user_id')))
                    ->columns(2)
                    ->schema([
                        Placeholder::make('selected_student_name')
                            ->label('اسم الطالب')
                            ->content(fn(Get $get): string => static::selectedStudent($get('user_id'))?->name ?? '—'),
                        Placeholder::make('selected_student_code')
                            ->label('كود الطالب')
                            ->content(fn(Get $get): string => static::selectedStudent($get('user_id'))?->studentProfile?->student_code ?? '—'),
                        Placeholder::make('selected_student_phone')
                            ->label('رقم الهاتف')
                            ->content(fn(Get $get): string => static::selectedStudent($get('user_id'))?->phone ?? '—'),
                        Placeholder::make('selected_student_group')
                            ->label('المجموعة')
                            ->content(fn(Get $get): string => static::selectedStudent($get('user_id'))?->studentProfile?->group?->name ?? 'بدون مجموعة'),
                        Placeholder::make('selected_student_financials')
                            ->label('السجلات المالية')
                            ->content(fn(Get $get): string => static::studentFinancialSummary($get('user_id'))),
                    ]),

                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->string()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('الوصف')
                    ->nullable()
                    ->string()
                    ->maxLength(5000)
                    ->rows(4),
            ]);
    }

    /**
     * Label for a student option: "Name - Group".
     *
     * Relation check: User hasOne StudentProfile belongsTo Group
     * (single group per student — see student_profiles.group_id).
     * There is no many-to-many student<->group table, so the label
     * shows the one group, with a "بدون مجموعة" fallback when the
     * profile/group is missing.
     */
    /**
     * Constrained eager load for the student option label / detail card.
     * Only the columns actually displayed are fetched:
     * - student_profiles: id (match), user_id (match), group_id (match
     *   for group relation), student_code (display)
     * - groups: id (match), name (display)
     * This turns `select * ... where id in (...)` into
     * `select id, ... ... where id in (...)`.
     *
     * @return array<string>
     */
    protected static function studentEagerLoad(): array
    {
        return [
            'studentProfile:id,user_id,group_id,student_code',
            'studentProfile.group:id,name',
        ];
    }

    public static function studentOptionLabel(User $user): string
    {
        $user->loadMissing(static::studentEagerLoad());

        $groupName = $user->studentProfile?->group?->name ?? 'بدون مجموعة';
        $code = $user->studentProfile?->student_code;

        return filled($code)
            ? "{$user->name} ({$code}) - {$groupName}"
            : "{$user->name} - {$groupName}";
    }

    /**
     * Custom search for the student select: matches student name,
     * phone, student code, or group name. Only fires when the user
     * types (blank search returns no options, so opening the create
     * form issues zero student queries).
     *
     * Single query only: joins fetch code + group name in the same
     * SELECT, so no extra student_profiles / groups queries fire.
     *
     * @return array<int, string>
     */
    public static function searchStudentOptions(string $search): array
    {
        $search = trim($search);

        if (blank($search)) {
            return [];
        }

        return User::query()
            ->where('users.role', UserRole::Student->value)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('groups', 'groups.id', '=', 'student_profiles.group_id')
            ->select([
                'users.id',
                'users.name',
                'users.phone',
                'student_profiles.student_code',
                'groups.name as group_name',
            ])
            ->where(function (Builder $query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('student_profiles.student_code', 'like', "%{$search}%")
                    ->orWhere('groups.name', 'like', "%{$search}%");
            })
            ->orderBy('users.name')
            ->limit(50)
            ->get()
            ->mapWithKeys(function (User $user): array {
                $groupName = $user->getAttribute('group_name') ?? 'بدون مجموعة';
                $code = $user->getAttribute('student_code');

                $label = filled($code)
                    ? "{$user->name} ({$code}) - {$groupName}"
                    : "{$user->name} - {$groupName}";

                return [$user->getKey() => $label];
            })
            ->all();
    }

    /**
     * Per-request memoization for the selected student.
     * The detail Section renders 4 Placeholders, each calling
     * selectedStudent() with the same id — without this cache that is
     * 4 identical user queries (+ profile/group eager loads) per
     * Livewire render. Static resets on the next request, so selecting
     * a different student can never return stale data.
     *
     * @var array<string, ?User>
     */
    protected static array $selectedStudentCache = [];

    /**
     * Resolve the label for an already-selected student id
     * (used for the current selection, e.g. on the edit page).
     * Reuses the memoized selectedStudent() so the select label
     * does not issue its own duplicate query.
     */
    public static function resolveStudentOptionLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $user = static::selectedStudent($value);

        if ($user === null) {
            return null;
        }

        return static::studentOptionLabel($user);
    }

    /**
     * The currently selected student with profile/group + financial
     * count preloaded. Memoized per id per request: repeated calls
     * with the same id (one per Placeholder) hit the cache.
     */
    protected static function selectedStudent(mixed $id): ?User
    {
        if (blank($id)) {
            return null;
        }

        $key = (string) $id;

        if (array_key_exists($key, static::$selectedStudentCache)) {
            return static::$selectedStudentCache[$key];
        }

        return static::$selectedStudentCache[$key] = User::query()
            ->select(['users.id', 'users.name', 'users.phone'])
            ->with(static::studentEagerLoad())
            ->withCount('financials')
            ->find($id);
    }

    /**
     * Financial summary for the selected student. The financials
     * table has no amount column (title/description only), so the
     * summary is the record count.
     */
    protected static function studentFinancialSummary(mixed $id): string
    {
        $student = static::selectedStudent($id);

        if ($student === null) {
            return '—';
        }

        $count = (int) ($student->financials_count ?? 0);

        return $count > 0 ? "{$count} سجل" : 'لا توجد سجلات';
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
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
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
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Details popup: full financial record view (student + group + creator).
     * Opens from the row click or the row action.
     */
    public static function viewDetailsAction(string $name = 'view'): ViewAction
    {
        return ViewAction::make($name)
            ->modalHeading(fn(?Financial $record): string => 'السجل المالي — ' . ($record?->title ?? ''))
            ->modalContent(fn(Financial $record): \Illuminate\Contracts\View\View => view(
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
     * Group filter options: plain "name" list, no counts.
     * Memoized per request since Filament evaluates the options
     * closure more than once per page load.
     *
     * @return array<int, string>
     */
    public static function groupFilterOptions(): array
    {
        static $options;

        return $options ??= Group::query()
            ->orderBy('name')
            ->pluck('name', 'id')
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