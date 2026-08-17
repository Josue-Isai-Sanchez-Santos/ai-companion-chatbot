<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteConversationAction
{
    public function execute(
        User $user,
        Conversation $conversation
    ): void {
        Gate::forUser($user)->authorize(
            'delete',
            $conversation
        );

        $conversation->delete();
    }
}
