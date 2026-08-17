<?php

namespace App\Livewire\Chat;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Models\Character;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Chat')]
class ChatPage extends Component
{
    public function mount(
        CreateUserCharacterProfileAction $createProfile
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $character = Character::query()
            ->active()
            ->orderBy('id')
            ->firstOrFail();

        $createProfile->execute(
            $user,
            $character
        );
    }

    public function render()
    {
        return view('livewire.chat.chat-page');
    }
}
