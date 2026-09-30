<?php

namespace App\Ai\State;

use App\Enums\CharacterMood;
use App\Models\Character;
use App\Models\CharacterExpression;
use LogicException;

final class ExpressionResolver
{
    public function resolve(
        Character $character,
        CharacterMood $mood
    ): CharacterExpression {
        /*
         * Expressions are always resolved inside
         * this character. An expression belonging
         * to another character can never leak here.
         */
        $expression = $character
            ->expressions()
            ->where(
                'name',
                $mood->value
            )
            ->first();

        if ($expression !== null) {
            return $expression;
        }

        /*
         * If a character does not implement a
         * particular mood visually, fall back to its
         * declared default expression.
         */
        $default = $character
            ->defaultExpression()
            ->first();

        if ($default !== null) {
            return $default;
        }

        /*
         * Be extra defensive: an explicitly named
         * neutral expression is also valid fallback.
         */
        $neutral = $character
            ->expressions()
            ->where(
                'name',
                CharacterMood::Neutral->value
            )
            ->first();

        if ($neutral !== null) {
            return $neutral;
        }

        throw new LogicException(
            'Character has no valid neutral or default expression.'
        );
    }
}
