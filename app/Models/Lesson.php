<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

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

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function scopeForGroup(Builder $query, Group|int $group): void
    {
        $query->whereHas('groups', function (Builder $query) use ($group): void {
            $query->where('groups.id', $group instanceof Group ? $group->getKey() : $group);
        });
    }

    /**
     * Only students in an attached group can see the lesson.
     * Assistants keep full visibility; users without a profile see nothing.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAssistant()) {
            return;
        }

        $groupId = $user->studentProfile?->group_id;

        if ($groupId === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->forGroup($groupId);
    }
}