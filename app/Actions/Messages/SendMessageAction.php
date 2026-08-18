<?php

namespace App\Actions\Messages;

use App\Ai\Agents\CharacterAgent;
use App\Ai\Exceptions\AiGatewayException;
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
     * @return array{
     *     user: Message,
     *     assistant: Message|null,
     *     error: string|null
     * }
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

        /*
         * The user message is committed BEFORE
         * contacting the external AI provider.
         */
        $userMessage = DB::transaction(
            function () use (
                $conversation,
                $validated
            ): Message {
                $parentMessageId = $conversation
                    ->messages()
                    ->latest('created_at')
                    ->latest('id')
                    ->value('id');

                $message = $conversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $parentMessageId,

                        'role' => MessageRole::User,

                        'content' => $validated['message'],

                        'metadata' => null,
                        'token_count' => null,

                        'status' => Message::STATUS_COMPLETED,
                    ]);

                $conversation->forceFill([
                    'last_message_at' => $message->created_at,
                ])->save();

                $conversation
                    ->userCharacterProfile()
                    ->update([
                        'last_interaction_at' => $message->created_at,
                    ]);

                return $message;
            }
        );

        try {
            $reply = $this
                ->characterAgent
                ->reply(
                    $user,
                    $conversation,
                    $userMessage->content,
                    persistedMessage: $userMessage
                );
        } catch (AiGatewayException $exception) {
            report($exception);

            return [
                'user' => $userMessage,
                'assistant' => null,

                'error' => 'No fue posible obtener una respuesta de IA. '
                    .'Tu mensaje quedó guardado.',
            ];
        }

        /*
         * Only persist the assistant after a
         * complete provider response exists.
         */
        $assistantMessage = DB::transaction(
            function () use (
                $conversation,
                $userMessage,
                $reply
            ): Message {
                $message = $conversation
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
                    'last_message_at' => $message->created_at,
                ])->save();

                $conversation
                    ->userCharacterProfile()
                    ->update([
                        'last_interaction_at' => $message->created_at,
                    ]);

                return $message;
            }
        );

        return [
            'user' => $userMessage,
            'assistant' => $assistantMessage,
            'error' => null,
        ];
    }
}
