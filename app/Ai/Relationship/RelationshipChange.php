<?php

namespace App\Ai\Relationship;

final readonly class RelationshipChange
{
    public function __construct(
        public bool $significant,
        public string $eventSummary,
        public int $trustDelta,
        public int $affectionDelta,
        public int $familiarityDelta,
        public int $tensionDelta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(
        array $data
    ): self {
        return new self(
            significant:
                (bool) (
                    $data['significant']
                    ?? false
                ),

            eventSummary:
                trim(
                    (string) (
                        $data['event_summary']
                        ?? ''
                    )
                ),

            trustDelta:
                (int) (
                    $data['trust_delta']
                    ?? 0
                ),

            affectionDelta:
                (int) (
                    $data['affection_delta']
                    ?? 0
                ),

            familiarityDelta:
                (int) (
                    $data['familiarity_delta']
                    ?? 0
                ),

            tensionDelta:
                (int) (
                    $data['tension_delta']
                    ?? 0
                ),
        );
    }

    /**
     * @return array{
     *     trust: int,
     *     affection: int,
     *     familiarity: int,
     *     tension: int
     * }
     */
    public function deltas(): array
    {
        return [
            'trust' =>
                $this->trustDelta,

            'affection' =>
                $this->affectionDelta,

            'familiarity' =>
                $this->familiarityDelta,

            'tension' =>
                $this->tensionDelta,
        ];
    }
}
