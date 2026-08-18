<?php

namespace Tests\Fakes;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;
use Throwable;

final class FakeChatGateway implements ChatGateway
{
    /**
     * @var list<ChatContext>
     */
    public array $contexts = [];

    private GeneratedReply $reply;

    private ?Throwable $failure = null;

    /**
     * @var list<string>
     */
    private array $streamDeltas = [];

    private ?Throwable $streamFailure = null;

    public function __construct()
    {
        $this->reply = new GeneratedReply(
            content: 'Respuesta del FakeChatGateway.',

            metadata: [
                'fake' => true,
            ],
        );
    }

    public function replyWith(
        GeneratedReply $reply
    ): void {
        $this->reply = $reply;
    }

    public function failWith(
        Throwable $failure
    ): void {
        $this->failure = $failure;
    }

    /**
     * @param  list<string>  $deltas
     */
    public function streamWith(
        array $deltas
    ): void {
        $this->streamDeltas = $deltas;
    }

    /**
     * @param  list<string>  $beforeFailure
     */
    public function failStreamWith(
        Throwable $failure,
        array $beforeFailure = []
    ): void {
        $this->streamDeltas = $beforeFailure;
        $this->streamFailure = $failure;
    }

    public function resetFailures(): void
    {
        $this->failure = null;
        $this->streamFailure = null;
    }

    public function generate(
        ChatContext $context
    ): GeneratedReply {
        $this->contexts[] = $context;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->reply;
    }

    public function stream(
        ChatContext $context,
        callable $onDelta
    ): GeneratedReply {
        $this->contexts[] = $context;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $deltas = $this->streamDeltas;

        if ($deltas === []) {
            $deltas = [
                $this->reply->content,
            ];
        }

        foreach ($deltas as $delta) {
            $onDelta($delta);
        }

        if ($this->streamFailure !== null) {
            throw $this->streamFailure;
        }

        return $this->reply;
    }
}
