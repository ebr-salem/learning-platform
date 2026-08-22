<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'student_code', 'grade', 'profile_image', 'qr_code_string', 'dob', 'guardian_name', 'guardian_phone'])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    public const GRADES = [
        'الصف الأول',
        'الصف الثاني',
        'الصف الثالث',
        'الصف الرابع',
        'الصف الخامس',
        'الصف السادس',
    ];

    protected static function booted(): void
    {
        static::creating(function (StudentProfile $profile): void {
            $profile->qr_code_string ??= (string) Str::uuid();

            // Only generate a code if one wasn't manually provided
            if (empty($profile->student_code)) {

                do {
                    $code = 'STU-' . random_int(1000, 9999);
                } while (self::where('student_code', $code)->exists());

                // Inject the code into the profile right before it hits MySQL
                $profile->student_code = $code;
            }
        });


    }

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}