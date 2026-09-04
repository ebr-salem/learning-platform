<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YoutubeUrl implements ValidationRule
{
    private const PATTERNS = [
        '~^https?://(?:www\.)?youtube\.com/watch\?v=[A-Za-z0-9_-]{11}(?:&.*)?$~',
        '~^https?://youtu\.be/[A-Za-z0-9_-]{11}$~',
        '~^https?://(?:www\.)?youtube\.com/shorts/[A-Za-z0-9_-]{11}$~',
        '~^https?://img\.youtube\.com/vi/[A-Za-z0-9_-]{11}/[A-Za-z]+\.jpg$~',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, (string) $value)) {
                return;
            }
        }

        $fail(__('يجب أن يكون الرابط رابط يوتيوب صالحًا.'));
    }
}