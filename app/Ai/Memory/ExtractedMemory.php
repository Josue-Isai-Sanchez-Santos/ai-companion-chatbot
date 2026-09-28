<?php

namespace App\Ai\Memory;

use App\Enums\MemoryType;

final readonly class ExtractedMemory
{
    public const MAX_CONTENT_LENGTH = 1000;

    public function __construct(
        public MemoryType $type,
        public string $content,
        public float $importance,
        public float $confidence,
        public ?int $sourceMessageId,
    ) {}

    /**
     * @return list<MemoryType>
     */
    public static function allowedTypes(): array
    {
        return [
            MemoryType::UserFact,
            MemoryType::UserPreference,
            MemoryType::CharacterFact,
            MemoryType::SharedEvent,
            MemoryType::Promise,
            MemoryType::RelationshipEvent,
            MemoryType::WorldFact,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedTypeValues(): array
    {
        return array_map(
            static fn (
                MemoryType $type
            ): string =>
                $type->value,

            self::allowedTypes()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(
        array $data
    ): ?self {
        $rawType = $data['type']
            ?? null;

        if (! is_string($rawType)) {
            return null;
        }

        $type = MemoryType::tryFrom(
            $rawType
        );

        if (
            $type === null
            || ! in_array(
                $type,
                self::allowedTypes(),
                true
            )
        ) {
            return null;
        }

        $content = $data['content']
            ?? null;

        if (! is_string($content)) {
            return null;
        }

        $content = trim(
            $content
        );

        if (
            $content === ''
            || mb_strlen($content)
                > self::MAX_CONTENT_LENGTH
        ) {
            return null;
        }

        $importance =
            $data['importance']
            ?? null;

        $confidence =
            $data['confidence']
            ?? null;

        if (
            ! is_numeric($importance)
            || ! is_numeric($confidence)
        ) {
            return null;
        }

        $importance = (float)
            $importance;

        $confidence = (float)
            $confidence;

        if (
            $importance < 0.0
            || $importance > 1.0
            || $confidence < 0.0
            || $confidence > 1.0
        ) {
            return null;
        }

        $sourceMessageId =
            $data['source_message_id']
            ?? null;

        if (
            is_string($sourceMessageId)
            && ctype_digit(
                $sourceMessageId
            )
        ) {
            $sourceMessageId =
                (int) $sourceMessageId;
        }

        if (
            ! is_int($sourceMessageId)
            || $sourceMessageId <= 0
        ) {
            $sourceMessageId = null;
        }

        return new self(
            type: $type,
            content: $content,
            importance: $importance,
            confidence: $confidence,
            sourceMessageId:
                $sourceMessageId,
        );
    }
}
