<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateConversationAction
{
    public function execute(
        User $user,
        UserCharacterProfile $profile,
        ?string $title = null
    ): Conversation {
        Gate::forUser($user)->authorize(
            'create',
            [
                Conversation::class,
                $profile,
            ]
        );

        $title = trim($title ?? '');

        if ($title === '') {
            $title = 'Nueva conversación';
        }

        $validated = Validator::make(
            [
                'title' => $title,
            ],
            [
                'title' => [
                    'required',
                    'string',
                    'max:160',
                ],
            ]
        )->validate();

        return $profile
            ->conversations()
            ->create([
                'title' => $validated['title'],
            ]);
    }
}
