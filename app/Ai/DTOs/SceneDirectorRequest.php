<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class SceneDirectorRequest implements JsonSerializable
{
    /**
     * @param  list<SceneParticipant>  $participants
     * @param  list<int>  $explicitRecipientProfileIds
     */
    public function __construct(
        public int $sceneId,
        public int $turnId,
        public string $userMessage,
        public array $participants,
        public array $explicitRecipientProfileIds,
        public AuthorizedSceneContext $context,
        public int $maxInterventions,
        public int $remainingInterventions,
    ) {
        if ($sceneId < 1 || $turnId < 1) {
            throw new InvalidArgumentException(
                'Scene and turn IDs must be positive.'
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
        }

        if (! array_is_list($explicitRecipientProfileIds)) {
            throw new InvalidArgumentException(
                'Invalid explicit recipient list.'
            );
        }

        $seen = [];

        foreach ($explicitRecipientProfileIds as $recipientId) {
            if (
                ! is_int($recipientId)
                || ! in_array($recipientId, $ids, true)
                || in_array($recipientId, $seen, true)
            ) {
                throw new InvalidArgumentException(
                    'Invalid or repeated explicit recipient.'
                );
            }

            $seen[] = $recipientId;
        }

        if (
            $context->sceneId !== $sceneId
            || $context->audience !== 'director'
        ) {
            throw new InvalidArgumentException(
                'Director context does not match this scene and audience.'
            );
        }

        if (
            $maxInterventions < 1
            || $maxInterventions > 6
            || $remainingInterventions < 0
            || $remainingInterventions > $maxInterventions
        ) {
            throw new InvalidArgumentException(
                'Invalid scene intervention budget.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'turn_id' => $this->turnId,
            'user_message' => $this->userMessage,
            'participants' => $this->participants,
            'explicit_recipient_profile_ids' => $this->explicitRecipientProfileIds,
            'context' => $this->context,
            'max_interventions' => $this->maxInterventions,
            'remaining_interventions' => $this->remainingInterventions,
        ];
    }
}
