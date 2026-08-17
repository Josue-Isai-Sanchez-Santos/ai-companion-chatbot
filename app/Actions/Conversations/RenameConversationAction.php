<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RenameConversationAction
{
    public function execute(
        User $user,
        Conversation $conversation,
        string $title
    ): Conversation {
        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        $validated = Validator::make(
            [
                'title' => trim($title),
            ],
            [
                'title' => [
                    'required',
                    'string',
                    'max:160',
                ],
            ]
        )->validate();

        $conversation->update([
            'title' => $validated['title'],
        ]);

        return $conversation->refresh();
    }
}
