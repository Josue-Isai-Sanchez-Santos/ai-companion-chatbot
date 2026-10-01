<?php

namespace App\Jobs;

use App\Ai\Agents\RelationshipAnalysisAgent;
use App\Ai\Prompts\RelationshipPromptBuilder;
use App\Ai\Relationship\RelationshipChange;
use App\Ai\Relationship\RelationshipUpdater;
use App\Ai\State\CharacterStateResolver;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\RelationshipEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;

final class UpdateRelationshipState implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $assistantMessageId,
    ) {
        $this->onQueue(
            (string) config(
                'relationship.analysis.queue',
                'relationship'
            )
        );

        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return sprintf(
            '%d:%d',
            $this->conversationId,
            $this->assistantMessageId
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            10,
            30,
            60,
        ];
    }

    public function handle(
        RelationshipAnalysisAgent $agent,
        RelationshipPromptBuilder $promptBuilder,
        RelationshipUpdater $updater,
        CharacterStateResolver $stateResolver
    ): void {
        if (
            ! (bool) config(
                'relationship.analysis.enabled',
                true
            )
        ) {
            return;
        }

        $conversation =
            Conversation::query()
                ->with(
                    'userCharacterProfile'
                )
                ->find(
                    $this->conversationId
                );

        if ($conversation === null) {
            return;
        }

        $assistant =
            $conversation
                ->messages()
                ->activeBranch()
                ->whereKey(
                    $this->assistantMessageId
                )
                ->where(
                    'role',
                    MessageRole::Assistant->value
                )
                ->where(
                    'status',
                    Message::STATUS_COMPLETED
                )
                ->first();

        if ($assistant === null) {
            return;
        }

        /*
         * Idempotency protection before invoking AI.
         */
        if (
            RelationshipEvent::query()
                ->where(
                    'assistant_message_id',
                    $assistant->id
                )
                ->exists()
        ) {
            return;
        }

        $userMessage =
            $conversation
                ->messages()
                ->activeBranch()
                ->whereKey(
                    $assistant
                        ->parent_message_id
                )
                ->where(
                    'role',
                    MessageRole::User->value
                )
                ->where(
                    'status',
                    Message::STATUS_COMPLETED
                )
                ->first();

        if ($userMessage === null) {
            return;
        }

        $profile =
            $conversation
                ->userCharacterProfile;

        $response = $agent
            ->prompt(
                $promptBuilder->build(
                    $profile,
                    $conversation->summary,
                    $userMessage,
                    $assistant
                ),

                provider:
                    $this->provider(),

                model:
                    $this->model(),

                timeout:
                    max(
                        1,
                        (int) config(
                            'relationship.analysis.timeout',
                            120
                        )
                    )
            );

        if (
            ! $response
                instanceof StructuredAgentResponse
        ) {
            throw new LogicException(
                'Relationship analysis agent did not return structured output.'
            );
        }

        $change =
            RelationshipChange::fromArray([
                'significant' =>
                    $response[
                        'significant'
                    ]
                    ?? false,

                'event_summary' =>
                    $response[
                        'event_summary'
                    ]
                    ?? '',

                'trust_delta' =>
                    $response[
                        'trust_delta'
                    ]
                    ?? 0,

                'affection_delta' =>
                    $response[
                        'affection_delta'
                    ]
                    ?? 0,

                'familiarity_delta' =>
                    $response[
                        'familiarity_delta'
                    ]
                    ?? 0,

                'tension_delta' =>
                    $response[
                        'tension_delta'
                    ]
                    ?? 0,
            ]);

        $event = $updater->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistant,
            $change
        );

        /*
         * Emotional state is resolved only after the
         * backend has validated and applied the
         * relationship proposal.
         */
        $stateResolver->apply(
            $profile->fresh(),
            $event
        );
    }

    private function provider(): string
    {
        $provider = trim(
            (string) config(
                'relationship.analysis.provider'
            )
        );

        if ($provider !== '') {
            return $provider;
        }

        return (string) config(
            'ai.chat.provider'
        );
    }

    private function model(): string
    {
        $model = trim(
            (string) config(
                'relationship.analysis.model'
            )
        );

        if ($model !== '') {
            return $model;
        }

        return (string) config(
            'ai.chat.model'
        );
    }
}
