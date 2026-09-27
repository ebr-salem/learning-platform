<?php

namespace App\Notifications;

use App\Filament\Resources\WhatsappLogResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Raised on the dashboard notification bell when a guardian could not be
 * reached through either WhatsApp or SMS.
 *
 * The payload follows Filament's own database-notification format - the
 * panel only lists rows whose data carries `format => filament`, and each
 * entry is hydrated back into a Filament notification.
 */
class WhatsappSendFailed extends Notification
{
    use Queueable;

    /**
     * @param  int  $studentId  Lets the assistant jump straight to the student.
     */
    public function __construct(
        public readonly int $studentId,
        public readonly string $studentName,
        public readonly string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => 'فشل إشعار واتساب',
            'body' => "تعذّر إبلاغ ولي أمر الطالب ({$this->studentName}) تسجيل الحضور: {$this->reason}",
            'status' => 'danger',
            'icon' => Heroicon::OutlinedExclamationTriangle,
            'iconColor' => 'danger',
            'actions' => [
                Action::make('viewLog')
                    ->label('عرض السجل')
                    ->icon(Heroicon::OutlinedArrowLeftOnRectangle)
                    ->url(WhatsappLogResource::getUrl('index', panel: 'admin'))
                    ->toArray(),
            ],
            'student_id' => $this->studentId,
        ];
    }
}
