<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\SendAttendanceSmsJob;
use App\Models\Attendance;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function scanStudent(string $qrCodeString): JsonResponse
    {
        $profile = StudentProfile::with('user')
            ->where('qr_code_string', $qrCodeString)
            ->first();

        if ($profile === null) {
            return $this->errorResponse('الطالب غير موجود', 404);
        }

        return $this->successResponse([
            'student_id' => $profile->user_id,
            'full_name' => $profile->user->name,
            'student_code' => $profile->student_code,
            'grade' => $profile->grade,
            'profile_image' => $profile->profile_image,
            'dob' => $profile->dob?->format('Y-m-d'),
            'guardian_name' => $profile->guardian_name,
            'guardian_phone' => $profile->guardian_phone,
        ], 'تم العثور على الطالب');
    }

    public function registerAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $student = User::findOrFail($validated['student_id']);

        if ($student->role !== UserRole::Student) {
            return $this->errorResponse('المستخدم المحدد ليس طالباً', 422);
        }

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'scanned_by' => $request->user()->id,
        ]);

        SendAttendanceSmsJob::dispatch($student);

        return $this->successResponse($attendance->fresh(), 'تم تسجيل الحضور بنجاح', 201);
    }
}
