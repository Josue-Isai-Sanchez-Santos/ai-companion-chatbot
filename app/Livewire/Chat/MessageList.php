<?php

namespace App\Livewire\Chat;

use App\Actions\Messages\RegenerateMessageAction;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

    public function regenerate(
        int $assistantMessageId,
        RegenerateMessageAction $regenerate
    ): void {
        $this->resetErrorBag(
            'regeneration'
        );

        $assistant =
            Message::query()
                ->findOrFail(
                    $assistantMessageId
                );

        abort_unless(
            $assistant
                ->conversation_id
                === $this->conversationId,
            404
        );

        /** @var User $user */
        $user = auth()->user();

        $result =
            $regenerate->execute(
                $user,
                $assistant
            );

        if (
            $result['error']
            !== null
        ) {
            $this->addError(
                'regeneration',
                $result['error']
            );
        }

        $this->dispatch(
            'messages-updated',
            conversationId:
                $this->conversationId
        );
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
            ->activeBranch()
            ->chronological()
            ->get();

        $latestActiveMessage =
            $messages->last();

        $latestCompletedAssistantId =
            $latestActiveMessage
                instanceof Message
            && $latestActiveMessage->role
                === MessageRole::Assistant
            && $latestActiveMessage->status
                === Message::STATUS_COMPLETED
                ? $latestActiveMessage->id
                : null;

        $latestAssistantAlternativeCount =
            $latestCompletedAssistantId
                === null
                ? 0
                : $conversation
                    ->messages()
                    ->where(
                        'parent_message_id',
                        $latestActiveMessage
                            ->parent_message_id
                    )
                    ->where(
                        'role',
                        MessageRole::Assistant->value
                    )
                    ->where(
                        'status',
                        Message::STATUS_COMPLETED
                    )
                    ->count();

        return view(
            'livewire.chat.message-list',
            [
                'messages' => $messages,

                'latestCompletedAssistantId' =>
                    $latestCompletedAssistantId,

                'latestAssistantAlternativeCount' =>
                    $latestAssistantAlternativeCount,
            ]
        );
    }
}
