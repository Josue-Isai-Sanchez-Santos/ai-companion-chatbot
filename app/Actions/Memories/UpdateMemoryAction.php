<?php

namespace App\Actions\Memories;

use App\Enums\MemoryType;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateMemoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        User $user,
        Memory $memory,
        array $attributes
    ): Memory {
        Gate::forUser($user)->authorize(
            'update',
            $memory
        );

        $attributes = Arr::only(
            $attributes,
            [
                'type',
                'content',
                'importance',
                'confidence',
                'embedding',
                'expires_at',
            ]
        );

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'memory' =>
                    'No se proporcionaron cambios para la memoria.',
            ]);
        }

        $embeddingWasProvided =
            array_key_exists(
                'embedding',
                $attributes
            );

        if (
            isset($attributes['type'])
            && $attributes['type']
                instanceof MemoryType
        ) {
            $attributes['type'] =
                $attributes['type']->value;
        }

        if (
            isset($attributes['content'])
            && is_string(
                $attributes['content']
            )
        ) {
            $attributes['content'] = trim(
                $attributes['content']
            );
        }

        $validated = Validator::make(
            $attributes,
            [
                'type' => [
                    'sometimes',

                    Rule::enum(
                        MemoryType::class
                    ),
                ],

                'content' => [
                    'sometimes',
                    'required',
                    'string',
                ],

                'importance' => [
                    'sometimes',
                    'numeric',
                    'min:0',
                    'max:1',
                ],

                'confidence' => [
                    'sometimes',
                    'numeric',
                    'min:0',
                    'max:1',
                ],

                'embedding' => [
                    'sometimes',
                    'nullable',
                    'array',

                    'size:'
                        .Memory::EMBEDDING_DIMENSIONS,
                ],

                'embedding.*' => [
                    'numeric',
                ],

                'expires_at' => [
                    'sometimes',
                    'nullable',
                    'date',
                    'after:now',
                ],
            ]
        )->validate();

        $resultingType =
            array_key_exists(
                'type',
                $validated
            )
                ? MemoryType::from(
                    $validated['type']
                )
                : $memory->type;

        $resultingExpiresAt =
            array_key_exists(
                'expires_at',
                $validated
            )
                ? $validated['expires_at']
                : $memory->expires_at;

        if (
            $resultingType
                === MemoryType::TemporaryContext
            && $resultingExpiresAt
                === null
        ) {
            throw ValidationException::withMessages([
                'expires_at' =>
                    'Una memoria temporal necesita una fecha de expiración.',
            ]);
        }

        if (
            array_key_exists(
                'content',
                $validated
            )
            && $validated['content']
                !== $memory->content
            && ! $embeddingWasProvided
        ) {
            $validated['embedding'] = null;
        }

        $memory->fill(
            $validated
        );

        $memory->save();

        return $memory->fresh();
    }
}
