<?php

namespace App\Ai\Safety;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class ChatSafetyPolicy
{
    /**
     * These markers belong to the internal system
     * prompt and should never appear verbatim in a
     * normal character response.
     *
     * @var list<string>
     */
    private const INTERNAL_PROMPT_MARKERS = [
        '## 01_GLOBAL_RULES',
        '## 03_CHARACTER_RULES',
        '## 14_RESPONSE_PROTOCOL',
    ];

    public function assertGenerationAllowed(
        User $user
    ): void {
        $maxAttempts = max(
            1,
            (int) config(
                'chatbot.rate_limits.generation_per_minute',
                20
            )
        );

        $key = sprintf(
            'chat-generation:user:%d',
            $user->id
        );

        if (
            RateLimiter::tooManyAttempts(
                $key,
                $maxAttempts
            )
        ) {
            $retryAfter =
                RateLimiter::availableIn(
                    $key
                );

            throw ValidationException::withMessages([
                'message' =>
                    "Demasiadas solicitudes. Intenta de nuevo en {$retryAfter} segundos.",
            ]);
        }

        RateLimiter::hit(
            $key,
            60
        );
    }

    public function containsInternalPromptLeak(
        string $content
    ): bool {
        foreach (
            self::INTERNAL_PROMPT_MARKERS
            as $marker
        ) {
            if (
                stripos(
                    $content,
                    $marker
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }
}
