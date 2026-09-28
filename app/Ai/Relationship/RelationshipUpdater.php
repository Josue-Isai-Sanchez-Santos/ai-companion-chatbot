<?php

namespace App\Ai\Relationship;

use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class RelationshipUpdater
{
    /**
     * @var list<string>
     */
    private const METRICS = [
        'trust',
        'affection',
        'familiarity',
        'tension',
    ];

    public function apply(
        UserCharacterProfile $profile,
        Conversation $conversation,
        Message $userMessage,
        Message $assistantMessage,
        RelationshipChange $change
    ): ?RelationshipEvent {
        if (
            ! $change->significant
            || trim(
                $change->eventSummary
            ) === ''
        ) {
            return null;
        }

        $this->assertValidTurn(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage
        );

        $existing =
            RelationshipEvent::query()
                ->where(
                    'assistant_message_id',
                    $assistantMessage->id
                )
                ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(
            function () use (
                $profile,
                $conversation,
                $userMessage,
                $assistantMessage,
                $change
            ): ?RelationshipEvent {
                $lockedProfile =
                    UserCharacterProfile::query()
                        ->whereKey(
                            $profile->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $existing =
                    RelationshipEvent::query()
                        ->where(
                            'assistant_message_id',
                            $assistantMessage->id
                        )
                        ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $requested =
                    $change->deltas();

                $before = [];
                $after = [];
                $applied = [];

                foreach (
                    self::METRICS
                    as $metric
                ) {
                    $before[$metric] =
                        (int)
                        $lockedProfile
                            ->getAttribute(
                                $metric
                            );

                    $proposal =
                        $this
                            ->limitProposal(
                                $metric,
                                $requested[$metric]
                            );

                    $minimum =
                        (int) config(
                            "relationship.metrics.{$metric}.min",
                            0
                        );

                    $maximum =
                        (int) config(
                            "relationship.metrics.{$metric}.max",
                            100
                        );

                    $after[$metric] =
                        max(
                            $minimum,
                            min(
                                $maximum,
                                $before[$metric]
                                    + $proposal
                            )
                        );

                    $applied[$metric] =
                        $after[$metric]
                        - $before[$metric];
                }

                if (
                    collect(
                        $applied
                    )->every(
                        static fn (
                            int $delta
                        ): bool =>
                            $delta === 0
                    )
                ) {
                    return null;
                }

                $fromStage =
                    $lockedProfile
                        ->relationship_stage;

                $targetStage =
                    $this->targetStage(
                        $after
                    );

                $toStage =
                    $this->moveOneStage(
                        $fromStage,
                        $targetStage
                    );

                $lockedProfile
                    ->forceFill([
                        'trust' =>
                            $after['trust'],

                        'affection' =>
                            $after['affection'],

                        'familiarity' =>
                            $after['familiarity'],

                        'tension' =>
                            $after['tension'],

                        'relationship_stage' =>
                            $toStage,
                    ])
                    ->save();

                return RelationshipEvent::query()
                    ->create([
                        'user_character_profile_id' =>
                            $lockedProfile->id,

                        'conversation_id' =>
                            $conversation->id,

                        'user_message_id' =>
                            $userMessage->id,

                        'assistant_message_id' =>
                            $assistantMessage->id,

                        'event_summary' =>
                            Str::limit(
                                trim(
                                    $change
                                        ->eventSummary
                                ),
                                500,
                                ''
                            ),

                        'from_stage' =>
                            $fromStage,

                        'to_stage' =>
                            $toStage,

                        'requested_trust_delta' =>
                            $requested['trust'],

                        'applied_trust_delta' =>
                            $applied['trust'],

                        'trust_before' =>
                            $before['trust'],

                        'trust_after' =>
                            $after['trust'],

                        'requested_affection_delta' =>
                            $requested['affection'],

                        'applied_affection_delta' =>
                            $applied['affection'],

                        'affection_before' =>
                            $before['affection'],

                        'affection_after' =>
                            $after['affection'],

                        'requested_familiarity_delta' =>
                            $requested['familiarity'],

                        'applied_familiarity_delta' =>
                            $applied['familiarity'],

                        'familiarity_before' =>
                            $before['familiarity'],

                        'familiarity_after' =>
                            $after['familiarity'],

                        'requested_tension_delta' =>
                            $requested['tension'],

                        'applied_tension_delta' =>
                            $applied['tension'],

                        'tension_before' =>
                            $before['tension'],

                        'tension_after' =>
                            $after['tension'],
                    ]);
            }
        );
    }

    private function limitProposal(
        string $metric,
        int $proposal
    ): int {
        $limit = max(
            0,
            (int) config(
                "relationship.max_delta_per_event.{$metric}",
                3
            )
        );

        return max(
            -$limit,
            min(
                $limit,
                $proposal
            )
        );
    }

    /**
     * @param  array{
     *     trust: int,
     *     affection: int,
     *     familiarity: int,
     *     tension: int
     * }  $metrics
     */
    private function targetStage(
        array $metrics
    ): RelationshipStage {
        $target =
            RelationshipStage::Strangers;

        foreach (
            RelationshipStage::cases()
            as $stage
        ) {
            $thresholds =
                config(
                    "relationship.stage_thresholds.{$stage->value}"
                );

            if (
                ! is_array(
                    $thresholds
                )
            ) {
                continue;
            }

            if (
                $metrics['trust']
                    >= (
                        $thresholds['trust']
                        ?? 0
                    )
                && $metrics['affection']
                    >= (
                        $thresholds['affection']
                        ?? 0
                    )
                && $metrics['familiarity']
                    >= (
                        $thresholds['familiarity']
                        ?? 0
                    )
                && $metrics['tension']
                    <= (
                        $thresholds['max_tension']
                        ?? 100
                    )
            ) {
                $target = $stage;
            }
        }

        return $target;
    }

    private function moveOneStage(
        RelationshipStage $current,
        RelationshipStage $target
    ): RelationshipStage {
        $stages =
            RelationshipStage::cases();

        $currentIndex =
            array_search(
                $current,
                $stages,
                true
            );

        $targetIndex =
            array_search(
                $target,
                $stages,
                true
            );

        if (
            $currentIndex === false
            || $targetIndex === false
        ) {
            return $current;
        }

        if (
            $targetIndex
            > $currentIndex
        ) {
            return $stages[
                $currentIndex + 1
            ];
        }

        if (
            $targetIndex
            < $currentIndex
        ) {
            return $stages[
                $currentIndex - 1
            ];
        }

        return $current;
    }

    private function assertValidTurn(
        UserCharacterProfile $profile,
        Conversation $conversation,
        Message $userMessage,
        Message $assistantMessage
    ): void {
        if (
            $conversation
                ->user_character_profile_id
                !== $profile->id
            || $userMessage
                ->conversation_id
                !== $conversation->id
            || $assistantMessage
                ->conversation_id
                !== $conversation->id
            || $userMessage->role
                !== MessageRole::User
            || $assistantMessage->role
                !== MessageRole::Assistant
            || $userMessage->status
                !== Message::STATUS_COMPLETED
            || $assistantMessage->status
                !== Message::STATUS_COMPLETED
            || $assistantMessage
                ->parent_message_id
                !== $userMessage->id
        ) {
            throw new LogicException(
                'Invalid conversation turn for relationship update.'
            );
        }
    }
}
