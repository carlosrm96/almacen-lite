<?php

namespace App\Modules\Access\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update');
    }

    /** Nadie puede borrarse a sí mismo: dejaría el sistema sin su propio operador. */
    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $user->id !== $target->id;
    }
}
