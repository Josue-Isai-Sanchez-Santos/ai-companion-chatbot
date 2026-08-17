<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class MessageList extends Component
{
    #[Locked]
    public int $conversationId;

    public function mount(
        int $conversationId
    ): void {
        $this->conversationId = $conversationId;
    }

    #[On('messages-updated')]
    public function refreshMessages(
        int $conversationId
    ): void {
        if ($conversationId !== $this->conversationId) {
            return;
        }
    }

    public function render(): View
    {
        $conversation = Conversation::query()
            ->findOrFail($this->conversationId);

        Gate::authorize(
            'view',
            $conversation
        );

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get();

        return view(
            'livewire.chat.message-list',
            [
                'messages' => $messages,
            ]
        );
    }
}
