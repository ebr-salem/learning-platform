<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAssistant();
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $user->isAssistant();
    }

    public function create(User $user): bool
    {
        return $user->isAssistant();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->isAssistant();
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->isAssistant();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAssistant();
    }
}