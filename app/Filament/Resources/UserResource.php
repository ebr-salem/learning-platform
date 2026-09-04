<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Group;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Get;
use Closure;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    protected static string|UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Adjust 'role' and 'student' to match your exact database column and value
        return parent::getEloquentQuery()->where('role', 'student');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم الطالب')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->label('اسم الدخول')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn(?string $state): bool => filled($state))
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->confirmed()
                    ->maxLength(255),
                TextInput::make('password_confirmation')
                    ->label('تأكيد كلمة المرور')
                    ->password()
                    ->revealable()
                    ->dehydrated(false),
                Fieldset::make('بيانات ملف الطالب')
                    ->relationship('studentProfile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('student_code')
                            ->label('كود الطالب')
                            ->disabled(),
                        Select::make('grade')
                            ->label('الصف الدراسي')
                            ->options(array_combine(StudentProfile::GRADES, StudentProfile::GRADES))
                            ->required(),
                        Select::make('group_id')
                            ->label('المجموعة')
                            ->relationship('group', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        FileUpload::make('profile_image')
                            ->label('صورة الطالب')
                            ->image()
                            ->disk('public')
                            ->directory('student-profiles')
                            ->imageEditor()
                            ->nullable(),
                        TextInput::make('qr_code_string')
                            ->label('رمز QR')
                            ->disabled(),
                        DatePicker::make('dob')
                            ->label('تاريخ الميلاد')
                            ->required(),
                        TextInput::make('guardian_name')
                            ->label('اسم ولي الأمر')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('guardian_phone')
                            ->label('هاتف ولي الأمر')
                            ->tel()
                            ->required()
                            ->maxLength(255)
                            ->rule(
                                fn(\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $phone = $get('../phone');

                                    if (filled($phone) && $value === $phone) {
                                        $fail('رقم ولي الأمر يجب أن يكون مختلفاً عن رقم الطالب.');
                                    }
                                }
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('studentProfile.student_code')
                    ->label('كود الطالب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label('اسم الدخول')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('رقم الهاتف'),
                TextColumn::make('studentProfile.group.name')
                    ->label('المجموعة')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->deferFilters(false)
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('studentProfile.group_id')
                    ->label('المجموعة')
                    ->relationship('studentProfile.group', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('الكل'),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}