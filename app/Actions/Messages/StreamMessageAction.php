<?php

namespace App\Actions\Messages;

use App\Ai\DTOs\GeneratedReply;
use App\Enums\MessageRole;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class StreamMessageAction
{
    /**
     * @return array{
     *     conversation: Conversation,
     *     user: Message,
     *     assistant: Message
     * }
     */
    public function start(
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
                $lockedConversation = Conversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureNoActiveStream(
                    $lockedConversation
                );

                $parentMessageId = $lockedConversation
                    ->messages()
                    ->latest('created_at')
                    ->latest('id')
                    ->value('id');

                $userMessage = $lockedConversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $parentMessageId,

                        'role' => MessageRole::User,

                        'content' => $validated['message'],

                        'metadata' => null,
                        'token_count' => null,

                        'status' => Message::STATUS_COMPLETED,
                    ]);

                $assistantMessage = $lockedConversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $userMessage->id,

                        'role' => MessageRole::Assistant,

                        'content' => '',

                        'metadata' => [
                            'stream' => [
                                'attempt' => 1,

                                'started_at' => now()
                                    ->toISOString(),
                            ],
                        ],

                        'token_count' => null,

                        'status' => Message::STATUS_STREAMING,
                    ]);

                $lockedConversation->forceFill([
                    'last_message_at' => $assistantMessage
                        ->created_at,
                ])->save();

                $lockedConversation
                    ->userCharacterProfile()
                    ->update([
                        'last_interaction_at' => $userMessage
                            ->created_at,
                    ]);

                return [
                    'conversation' => $lockedConversation,
                    'user' => $userMessage,
                    'assistant' => $assistantMessage,
                ];
            }
        );
    }

    /**
     * @return array{
     *     conversation: Conversation,
     *     user: Message,
     *     assistant: Message
     * }
     */
    public function retry(
        User $user,
        Message $assistant
    ): array {
        $conversation = $assistant
            ->conversation()
            ->firstOrFail();

        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        return DB::transaction(
            function () use (
                $conversation,
                $assistant
            ): array {
                $lockedConversation = Conversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedAssistant = Message::query()
                    ->whereKey($assistant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedAssistant->conversation_id
                        !== $lockedConversation->id
                    || $lockedAssistant->role
                        !== MessageRole::Assistant
                    || ! in_array(
                        $lockedAssistant->status,
                        [
                            Message::STATUS_FAILED,
                            Message::STATUS_INTERRUPTED,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'assistant_message_id' =>
                            'Esta respuesta no se puede reintentar.',
                    ]);
                }

                $this->ensureNoActiveStream(
                    $lockedConversation,
                    $lockedAssistant->id
                );

                $userMessage = Message::query()
                    ->whereKey(
                        $lockedAssistant->parent_message_id
                    )
                    ->firstOrFail();

                if (
                    $userMessage->conversation_id
                        !== $lockedConversation->id
                    || $userMessage->role
                        !== MessageRole::User
                ) {
                    throw new LogicException(
                        'Assistant message does not have a valid parent user message.'
                    );
                }

                $metadata = $lockedAssistant
                    ->metadata
                    ?? [];

                $attempt = max(
                    1,
                    (int) data_get(
                        $metadata,
                        'stream.attempt',
                        1
                    )
                ) + 1;

                data_forget(
                    $metadata,
                    'stream.finished_at'
                );

                data_forget(
                    $metadata,
                    'stream.failure'
                );

                data_forget(
                    $metadata,
                    'stream.interrupted_at'
                );

                data_set(
                    $metadata,
                    'stream.attempt',
                    $attempt
                );

                data_set(
                    $metadata,
                    'stream.started_at',
                    now()->toISOString()
                );

                data_set(
                    $metadata,
                    'stream.status',
                    Message::STATUS_STREAMING
                );

                $lockedAssistant->forceFill([
                    'content' => '',
                    'metadata' => $metadata,
                    'token_count' => null,

                    'status' => Message::STATUS_STREAMING,
                ])->save();

                $lockedConversation->forceFill([
                    'last_message_at' => now(),
                ])->save();

                return [
                    'conversation' => $lockedConversation,
                    'user' => $userMessage,
                    'assistant' => $lockedAssistant->fresh(),
                ];
            }
        );
    }

    public function complete(
        Message $assistant,
        GeneratedReply $reply
    ): Message {
        return DB::transaction(
            function () use (
                $assistant,
                $reply
            ): Message {
                $message = Message::query()
                    ->whereKey($assistant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $message->status
                    === Message::STATUS_COMPLETED
                ) {
                    return $message;
                }

                if (
                    $message->status
                    !== Message::STATUS_STREAMING
                ) {
                    throw new LogicException(
                        'Only a streaming message may be completed.'
                    );
                }

                $metadata = array_replace_recursive(
                    $message->metadata ?? [],
                    $reply->metadata
                );

                data_set(
                    $metadata,
                    'stream.finished_at',
                    now()->toISOString()
                );

                data_set(
                    $metadata,
                    'stream.status',
                    Message::STATUS_COMPLETED
                );

                $message->forceFill([
                    'content' => $reply->content,
                    'metadata' => $metadata,
                    'token_count' => $reply->tokenCount,

                    'status' => Message::STATUS_COMPLETED,
                ])->save();

                $message
                    ->conversation()
                    ->update([
                        'last_message_at' => now(),
                    ]);

                return $message->fresh();
            }
        );
    }

    public function fail(
        Message $assistant,
        string $partialContent,
        Throwable $failure
    ): Message {
        return $this->transition(
            $assistant,
            Message::STATUS_FAILED,
            $partialContent,
            $failure
        );
    }

    public function interrupt(
        Message $assistant,
        string $partialContent
    ): Message {
        return $this->transition(
            $assistant,
            Message::STATUS_INTERRUPTED,
            $partialContent
        );
    }

    private function transition(
        Message $assistant,
        string $status,
        string $partialContent,
        ?Throwable $failure = null
    ): Message {
        return DB::transaction(
            function () use (
                $assistant,
                $status,
                $partialContent,
                $failure
            ): Message {
                $message = Message::query()
                    ->whereKey($assistant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $message->status
                    !== Message::STATUS_STREAMING
                ) {
                    return $message;
                }

                $metadata = $message
                    ->metadata
                    ?? [];

                data_set(
                    $metadata,
                    'stream.finished_at',
                    now()->toISOString()
                );

                data_set(
                    $metadata,
                    'stream.status',
                    $status
                );

                if ($failure !== null) {
                    data_set(
                        $metadata,
                        'stream.failure',
                        $failure::class
                    );
                }

                if (
                    $status
                    === Message::STATUS_INTERRUPTED
                ) {
                    data_set(
                        $metadata,
                        'stream.interrupted_at',
                        now()->toISOString()
                    );
                }

                $message->forceFill([
                    'content' => $partialContent,
                    'metadata' => $metadata,
                    'token_count' => null,
                    'status' => $status,
                ])->save();

                $message
                    ->conversation()
                    ->update([
                        'last_message_at' => now(),
                    ]);

                return $message->fresh();
            }
        );
    }

    private function ensureNoActiveStream(
        Conversation $conversation,
        ?int $ignoredAssistantId = null
    ): void {
        $query = $conversation
            ->messages()
            ->where(
                'role',
                MessageRole::Assistant->value
            )
            ->where(
                'status',
                Message::STATUS_STREAMING
            );

        if ($ignoredAssistantId !== null) {
            $query->where(
                'id',
                '!=',
                $ignoredAssistantId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'message' =>
                    'Ya hay una respuesta en curso para esta conversación.',
            ]);
        }
    }
}
