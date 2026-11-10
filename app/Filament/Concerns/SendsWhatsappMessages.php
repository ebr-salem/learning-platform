<?php

namespace App\Filament\Concerns;

use App\Enums\WhatsappLogSource;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Services\WahaSendResult;
use App\Services\WahaService;
use App\Support\PhoneNumber;
use Filament\Notifications\Notification;

/**
 * Shared plumbing for the two dashboard screens that put a WhatsApp message
 * on the wire: the settings page test send and the ad-hoc send page.
 *
 * Every send is logged, so anything sent from the dashboard is traceable in
 * the same place as the automatic attendance notifications.
 */
trait SendsWhatsappMessages
{
    /**
     * Normalise the number, send, log, and report the outcome as a toast.
     *
     * @param  string  $phone  A local or international number, or a ready chat id.
     * @return bool Whether the message was accepted by WAHA.
     */
    protected function sendWhatsappMessage(
        string $phone,
        string $message,
        WhatsappLogSource $source,
        ?User $student = null,
        ?string $recipientName = null,
    ): bool {
        $chatId = PhoneNumber::toWhatsAppChatId($phone, $this->whatsappCountryCode());

        if ($chatId === null) {
            $this->whatsappNotification()
                ->danger()
                ->title('رقم غير صالح')
                ->body("تعذّر تحويل الرقم «{$phone}» إلى معرف واتساب.")
                ->send();

            return false;
        }

        $result = app(WahaService::class)->sendText($chatId, $message);

        WhatsappLog::record(
            result: $result,
            chatId: $chatId,
            phone: $phone,
            message: $message,
            source: $source,
            student: $student,
            recipientName: $recipientName,
        );

        $this->reportWhatsappResult($result, $chatId);

        return $result->ok;
    }

    /**
     * Surface a send result as a toast, keeping the WAHA error text intact
     * because it is the only clue about rate limits and session problems.
     */
    protected function reportWhatsappResult(WahaSendResult $result, string $chatId): void
    {
        if (! $result->ok) {
            $this->whatsappNotification()
                ->danger()
                ->title('فشل إرسال الرسالة')
                ->body($result->error ?? 'خطأ غير معروف')
                ->persistent()
                ->send();

            return;
        }

        $this->whatsappNotification()
            ->success()
            ->title('تم إرسال الرسالة')
            ->body("المستلم: {$chatId} — معرف الرسالة: {$result->messageId}")
            ->send();
    }

    protected function whatsappNotification(): Notification
    {
        return Notification::make();
    }

    protected function whatsappCountryCode(): string
    {
        return (string) config('services.waha.default_country_code', '20');
    }
}
