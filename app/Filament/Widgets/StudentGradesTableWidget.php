<?php

namespace App\Filament\Widgets;

use App\Models\StudentProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class StudentGradesTableWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getTableQuery(): Builder
    {
        [$case, $bindings] = self::gradeOrderExpression('grade_list.grade');

        // Start from the canonical grades list so grades with zero students
        // still appear as rows (GROUP BY on student_profiles would omit them).
        return StudentProfile::query()
            ->fromSub(self::gradesListQuery(), 'grade_list')
            ->select('grade_list.grade as grade')
            ->selectRaw('COUNT(users.id) as total_students')
            ->leftJoin('student_profiles as sp', 'sp.grade', '=', 'grade_list.grade')
            ->leftJoin('users', function (JoinClause $join): void {
                $join->on('users.id', '=', 'sp.user_id')->where('users.role', 'student');
            })
            ->groupBy('grade_list.grade')
            ->orderByRaw($case.' ASC', $bindings);
    }

    /**
     * A subquery returning one row per canonical grade in StudentProfile::GRADES.
     */
    protected static function gradesListQuery(): BaseQueryBuilder
    {
        $query = null;

        foreach (StudentProfile::GRADES as $grade) {
            $select = DB::query()->selectRaw('? as grade', [$grade]);
            $query = $query ? $query->unionAll($select) : $select;
        }

        return $query;
    }

    /**
     * Build a portable CASE expression that sorts grades
     * by their index in StudentProfile::GRADES.
     *
     * Unknown / null grades fall into ELSE 999 so they appear last in ASC order.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected static function gradeOrderExpression(string $column = 'grade_list.grade'): array
    {
        $case = "CASE {$column} ";

        foreach (StudentProfile::GRADES as $index => $grade) {
            $case .= "WHEN ? THEN {$index} ";
        }

        $case .= 'ELSE 999 END';

        return [$case, StudentProfile::GRADES];
    }

    public function getTableRecordKey(Model|array $record): string
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
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        [$case, $bindings] = self::gradeOrderExpression('grade_list.grade');
                        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

                        // Keep null / unknown grades last in both directions.
                        return $query
                            ->reorder()
                            ->orderByRaw("({$case}) = 999 ASC", $bindings)
                            ->orderByRaw("{$case} {$direction}", $bindings);
                    }),
                TextColumn::make('total_students')
                    ->label('عدد الطلاب')
                    ->sortable(),
            ]);
    }
}
