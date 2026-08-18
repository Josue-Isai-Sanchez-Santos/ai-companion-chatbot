<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Exceptions\AiGatewayException;
use App\Ai\Exceptions\AiProviderException;
use Laravel\Ai\Messages\Message as LaravelAiMessage;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

use function Laravel\Ai\agent;

final class LaravelAiGateway implements ChatGateway
{
    public function generate(
        ChatContext $context
    ): GeneratedReply {
        $request = $this->requestData(
            $context
        );

        try {
            $response = agent(
                instructions: $context->systemPrompt,
                messages: $request['history'],
                tools: [],
            )->prompt(
                $request['prompt'],
                provider: $request['provider'],
                model: $request['model'],
                timeout: $request['timeout'],
            );
        } catch (Throwable $exception) {
            throw new AiProviderException(
                'AI provider request failed.',
                0,
                $exception
            );
        }

        $content = trim(
            $response->text
        );

        if ($content === '') {
            throw new AiProviderException(
                'AI provider returned an empty response.'
            );
        }

        return new GeneratedReply(
            content: $content,

            metadata: [
                'driver' => 'laravel-ai',
                'provider' => $request['provider'],
                'model' => $request['model'],
                'invocation_id' => $response->invocationId,
                'usage' => $response->usage->toArray(),
            ],

            tokenCount: $response->usage->completionTokens,

            status: 'completed',
        );
    }

    public function stream(
        ChatContext $context,
        callable $onDelta
    ): GeneratedReply {
        $request = $this->requestData(
            $context
        );

        try {
            $stream = agent(
                instructions: $context->systemPrompt,
                messages: $request['history'],
                tools: [],
            )->stream(
                $request['prompt'],
                provider: $request['provider'],
                model: $request['model'],
                timeout: $request['timeout'],
            );

            foreach ($stream as $event) {
                if (
                    $event instanceof TextDelta
                    && $event->delta !== ''
                ) {
                    $onDelta(
                        $event->delta
                    );
                }
            }
        } catch (AiGatewayException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AiProviderException(
                'AI provider streaming request failed.',
                0,
                $exception
            );
        }

        $content = trim(
            (string) $stream->text
        );

        if ($content === '') {
            throw new AiProviderException(
                'AI provider returned an empty streamed response.'
            );
        }

        $usage = $stream->usage;

        return new GeneratedReply(
            content: $content,

            metadata: [
                'driver' => 'laravel-ai',
                'provider' => $request['provider'],
                'model' => $request['model'],
                'invocation_id' => $stream->invocationId,

                'usage' => $usage?->toArray()
                    ?? [],
            ],

            tokenCount: $usage?->completionTokens,

            status: 'completed',
        );
    }

    /**
     * @return array{
     *     provider: string,
     *     model: string,
     *     timeout: int,
     *     history: list<LaravelAiMessage>,
     *     prompt: string
     * }
     */
    private function requestData(
        ChatContext $context
    ): array {
        $provider = trim(
            (string) config(
                'ai.chat.provider',
                'openai'
            )
        );

        $model = trim(
            (string) config(
                'ai.chat.model',
                'gpt-5.4-mini'
            )
        );

        $timeout = max(
            1,
            (int) config(
                'ai.chat.timeout',
                30
            )
        );

        if (
            $provider === ''
            || $model === ''
        ) {
            throw new AiGatewayException(
                'AI provider configuration is incomplete.'
            );
        }

        $messages = $context->messages;

        $currentMessage = array_pop(
            $messages
        );

        if (
            $currentMessage === null
            || $currentMessage['role'] !== 'user'
        ) {
            throw new AiGatewayException(
                'The current AI prompt must end with a user message.'
            );
        }

        $history = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                continue;
            }

            if (
                ! in_array(
                    $message['role'],
                    [
                        'user',
                        'assistant',
                    ],
                    true
                )
            ) {
                throw new AiGatewayException(
                    'Unsupported message role for Laravel AI.'
                );
            }

            $history[] = new LaravelAiMessage(
                $message['role'],
                $message['content']
            );
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'timeout' => $timeout,
            'history' => $history,
            'prompt' => $currentMessage['content'],
        ];
    }
}
