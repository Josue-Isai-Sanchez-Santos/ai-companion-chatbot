<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class NarrativeResponse implements JsonSerializable
{
    /**
     * @param  list<NarrativeSegment>  $segments
     */
    public function __construct(
        public int $sceneId,
        public int $turnId,
        public int $speakerProfileId,
        public array $segments,
        public ProviderMetadata $metadata,
        public ?InferenceFailure $error = null,
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

        if (! array_is_list($segments)) {
            throw new InvalidArgumentException(
                'Narrative segments must form a list.'
            );
        }

        foreach ($segments as $segment) {
            if (! $segment instanceof NarrativeSegment) {
                throw new InvalidArgumentException(
                    'Narrative response contains an invalid segment.'
                );
            }
        }

        if (
            ($error === null && $segments === [])
            || ($error !== null && $segments !== [])
        ) {
            throw new InvalidArgumentException(
                'Narrative response must contain segments or one error, not both.'
            );
        }
    }

    public function assertMatches(
        NarrativeRequest $request
    ): void {
        if (
            $this->sceneId !== $request->sceneId
            || $this->turnId !== $request->turnId
            || $this->speakerProfileId !== $request->speakerProfileId
        ) {
            throw new InvalidArgumentException(
                'Narrative response does not match request IDs.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'turn_id' => $this->turnId,
            'speaker_profile_id' => $this->speakerProfileId,
            'segments' => $this->segments,
            'metadata' => $this->metadata,
            'error' => $this->error,
        ];
    }
}
