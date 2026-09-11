<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        // No data until the admin picks a filter (group and/or month).
        $filters = $this->tableFilters ?? [];

        $hasFilter = collect($filters)->contains(function ($data) {
            return filled($data['value'] ?? null);
        });

        if (! $hasFilter) {
            return $query->whereRaw('1 = 0'); // no filter picked yet, empty result
        }

        // Always one row per student: join the per-student aggregate
        // (scoped to the selected group and month, if any) on the latest scan id.
        // No GROUP BY on the outer query, so ordering stays valid under
        // MySQL ONLY_FULL_GROUP_BY. Only students with scans appear.
        $groupId = AttendanceResource::selectedGroupId($this);
        $month = AttendanceResource::selectedMonth($this);

        [$year, $monthNum] = preg_match('/^(\d{4})-(\d{2})$/', (string) $month, $m)
            ? [$m[1], $m[2]]
            : [null, null];

        $summary = Attendance::query()
            ->selectRaw('student_id, COUNT(*) as attendance_count, MAX(id) as latest_id')
            ->when(filled($groupId), fn($q) => $q->whereHas('student.studentProfile', fn($qq) => $qq->where('group_id', $groupId)))
            ->when($year && $monthNum, fn($q) => $q->whereYear('created_at', $year)->whereMonth('created_at', $monthNum))
            ->groupBy('student_id');

        $query
            ->joinSub($summary, 'attendance_summary', fn($join) => $join->on('attendance_summary.latest_id', '=', 'attendances.id'))
            ->select('attendances.*')
            ->selectRaw('attendance_summary.attendance_count as attendance_count')
            ->orderByDesc('attendance_summary.attendance_count');

        return $query;
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        $hasFilter = collect($this->tableFilters ?? [])->contains(fn($data) => filled($data['value'] ?? null));

        return $hasFilter ? 'لا يوجد سجل الحضور' : 'الرجاء اختيار فلتر لعرض البيانات';
    }
}