<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SmsMisrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAttendanceSmsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly User $student)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SmsMisrService $smsService): void
    {
        $guardianPhone = $this->student->studentProfile?->guardian_phone;

        if (empty($guardianPhone)) {
            Log::warning('No guardian phone available for student.', ['student_id' => $this->student->id]);

            return;
        }

        $message = "تم تسجيل حضور الطالب {$this->student->name} بنجاح";

        $smsService->send($guardianPhone, $message);
    }
}
