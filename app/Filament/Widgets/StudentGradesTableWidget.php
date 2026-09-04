<?php

namespace App\Filament\Widgets;

use App\Models\StudentProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentGradesTableWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    protected function getTableQuery(): Builder
    {
        return StudentProfile::query()
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('users.role', 'student')
            ->selectRaw('COALESCE(student_profiles.grade, \'غير محدد\') as grade')
            ->selectRaw('COUNT(*) as total_students')
            ->groupBy('student_profiles.grade')
            ->orderBy('student_profiles.grade');
    }

    public function getTableRecordKey(Model | array $record): string
    {
        return (string) $record->grade;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('الطلاب حسب الصف')
            ->query($this->getTableQuery())
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('grade')
                    ->label('الصف')
                    ->sortable(),
                TextColumn::make('total_students')
                    ->label('عدد الطلاب')
                    ->sortable(),
            ]);
    }
}