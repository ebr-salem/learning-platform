<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\FinancialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['title', 'description', 'user_id', 'created_by'])]
class Financial extends Model
{
    /** @use HasFactory<FinancialFactory> */
    use HasFactory;

    /**
     * Ensure only students can own a financial record, even outside Filament forms.
     */
    protected static function booted(): void
    {
        static::saving(function (self $financial): void {
            $userId = $financial->user_id;

            if (! filled($userId)) {
                return;
            }

            $isStudent = User::query()
                ->whereKey($userId)
                ->where('role', UserRole::Student->value)
                ->exists();

            if (! $isStudent) {
                throw new InvalidArgumentException('السجل المالي يجب أن يكون مرتبطاً بطالب.');
            }

            $creatorId = $financial->created_by;

            if (! filled($creatorId)) {
                return;
            }

            $isAssistant = User::query()
                ->whereKey($creatorId)
                ->where('role', UserRole::Assistant->value)
                ->exists();

            if (! $isAssistant) {
                throw new InvalidArgumentException('منشئ السجل المالي يجب أن يكون مساعداً.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
