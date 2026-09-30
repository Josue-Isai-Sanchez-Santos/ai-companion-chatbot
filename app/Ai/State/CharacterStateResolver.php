<?php

namespace App\Ai\State;

use App\Enums\CharacterMood;
use App\Models\RelationshipEvent;
use App\Models\UserCharacterProfile;

final class CharacterStateResolver
{
    /*
     * A one-point relationship movement is too small
     * to justify changing the visible emotional state.
     */
    private const EMOTIONAL_SIGNAL_THRESHOLD = 2;

    public function __construct(
        private readonly ExpressionResolver $expressionResolver
    ) {}

    public function determineMood(
        ?RelationshipEvent $event
    ): CharacterMood {
        if ($event === null) {
            return CharacterMood::Neutral;
        }

        $trust =
            $event->applied_trust_delta;

        $affection =
            $event->applied_affection_delta;

        $familiarity =
            $event->applied_familiarity_delta;

        $tension =
            $event->applied_tension_delta;

        /*
         * Strongly increasing tension has the highest
         * priority because conflict should not be
         * masked by simultaneous positive movement.
         */
        if (
            $tension
            >= self::EMOTIONAL_SIGNAL_THRESHOLD
        ) {
            return CharacterMood::Angry;
        }

        /*
         * A meaningful loss of trust or affection is
         * interpreted conservatively as sadness.
         */
        if (
            $trust
                <= -self::EMOTIONAL_SIGNAL_THRESHOLD
            || $affection
                <= -self::EMOTIONAL_SIGNAL_THRESHOLD
        ) {
            return CharacterMood::Sad;
        }

        /*
         * Relief after tension or meaningful positive
         * trust/affection movement is positive.
         */
        if (
            $tension
                <= -self::EMOTIONAL_SIGNAL_THRESHOLD
            || $trust
                >= self::EMOTIONAL_SIGNAL_THRESHOLD
            || $affection
                >= self::EMOTIONAL_SIGNAL_THRESHOLD
        ) {
            return CharacterMood::Happy;
        }

        /*
         * Familiarity by itself represents interest
         * and growing knowledge rather than affection.
         */
        if (
            $familiarity
            >= self::EMOTIONAL_SIGNAL_THRESHOLD
        ) {
            return CharacterMood::Curious;
        }

        return CharacterMood::Neutral;
    }

    public function apply(
        UserCharacterProfile $profile,
        ?RelationshipEvent $event
    ): UserCharacterProfile {
        $mood =
            $this->determineMood(
                $event
            );

        return $this->applyMood(
            $profile,
            $mood
        );
    }

    public function reset(
        UserCharacterProfile $profile
    ): UserCharacterProfile {
        return $this->applyMood(
            $profile,
            CharacterMood::Neutral
        );
    }

    private function applyMood(
        UserCharacterProfile $profile,
        CharacterMood $mood
    ): UserCharacterProfile {
        $character =
            $profile
                ->character()
                ->firstOrFail();

        $expression =
            $this
                ->expressionResolver
                ->resolve(
                    $character,
                    $mood
                );

        /*
         * If the desired mood has no dedicated visual
         * expression and the resolver fell back to the
         * neutral/default expression, keep the logical
         * mood neutral as well. The UI must never claim
         * an emotion that it cannot represent visually.
         */
        $resolvedMood =
            CharacterMood::tryFrom(
                $expression->name
            )
            ?? CharacterMood::Neutral;

        if (
            $profile->current_mood
                === $resolvedMood
            && $profile
                ->current_expression_id
                === $expression->id
        ) {
            return $profile;
        }

        $profile->forceFill([
            'current_mood' =>
                $resolvedMood,

            'current_expression_id' =>
                $expression->id,
        ])->save();

        return $profile
            ->fresh([
                'currentExpression',
                'character',
            ]);
    }
}
