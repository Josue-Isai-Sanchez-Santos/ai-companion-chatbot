<?php

namespace App\Jobs;

use App\Ai\Memory\MemoryExtractor;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExtractConversationMemories implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $throughMessageId,
    ) {
        $this->onQueue(
            (string) config(
                'memory.extraction.queue',
                'memory'
            )
        );

        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return sprintf(
            '%d:%d',
            $this->conversationId,
            $this->throughMessageId
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
        MemoryExtractor $extractor
    ): void {
        $conversation =
            Conversation::query()
                ->find(
                    $this->conversationId
                );

        if ($conversation === null) {
            return;
        }

        $extractor->extract(
            $conversation,
            $this->throughMessageId
        );
    }
}
