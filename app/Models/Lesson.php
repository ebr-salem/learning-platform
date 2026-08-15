<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['chapter_name', 'title', 'duration_minutes', 'video_url', 'thumbnail_url', 'about_lesson', 'what_you_will_learn', 'notes'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'what_you_will_learn' => 'array',
        ];
    }
}
