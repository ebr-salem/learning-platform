<?php

namespace App\Http\Controllers\Api;

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
            return $this->errorResponse('Student profile not found.', 404);
        }

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
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
        $query = Lesson::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->string('search').'%');
        }

        $lessons = $query
            ->orderBy('id')
            ->get(['id', 'chapter_name', 'title', 'duration_minutes', 'thumbnail_url']);

        return $this->successResponse($lessons);
    }

    public function lesson(int $id): JsonResponse
    {
        $lesson = Lesson::find($id);

        if ($lesson === null) {
            return $this->errorResponse('Lesson not found.', 404);
        }

        $previousLessonId = Lesson::where('id', '<', $id)->orderByDesc('id')->value('id');
        $nextLessonId = Lesson::where('id', '>', $id)->orderBy('id')->value('id');

        $data = $lesson->toArray();
        $data['previous_lesson_id'] = $previousLessonId;
        $data['next_lesson_id'] = $nextLessonId;

        return $this->successResponse($data);
    }
}
