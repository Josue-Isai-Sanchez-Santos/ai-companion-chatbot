<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class SceneDirectorResponse implements JsonSerializable
{
    public function __construct(
        public int $sceneId,
        public int $turnId,
        public ?int $nextSpeakerProfileId,
        public ?string $reason,
        public bool $continueScene,
        public bool $requiresUserInput,
        public ProviderMetadata $metadata,
        public ?InferenceFailure $error = null,
    ) {
        if ($sceneId < 1 || $turnId < 1) {
            throw new InvalidArgumentException(
                'Scene and turn IDs must be positive.'
            );
        }

        if ($error !== null) {
            if (
                $nextSpeakerProfileId !== null
                || $reason !== null
                || $continueScene
                || $requiresUserInput
            ) {
                throw new InvalidArgumentException(
                    'Failed director response cannot contain a decision.'
                );
            }

            return;
        }

        if ($nextSpeakerProfileId !== null) {
            if (
                $nextSpeakerProfileId < 1
                || ! in_array(
                    $reason,
                    ['explicit_address', 'scene_flow'],
                    true
                )
                || ! $continueScene
                || $requiresUserInput
            ) {
                throw new InvalidArgumentException(
                    'Invalid director speaker decision.'
                );
            }

            return;
        }

        if (
            ! in_array(
                $reason,
                ['waiting_for_user', 'no_available_speaker'],
                true
            )
            || $continueScene
            || ! $requiresUserInput
        ) {
            throw new InvalidArgumentException(
                'Invalid director pause decision.'
            );
        }
    }

    /**
     * Validate the response against its original request.
     *
     * Full addressee-priority logic belongs to
     * the future Scene Director.
     */
    public function assertMatches(
        SceneDirectorRequest $request
    ): void {
        if (
            $request->sceneId !== $this->sceneId
            || $request->turnId !== $this->turnId
        ) {
            throw new InvalidArgumentException(
                'Director response does not match request IDs.'
            );
        }

        if ($this->nextSpeakerProfileId === null) {
            return;
        }

        if ($request->remainingInterventions < 1) {
            throw new InvalidArgumentException(
                'Scene intervention budget is exhausted.'
            );
        }

        foreach ($request->participants as $participant) {
            if (
                $participant->profileId === $this->nextSpeakerProfileId
                && $participant->present
            ) {
                return;
            }
        }

        throw new InvalidArgumentException(
            'Director selected an absent or unknown speaker.'
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'turn_id' => $this->turnId,
            'next_speaker_profile_id' => $this->nextSpeakerProfileId,
            'reason' => $this->reason,
            'continue_scene' => $this->continueScene,
            'requires_user_input' => $this->requiresUserInput,
            'metadata' => $this->metadata,
            'error' => $this->error,
        ];
    }
}
