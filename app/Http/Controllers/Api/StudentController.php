<?php

namespace App\Http\Controllers\Api;

use App\Enums\FinancialType;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->studentProfile;

        if ($profile === null) {
            return $this->errorResponse('ملف الطالب غير موجود', 404);
        }

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'qr_code_string' => $profile->qr_code_string,
            'student_code' => $profile->student_code,
            'grade' => $profile->grade,
            'profile_image' => $profile->profile_image,
            'dob' => $profile->dob?->format('Y-m-d'),
            'guardian_name' => $profile->guardian_name,
            'guardian_phone' => $profile->guardian_phone,
        ]);
    }

    public function lessons(Request $request): JsonResponse
    {
        $query = Lesson::visibleTo($request->user());

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->string('search').'%');
        }

        $lessons = $query
            ->orderBy('id')
            ->get(['id', 'chapter_name', 'title', 'duration_minutes', 'thumbnail_url']);

        return $this->successResponse($lessons);
    }

    public function lesson(Request $request, int $id): JsonResponse
    {
        $lesson = Lesson::visibleTo($request->user())->find($id);

        if ($lesson === null) {
            return $this->errorResponse('الدرس غير موجود', 404);
        }

        $previousLessonId = Lesson::visibleTo($request->user())->where('id', '<', $id)->orderByDesc('id')->value('id');
        $nextLessonId = Lesson::visibleTo($request->user())->where('id', '>', $id)->orderBy('id')->value('id');

        $data = $lesson->toArray();
        $data['previous_lesson_id'] = $previousLessonId;
        $data['next_lesson_id'] = $nextLessonId;

        return $this->successResponse($data);
    }

    public function reports(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2099'],
        ]);

        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;
        $user = $request->user();

        $attendanceQuery = $user->attendances()->orderByDesc('created_at');

        if ($year !== null) {
            $attendanceQuery->whereYear('created_at', $year);
        }

        $attendance = $attendanceQuery->get(['id', 'created_at'])->map(
            fn ($record) => [
                'id' => $record->id,
                'date' => $record->created_at->format('Y-m-d'),
            ]
        );

        $financialQuery = $user->financials()->orderByDesc('created_at');

        if ($year !== null) {
            $financialQuery->whereYear('created_at', $year);
        }

        if ($month !== null) {
            $financialQuery->where(function ($query) use ($month): void {
                $query->where('type', FinancialType::Other->value)
                    ->orWhere(function ($query) use ($month): void {
                        $query->where('type', FinancialType::Monthly->value)
                            ->where('title', (string) $month);
                    });
            });
        }

        $financials = $financialQuery->get()->map(function ($record) {
            $isMonthly = $record->type->isMonthly();

            return [
                'id' => $record->id,
                'type' => $record->type->value,
                'type_label' => $record->type->getLabel(),
                'title' => $record->title,
                'title_label' => $isMonthly
                    ? FinancialType::monthTitleLabel($record->title)
                    : $record->title,
                'description' => $record->description,
                'created_at' => $record->created_at?->format('Y-m-d'),
                'created_by' => $record->creator?->name,
            ];
        });

        $profile = $user->studentProfile;

        return $this->successResponse([
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'student_code' => $profile?->student_code,
                'grade' => $profile?->grade,
                'group_name' => $profile?->group?->name,
                'profile_image' => $profile?->profile_image,
            ],
            'attendance' => [
                'total' => $attendance->count(),
                'records' => $attendance->values(),
            ],
            'financials' => [
                'total' => $financials->count(),
                'records' => $financials->values(),
            ],
        ]);
    }
}
