<?php

namespace App\Actions\Messages;

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

        return DB::transaction(
            function () use (
                $conversation,
                $validated
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
                        'content' => 'Respuesta simulada: recibí tu mensaje correctamente.',
                        'metadata' => [
                            'simulated' => true,
                        ],
                        'token_count' => null,
                        'status' => Message::STATUS_COMPLETED,
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
