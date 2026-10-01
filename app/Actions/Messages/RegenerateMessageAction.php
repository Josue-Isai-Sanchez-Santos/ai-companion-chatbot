<?php

namespace App\Actions\Messages;

use App\Ai\Agents\CharacterAgent;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Exceptions\AiGatewayException;
use App\Ai\Safety\ChatSafetyPolicy;
use App\Ai\State\CharacterStateResolver;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class RegenerateMessageAction
{
    public function __construct(
        private readonly CharacterAgent $characterAgent,
        private readonly CharacterStateResolver $stateResolver,
        private readonly ChatSafetyPolicy $chatSafetyPolicy,
    ) {}

    /**
     * @return array{
     *     previous: Message,
     *     assistant: Message|null,
     *     error: string|null
     * }
     */
    public function execute(
        User $user,
        Message $assistant
    ): array {
        $conversation =
            $assistant
                ->conversation()
                ->firstOrFail();

        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        $this
            ->chatSafetyPolicy
            ->assertGenerationAllowed(
                $user
            );

        $preparation =
            $this->prepare(
                $conversation,
                $assistant
            );

        try {
            if (
                $preparation[
                    'relationship_event_id'
                ] !== null
            ) {
                $this
                    ->restoreMoodBeforeTarget(
                        $preparation[
                            'conversation_id'
                        ],
                        $preparation[
                            'user_message_id'
                        ],
                        $preparation[
                            'profile_id'
                        ]
                    );
            }

            $preparedConversation =
                Conversation::query()
                    ->findOrFail(
                        $preparation[
                            'conversation_id'
                        ]
                    );

            $userMessage =
                Message::query()
                    ->findOrFail(
                        $preparation[
                            'user_message_id'
                        ]
                    );

            $reply =
                $this
                    ->characterAgent
                    ->reply(
                        $user,
                        $preparedConversation,
                        $userMessage->content,
                        persistedMessage:
                            $userMessage
                    );

            if (
                $reply->status
                !== Message::STATUS_COMPLETED
            ) {
                throw new LogicException(
                    'A regenerated synchronous reply must be completed.'
                );
            }

            $replacement =
                $this->complete(
                    $preparation,
                    $reply
                );

            return [
                'previous' =>
                    Message::query()
                        ->findOrFail(
                            $preparation[
                                'previous_assistant_id'
                            ]
                        ),

                'assistant' =>
                    $replacement,

                'error' =>
                    null,
            ];
        } catch (Throwable $exception) {
            $this->rollback(
                $preparation
            );

            if (
                $exception
                instanceof AiGatewayException
            ) {
                report(
                    $exception
                );

                return [
                    'previous' =>
                        Message::query()
                            ->findOrFail(
                                $preparation[
                                    'previous_assistant_id'
                                ]
                            ),

                    'assistant' =>
                        null,

                    'error' =>
                        'No fue posible regenerar la respuesta. '
                        .'La respuesta anterior continúa seleccionada.',
                ];
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function prepare(
        Conversation $conversation,
        Message $assistant
    ): array {
        return DB::transaction(
            function () use (
                $conversation,
                $assistant
            ): array {
                $lockedConversation =
                    Conversation::query()
                        ->whereKey(
                            $conversation->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $lockedAssistant =
                    Message::query()
                        ->whereKey(
                            $assistant->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this
                    ->assertRegeneratable(
                        $lockedConversation,
                        $lockedAssistant
                    );

                $userMessage =
                    Message::query()
                        ->whereKey(
                            $lockedAssistant
                                ->parent_message_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $userMessage
                        ->conversation_id
                        !== $lockedConversation->id
                    || $userMessage->role
                        !== MessageRole::User
                    || $userMessage->status
                        !== Message::STATUS_COMPLETED
                    || ! $userMessage
                        ->is_active_branch
                ) {
                    throw new LogicException(
                        'Regeneration target does not have a valid active user parent.'
                    );
                }

                $profile =
                    UserCharacterProfile::query()
                        ->whereKey(
                            $lockedConversation
                                ->user_character_profile_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $relationshipEvent =
                    RelationshipEvent::query()
                        ->where(
                            'assistant_message_id',
                            $lockedAssistant->id
                        )
                        ->lockForUpdate()
                        ->first();

                $profileSnapshot = [
                    'mood' =>
                        $profile
                            ->current_mood
                            ->value,

                    'expression_id' =>
                        $profile
                            ->current_expression_id,
                ];

                if (
                    $relationshipEvent
                    !== null
                ) {
                    /*
                     * The selected branch is about to
                     * stop containing this event.
                     * Restore the numerical relationship
                     * state to what it was immediately
                     * before this response.
                     */
                    $profile->forceFill([
                        'trust' =>
                            $relationshipEvent
                                ->trust_before,

                        'affection' =>
                            $relationshipEvent
                                ->affection_before,

                        'familiarity' =>
                            $relationshipEvent
                                ->familiarity_before,

                        'tension' =>
                            $relationshipEvent
                                ->tension_before,

                        'relationship_stage' =>
                            $relationshipEvent
                                ->from_stage,
                    ])->save();
                }

                $summaryBefore =
                    $lockedConversation
                        ->summary;

                $summaryUpdatedAtBefore =
                    $lockedConversation
                        ->summary_updated_at;

                $summaryWasCleared =
                    $summaryUpdatedAtBefore
                        !== null
                    && $lockedAssistant
                        ->created_at
                        !== null
                    && $lockedAssistant
                        ->created_at
                        ->lte(
                            $summaryUpdatedAtBefore
                        );

                if (
                    $summaryWasCleared
                ) {
                    /*
                     * The existing summary may contain
                     * text from the branch that is now
                     * being deselected.
                     */
                    $lockedConversation
                        ->forceFill([
                            'summary' =>
                                null,

                            'summary_updated_at' =>
                                null,
                        ])
                        ->save();
                }

                $lockedAssistant
                    ->forceFill([
                        'is_active_branch' =>
                            false,
                    ])
                    ->save();

                $replacementMetadata = [
                    'regeneration' => [
                        'replaces_message_id' =>
                            $lockedAssistant->id,

                        'started_at' =>
                            now()
                                ->toISOString(),
                    ],
                ];

                $replacement =
                    $lockedConversation
                        ->messages()
                        ->create([
                            'parent_message_id' =>
                                $userMessage->id,

                            'role' =>
                                MessageRole::Assistant,

                            'content' =>
                                '',

                            'metadata' =>
                                $replacementMetadata,

                            'token_count' =>
                                null,

                            'status' =>
                                Message::STATUS_STREAMING,

                            'is_active_branch' =>
                                true,
                        ]);

                $lockedConversation
                    ->forceFill([
                        'last_message_at' =>
                            $replacement
                                ->created_at,
                    ])
                    ->save();

                return [
                    'conversation_id' =>
                        $lockedConversation->id,

                    'profile_id' =>
                        $profile->id,

                    'user_message_id' =>
                        $userMessage->id,

                    'previous_assistant_id' =>
                        $lockedAssistant->id,

                    'replacement_id' =>
                        $replacement->id,

                    'relationship_event_id' =>
                        $relationshipEvent?->id,

                    'summary_was_cleared' =>
                        $summaryWasCleared,

                    'summary_before' =>
                        $summaryBefore,

                    'summary_updated_at_before' =>
                        $summaryUpdatedAtBefore,

                    'mood_before' =>
                        $profileSnapshot[
                            'mood'
                        ],

                    'expression_id_before' =>
                        $profileSnapshot[
                            'expression_id'
                        ],
                ];
            }
        );
    }

    /**
     * @param  array<string, mixed>  $preparation
     */
    private function complete(
        array $preparation,
        GeneratedReply $reply
    ): Message {
        return DB::transaction(
            function () use (
                $preparation,
                $reply
            ): Message {
                $replacement =
                    Message::query()
                        ->whereKey(
                            $preparation[
                                'replacement_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! $replacement
                        ->is_active_branch
                    || $replacement->role
                        !== MessageRole::Assistant
                    || $replacement->status
                        !== Message::STATUS_STREAMING
                ) {
                    throw new LogicException(
                        'Regeneration placeholder is no longer valid.'
                    );
                }

                /*
                 * Internal branch metadata wins over
                 * provider metadata.
                 */
                $metadata =
                    array_replace_recursive(
                        $reply->metadata,
                        $replacement->metadata
                            ?? []
                    );

                data_set(
                    $metadata,
                    'regeneration.finished_at',
                    now()->toISOString()
                );

                $replacement
                    ->forceFill([
                        'content' =>
                            $reply->content,

                        'metadata' =>
                            $metadata,

                        'token_count' =>
                            $reply->tokenCount,

                        'status' =>
                            Message::STATUS_COMPLETED,
                    ])
                    ->save();

                Conversation::query()
                    ->whereKey(
                        $replacement
                            ->conversation_id
                    )
                    ->update([
                        'last_message_at' =>
                            $replacement
                                ->created_at,
                    ]);

                /*
                 * Intentionally NO memory extraction,
                 * relationship analysis, or summary
                 * refresh is dispatched here.
                 */
                return $replacement
                    ->fresh();
            }
        );
    }

    /**
     * @param  array<string, mixed>  $preparation
     */
    private function rollback(
        array $preparation
    ): void {
        DB::transaction(
            function () use (
                $preparation
            ): void {
                $replacement =
                    Message::query()
                        ->whereKey(
                            $preparation[
                                'replacement_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->first();

                if ($replacement !== null) {
                    $replacement->delete();
                }

                $previous =
                    Message::query()
                        ->whereKey(
                            $preparation[
                                'previous_assistant_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $previous
                    ->forceFill([
                        'is_active_branch' =>
                            true,
                    ])
                    ->save();

                $profile =
                    UserCharacterProfile::query()
                        ->whereKey(
                            $preparation[
                                'profile_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $preparation[
                        'relationship_event_id'
                    ] !== null
                ) {
                    $event =
                        RelationshipEvent::query()
                            ->find(
                                $preparation[
                                    'relationship_event_id'
                                ]
                            );

                    if ($event !== null) {
                        $profile
                            ->forceFill([
                                'trust' =>
                                    $event
                                        ->trust_after,

                                'affection' =>
                                    $event
                                        ->affection_after,

                                'familiarity' =>
                                    $event
                                        ->familiarity_after,

                                'tension' =>
                                    $event
                                        ->tension_after,

                                'relationship_stage' =>
                                    $event
                                        ->to_stage,
                            ])
                            ->save();
                    }
                }

                $profile
                    ->forceFill([
                        'current_mood' =>
                            $preparation[
                                'mood_before'
                            ],

                        'current_expression_id' =>
                            $preparation[
                                'expression_id_before'
                            ],
                    ])
                    ->save();

                $conversation =
                    Conversation::query()
                        ->whereKey(
                            $preparation[
                                'conversation_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $preparation[
                        'summary_was_cleared'
                    ]
                ) {
                    $conversation
                        ->forceFill([
                            'summary' =>
                                $preparation[
                                    'summary_before'
                                ],

                            'summary_updated_at' =>
                                $preparation[
                                    'summary_updated_at_before'
                                ],
                        ]);
                }

                $conversation
                    ->forceFill([
                        'last_message_at' =>
                            $previous
                                ->created_at,
                    ])
                    ->save();
            }
        );
    }

    private function restoreMoodBeforeTarget(
        int $conversationId,
        int $userMessageId,
        int $profileId
    ): void {
        $profile =
            UserCharacterProfile::query()
                ->findOrFail(
                    $profileId
                );

        $previousAssistant =
            Message::query()
                ->where(
                    'conversation_id',
                    $conversationId
                )
                ->activeBranch()
                ->where(
                    'role',
                    MessageRole::Assistant->value
                )
                ->where(
                    'status',
                    Message::STATUS_COMPLETED
                )
                ->where(
                    'id',
                    '<',
                    $userMessageId
                )
                ->latest(
                    'created_at'
                )
                ->latest(
                    'id'
                )
                ->first();

        if ($previousAssistant === null) {
            $this
                ->stateResolver
                ->reset(
                    $profile
                );

            return;
        }

        $previousEvent =
            RelationshipEvent::query()
                ->where(
                    'assistant_message_id',
                    $previousAssistant->id
                )
                ->first();

        if ($previousEvent === null) {
            $this
                ->stateResolver
                ->reset(
                    $profile
                );

            return;
        }

        $this
            ->stateResolver
            ->apply(
                $profile,
                $previousEvent
            );
    }

    private function assertRegeneratable(
        Conversation $conversation,
        Message $assistant
    ): void {
        if (
            $assistant
                ->conversation_id
                !== $conversation->id
            || $assistant->role
                !== MessageRole::Assistant
            || $assistant->status
                !== Message::STATUS_COMPLETED
            || ! $assistant
                ->is_active_branch
        ) {
            throw ValidationException::withMessages([
                'assistant_message_id' =>
                    'Esta respuesta no se puede regenerar.',
            ]);
        }

        $latestActiveMessageId =
            $conversation
                ->messages()
                ->activeBranch()
                ->latest(
                    'created_at'
                )
                ->latest(
                    'id'
                )
                ->value(
                    'id'
                );

        if (
            $latestActiveMessageId
            !== $assistant->id
        ) {
            throw ValidationException::withMessages([
                'assistant_message_id' =>
                    'Solo se puede regenerar la última respuesta activa.',
            ]);
        }
    }
}
