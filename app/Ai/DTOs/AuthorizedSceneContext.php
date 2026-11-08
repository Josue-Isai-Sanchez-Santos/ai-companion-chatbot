<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class AuthorizedSceneContext implements JsonSerializable
{
    /**
     * @param  list<string>  $facts
     */
    public function __construct(
        public int $sceneId,
        public string $audience,
        public ?int $viewerProfileId,
        public array $facts,
    ) {
        if (
            $sceneId < 1
            || ! in_array(
                $audience,
                ['director', 'participant'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid authorized context scope.'
            );
        }

        if (
            ($audience === 'director' && $viewerProfileId !== null)
            || (
                $audience === 'participant'
                && (
                    $viewerProfileId === null
                    || $viewerProfileId < 1
                )
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid authorized context viewer.'
            );
        }

        if (
            ! array_is_list($facts)
            || count($facts) > 64
        ) {
            throw new InvalidArgumentException(
                'Invalid authorized context facts.'
            );
        }

        foreach ($facts as $fact) {
            if (
                ! is_string($fact)
                || trim($fact) === ''
                || mb_strlen($fact) > 2000
            ) {
                throw new InvalidArgumentException(
                    'Invalid authorized context fact.'
                );
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'scene_id' => $this->sceneId,
            'audience' => $this->audience,
            'viewer_profile_id' => $this->viewerProfileId,
            'facts' => $this->facts,
        ];
    }
}
