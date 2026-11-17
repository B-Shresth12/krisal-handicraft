<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_active;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_active
            && ! $user->is($model)
            && ! $this->isLastActive($model);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->is_active
            && ! $user->is($model)
            && ! $this->isLastActive($model);
    }

    private function isLastActive(User $user): bool
    {
        return $user->is_active
            && ! User::query()
                ->where('is_active', true)
                ->whereKeyNot($user->getKey())
                ->exists();
    }
}
