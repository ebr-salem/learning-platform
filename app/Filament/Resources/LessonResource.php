<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LessonResource\Pages;
use App\Models\Lesson;
use App\Rules\YoutubeUrl;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Filament\Tables\Columns\RelatedCountColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'درس';

    protected static ?string $pluralModelLabel = 'الدروس';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى التعليمي';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('chapter_name')
                    ->label('اسم الفصل')
                    ->required()
                    ->maxLength(255),

                TextInput::make('title')
                    ->label('عنوان الدرس')
                    ->required()
                    ->maxLength(255),

                TextInput::make('video_url')
                    ->label('رابط فيديو اليوتيوب')
                    ->url()
                    ->required()
                    ->rules([new YoutubeUrl()]),

                TextInput::make('thumbnail_url')
                    ->label('رابط الصورة المصغرة')
                    ->url()
                    ->required(),

                Textarea::make('about_lesson')
                    ->label('عن الدرس')
                    ->rows(5)
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('duration_minutes')
                    ->label('المدة (بالدقائق)')
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(480)
                    ->extraInputAttributes([
                        'onkeypress' => 'return event.charCode >= 48 && event.charCode <= 57',
                        'oninput' => "
                        if (this.value > 480) { this.value = 480; }
                        if (this.value !== '' && this.value < 1) { this.value = 1; }
                    "
                    ])
                    ->columnSpanFull(),

                Select::make('groups')
                    ->label('المجموعات التي يظهر لها الدرس')
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Repeater::make('what_you_will_learn')
                    ->label('ماذا ستتعلم')
                    ->addActionLabel('إضافة نقطة')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('value')
                            ->label('نقطة تعلم')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->formatStateUsing(fn(mixed $state): array => collect($state ?? [])
                        ->map(fn($item): array => is_array($item) ? ['value' => $item['value'] ?? null] : ['value' => $item])
                        ->all())
                    ->dehydrateStateUsing(fn(mixed $state): array => collect($state ?? [])
                        ->pluck('value')
                        ->filter(fn(mixed $value): bool => filled($value))
                        ->values()
                        ->all()),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(5)
                    ->columnSpanFull()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('الصورة المصغرة')
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label('عنوان الدرس')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('chapter_name')
                    ->label('الفصل')
                    ->searchable(),
                RelatedCountColumn::make('groups_count')
                    ->label('عدد المجموعات')
                    ->sortable()
                    ->showsRelated(
                        relationship: 'groups',
                        displayColumn: 'name',
                        titleColumn: 'title',
                        headingPrefix: 'مجموعات الدرس: ',
                        emptyMessage: 'لا توجد مجموعات مرتبطة بهذا الدرس.',
                    ),
                TextColumn::make('duration_minutes')
                    ->label('المدة')
                    ->suffix(' دقيقة')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessons::route('/'),
            'create' => Pages\CreateLesson::route('/create'),
            'edit' => Pages\EditLesson::route('/{record}/edit'),
        ];
    }
}