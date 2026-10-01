<?php

namespace App\Ai\Safety;

use App\Ai\DTOs\GeneratedReply;
use App\Ai\Exceptions\AiGatewayException;

final class OutputValidator
{
    public function __construct(
        private readonly ChatSafetyPolicy $safetyPolicy
    ) {}

    public function validate(
        GeneratedReply $reply
    ): GeneratedReply {
        $content =
            trim(
                $reply->content
            );

        $this->assertSafeText(
            $content,
            allowEmpty: false
        );

        return new GeneratedReply(
            content:
                $content,

            metadata:
                $reply->metadata,

            tokenCount:
                $reply->tokenCount,

            status:
                $reply->status,
        );
    }

    public function assertSafePartial(
        string $content
    ): void {
        $this->assertSafeText(
            $content,
            allowEmpty: true
        );
    }

    private function assertSafeText(
        string $content,
        bool $allowEmpty
    ): void {
        if (
            ! $allowEmpty
            && trim(
                $content
            ) === ''
        ) {
            throw new AiGatewayException(
                'AI response failed output validation.'
            );
        }

        if (
            ! mb_check_encoding(
                $content,
                'UTF-8'
            )
        ) {
            throw new AiGatewayException(
                'AI response failed output validation.'
            );
        }

        if (
            preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $content
            ) === 1
        ) {
            throw new AiGatewayException(
                'AI response failed output validation.'
            );
        }

        $maximumLength = max(
            1,
            (int) config(
                'chatbot.response_max_length',
                12000
            )
        );

        if (
            mb_strlen(
                $content
            ) > $maximumLength
        ) {
            throw new AiGatewayException(
                'AI response exceeded the configured output limit.'
            );
        }

        if (
            $this
                ->safetyPolicy
                ->containsInternalPromptLeak(
                    $content
                )
        ) {
            throw new AiGatewayException(
                'AI response failed prompt confidentiality validation.'
            );
        }
    }
}
