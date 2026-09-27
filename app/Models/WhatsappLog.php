<?php

namespace App\Models;

use App\Enums\WhatsappLogSource;
use App\Enums\WhatsappLogStatus;
use App\Services\WahaSendResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source',
    'student_id',
    'recipient_name',
    'phone',
    'chat_id',
    'session',
    'message',
    'status',
    'message_id',
    'http_status',
    'error',
    'sms_fallback_sent',
    'sent_at',
])]
class WhatsappLog extends Model
{
    /**
     * Persist the outcome of one WhatsApp send attempt.
     *
     * Every send in the app funnels through here, so the log always matches
     * what was actually sent. Arguments are named because there are enough of
     * them that positional calls get unreadable.
     */
    public static function record(
        WahaSendResult $result,
        string $chatId,
        string $message,
        WhatsappLogSource $source = WhatsappLogSource::Auto,
        ?string $phone = null,
        ?User $student = null,
        ?string $recipientName = null,
        ?string $session = null,
    ): self {
        return self::create([
            'source' => $source,
            'student_id' => $student?->id,
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'chat_id' => $chatId,
            'session' => $session ?? self::defaultSession(),
            'message' => $message,
            'status' => $result->ok ? WhatsappLogStatus::Sent : WhatsappLogStatus::Failed,
            'message_id' => $result->messageId,
            'http_status' => $result->httpStatus,
            'error' => $result->ok ? null : $result->error,
            'sent_at' => $result->ok ? now() : null,
        ]);
    }

    public static function defaultSession(): string
    {
        return (string) (config('services.waha.session') ?: 'default');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'source' => WhatsappLogSource::class,
            'status' => WhatsappLogStatus::class,
            'sms_fallback_sent' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WhatsappLogStatus::Failed);
    }
}
