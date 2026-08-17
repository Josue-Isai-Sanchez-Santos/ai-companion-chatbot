<?php

namespace App\Actions\Messages;

use App\Ai\Agents\CharacterAgent;
use App\Enums\MessageRole;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SendMessageAction
{
    public function __construct(
        private readonly CharacterAgent $characterAgent
    ) {}

    /**
     * @return array{user: Message, assistant: Message}
     */
    public function execute(
        User $user,
        Conversation $conversation,
        string $content
    ): array {
        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        $validated = Validator::make(
            [
                'message' => trim($content),
            ],
            SendMessageRequest::messageRules(),
            SendMessageRequest::messageValidationMessages()
        )->validate();

        $reply = $this->characterAgent->reply(
            $user,
            $conversation,
            $validated['message']
        );

        return DB::transaction(
            function () use (
                $conversation,
                $validated,
                $reply
            ): array {
                $parentMessageId = $conversation
                    ->messages()
                    ->latest('created_at')
                    ->latest('id')
                    ->value('id');

                $userMessage = $conversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $parentMessageId,
                        'role' => MessageRole::User,
                        'content' => $validated['message'],
                        'metadata' => null,
                        'token_count' => null,
                        'status' => Message::STATUS_COMPLETED,
                    ]);

                $assistantMessage = $conversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $userMessage->id,
                        'role' => MessageRole::Assistant,
                        'content' => $reply->content,
                        'metadata' => $reply->metadata,
                        'token_count' => $reply->tokenCount,
                        'status' => $reply->status,
                    ]);

                $conversation->forceFill([
                    'last_message_at' => $assistantMessage->created_at,
                ])->save();

                $conversation
                    ->userCharacterProfile()
                    ->update([
                        'last_interaction_at' => $assistantMessage->created_at,
                    ]);

                return [
                    'user' => $userMessage,
                    'assistant' => $assistantMessage,
                ];
            }
        );
    }
}
