<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

final class CharacterPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return true;
    }

    public function view(
        User $user,
        Character $character
    ): bool {
        return $character->is_active;
    }

    public function create(
        User $user
    ): bool {
        return false;
    }

    public function update(
        User $user,
        Character $character
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        Character $character
    ): bool {
        return false;
    }
}
