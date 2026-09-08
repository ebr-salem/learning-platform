<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\StudentProfile;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentProfileRelationManager extends RelationManager
{
    protected static string $relationship = 'studentProfile';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::profileSchemaComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_code')
                    ->label('كود الطالب')
                    ->searchable(),
                TextColumn::make('grade')
                    ->label('الصف الدراسي'),
                TextColumn::make('dob')
                    ->label('تاريخ الميلاد')
                    ->date(),
                TextColumn::make('guardian_name')
                    ->label('اسم ولي الأمر'),
                TextColumn::make('guardian_phone')
                    ->label('هاتف ولي الأمر'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة ملف الطالب')
                    ->hidden(fn(): bool => $this->getOwnerRecord()->studentProfile()->exists()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function profileSchemaComponents(): array
    {
        return [
            TextInput::make('student_code')
                ->label('كود الطالب')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('grade')
                ->label('الصف الدراسي')
                ->options(array_combine(StudentProfile::GRADES, StudentProfile::GRADES))
                ->required(),
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
                ->maxLength(255),
        ];
    }
}