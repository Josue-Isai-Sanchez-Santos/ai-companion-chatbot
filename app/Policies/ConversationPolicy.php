<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserCharacterProfile;

class ConversationPolicy
{
    public function viewAny(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id === $user->id;
    }

    public function view(
        User $user,
        Conversation $conversation
    ): bool {
        return $conversation
            ->userCharacterProfile()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id === $user->id;
    }

    public function update(
        User $user,
        Conversation $conversation
    ): bool {
        return $this->view(
            $user,
            $conversation
        );
    }

    public function delete(
        User $user,
        Conversation $conversation
    ): bool {
        return $this->view(
            $user,
            $conversation
        );
    }
}
