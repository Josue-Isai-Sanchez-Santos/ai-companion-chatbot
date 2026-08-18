<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Exceptions\AiGatewayException;
use Laravel\Ai\Messages\Message as LaravelAiMessage;
use Throwable;

use function Laravel\Ai\agent;

final class LaravelAiGateway implements ChatGateway
{
    public function generate(
        ChatContext $context
    ): GeneratedReply {
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

        if ($provider === '' || $model === '') {
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

        try {
            $response = agent(
                instructions: $context->systemPrompt,
                messages: $history,
                tools: [],
            )->prompt(
                $currentMessage['content'],
                provider: $provider,
                model: $model,
                timeout: $timeout,
            );
        } catch (Throwable $exception) {
            throw new AiGatewayException(
                'AI provider request failed.',
                0,
                $exception
            );
        }

        $content = trim(
            $response->text
        );

        if ($content === '') {
            throw new AiGatewayException(
                'AI provider returned an empty response.'
            );
        }

        return new GeneratedReply(
            content: $content,

            metadata: [
                'driver' => 'laravel-ai',
                'provider' => $provider,
                'model' => $model,
                'invocation_id' => $response->invocationId,
                'usage' => $response->usage->toArray(),
            ],

            tokenCount: $response->usage->completionTokens,

            status: 'completed',
        );
    }
}
