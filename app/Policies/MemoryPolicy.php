<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserCharacterProfile;

class MemoryPolicy
{
    public function viewAny(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id
            === $user->id;
    }

    public function view(
        User $user,
        Memory $memory
    ): bool {
        return $memory
            ->userCharacterProfile()
            ->where(
                'user_id',
                $user->id
            )
            ->exists();
    }

    public function create(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id
            === $user->id;
    }

    public function update(
        User $user,
        Memory $memory
    ): bool {
        return $this->view(
            $user,
            $memory
        );
    }

    public function delete(
        User $user,
        Memory $memory
    ): bool {
        return $this->view(
            $user,
            $memory
        );
    }
}
