<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Enums\WhatsappLogSource;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Notifications\WhatsappSendFailed;
use App\Services\SmsMisrService;
use App\Services\WahaService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Tells a student's guardian that attendance was recorded.
 *
 * WhatsApp is the primary channel. SMSMisr is only a fallback: it runs when
 * WhatsApp could not deliver, so a WAHA outage degrades the platform to the
 * old behaviour instead of silently dropping the message. Assistants are
 * alerted through the dashboard notification bell only when both channels
 * fail, which keeps the bell quiet when a single number is at fault.
 */
class SendAttendanceNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly User $student) {}

    /**
     * Retries exist for transport blips, not for rejected numbers.
     */
    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    /**
     * Execute the job.
     */
    public function handle(WahaService $waha, SmsMisrService $sms): void
    {
        $profile = $this->student->studentProfile;
        $guardianPhone = $profile?->guardian_phone;

        if (blank($guardianPhone)) {
            Log::warning('No guardian phone available for student.', ['student_id' => $this->student->id]);

            return;
        }

        $message = "تم تسجيل حضور الطالب {$this->student->name} بنجاح";
        $chatId = PhoneNumber::toWhatsAppChatId($guardianPhone, $this->countryCode());

        // Stays null when the number cannot be normalised, in which case there
        // is no WhatsApp attempt to log and only the SMS fallback runs.
        $log = null;

        if ($chatId === null) {
            Log::warning('Guardian phone cannot be normalised to a WhatsApp chat id.', [
                'student_id' => $this->student->id,
                'phone' => $guardianPhone,
            ]);
        } else {
            $result = $waha->sendText($chatId, $message);

            $log = WhatsappLog::record(
                result: $result,
                chatId: $chatId,
                phone: $guardianPhone,
                message: $message,
                source: WhatsappLogSource::Auto,
                student: $this->student,
                recipientName: $profile?->guardian_name,
            );

            if ($result->ok) {
                return;
            }

            Log::warning('WhatsApp attendance notification failed, falling back to SMS.', [
                'student_id' => $this->student->id,
                'error' => $result->error,
            ]);
        }

        $smsSent = $sms->send($guardianPhone, $message);

        $log?->update(['sms_fallback_sent' => $smsSent]);

        if (! $smsSent) {
            // Both channels refused the message. Throwing here would only
            // re-bill SMSMisr for a number that is already known bad, so
            // report it to the assistants directly instead of retrying.
            $this->failed(new RuntimeException('تعذّر الإرسال عبر واتساب أو SMS للطالب '.$this->student->name));
        }
    }

    /**
     * Terminal failure handler: a guardian has silently received nothing, so
     * raise it on the dashboard notification bell.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Attendance notification job failed.', [
            'student_id' => $this->student->id,
            'exception' => $exception?->getMessage(),
        ]);

        Notification::send(
            User::query()->where('role', UserRole::Assistant->value)->get(),
            new WhatsappSendFailed(
                studentId: $this->student->id,
                studentName: $this->student->name,
                reason: $exception?->getMessage() ?? 'فشل غير معروف',
            )
        );
    }

    private function countryCode(): string
    {
        return (string) config('services.waha.default_country_code', '20');
    }
}
