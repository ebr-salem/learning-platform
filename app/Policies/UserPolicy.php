<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAssistant();
    }

    public function view(User $user, User $record): bool
    {
        return $user->isAssistant();
    }

    public function create(User $user): bool
    {
        return $user->isAssistant();
    }

    public function update(User $user, User $record): bool
    {
        return $user->isAssistant();
    }

    public function delete(User $user, User $record): bool
    {
        return $user->isAssistant();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAssistant();
    }
}