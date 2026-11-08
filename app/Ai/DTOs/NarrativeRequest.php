<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class NarrativeRequest implements JsonSerializable
{
    /**
     * @param  list<SceneParticipant>  $participants
     * @param  list<int>  $recipientProfileIds
     */
    public function __construct(
        public int $sceneId,
        public int $turnId,
        public int $speakerProfileId,
        public string $userMessage,
        public array $participants,
        public array $recipientProfileIds,
        public AuthorizedSceneContext $context,
        public int $remainingInterventions,
    ) {
        if (
            $sceneId < 1
            || $turnId < 1
            || $speakerProfileId < 1
        ) {
            throw new InvalidArgumentException(
                'Scene, turn and speaker IDs must be positive.'
            );
        }

        if (
            trim($userMessage) === ''
            || mb_strlen($userMessage) > 4000
        ) {
            throw new InvalidArgumentException(
                'Invalid user message.'
            );
        }

        if (
            ! array_is_list($participants)
            || $participants === []
            || count($participants) > 100
        ) {
            throw new InvalidArgumentException(
                'Invalid participant list.'
            );
        }

        $ids = [];
        $speakerPresent = false;

        foreach ($participants as $participant) {
            if (
                ! $participant instanceof SceneParticipant
                || in_array($participant->profileId, $ids, true)
            ) {
                throw new InvalidArgumentException(
                    'Invalid or repeated participant.'
                );
            }

            $ids[] = $participant->profileId;

            if ($participant->profileId === $speakerProfileId) {
                $speakerPresent = $participant->present;
            }
        }

        if (! $speakerPresent) {
            throw new InvalidArgumentException(
                'Narrative speaker must be present in the scene.'
            );
        }

        if (! array_is_list($recipientProfileIds)) {
            throw new InvalidArgumentException(
                'Invalid narrative recipient list.'
            );
        }

        $seen = [];

        foreach ($recipientProfileIds as $recipientId) {
            if (
                ! is_int($recipientId)
                || ! in_array($recipientId, $ids, true)
                || in_array($recipientId, $seen, true)
            ) {
                throw new InvalidArgumentException(
                    'Invalid or repeated narrative recipient.'
                );
            }

            $seen[] = $recipientId;
        }

        if (
            $context->sceneId !== $sceneId
            || $context->audience !== 'participant'
            || $context->viewerProfileId !== $speakerProfileId
        ) {
            throw new InvalidArgumentException(
                'Narrative context is not scoped to this speaker and scene.'
            );
        }

        if (
            $remainingInterventions < 1
            || $remainingInterventions > 6
        ) {
            throw new InvalidArgumentException(
                'Narrative generation requires an available intervention.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'turn_id' => $this->turnId,
            'speaker_profile_id' => $this->speakerProfileId,
            'user_message' => $this->userMessage,
            'participants' => $this->participants,
            'recipient_profile_ids' => $this->recipientProfileIds,
            'context' => $this->context,
            'remaining_interventions' => $this->remainingInterventions,
        ];
    }
}
