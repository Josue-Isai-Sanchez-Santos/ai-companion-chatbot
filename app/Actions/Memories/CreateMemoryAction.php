<?php

namespace App\Actions\Memories;

use App\Enums\MemoryType;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateMemoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        User $user,
        UserCharacterProfile $profile,
        array $attributes
    ): Memory {
        Gate::forUser($user)->authorize(
            'create',
            [
                Memory::class,
                $profile,
            ]
        );

        $attributes = $this->prepare(
            $attributes
        );

        $validated = Validator::make(
            $attributes,
            $this->rules()
        )->validate();

        $this->ensureTemporaryMemoryExpires(
            $validated
        );

        $this->ensureSourceMessageBelongsToProfile(
            $profile,
            $validated['source_message_id']
        );

        return $profile
            ->memories()
            ->create([
                'source_message_id' =>
                    $validated['source_message_id'],

                'type' =>
                    $validated['type'],

                'content' =>
                    $validated['content'],

                'importance' =>
                    $validated['importance'],

                'confidence' =>
                    $validated['confidence'],

                'embedding' =>
                    $validated['embedding'],

                'access_count' => 0,

                'last_accessed_at' => null,

                'expires_at' =>
                    $validated['expires_at'],
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepare(
        array $attributes
    ): array {
        $attributes = Arr::only(
            $attributes,
            [
                'source_message_id',
                'type',
                'content',
                'importance',
                'confidence',
                'embedding',
                'expires_at',
            ]
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

        $attributes += [
            'source_message_id' => null,

            'importance' => 0.5,

            'confidence' => 1.0,

            'embedding' => null,

            'expires_at' => null,
        ];

        return $attributes;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'source_message_id' => [
                'nullable',
                'integer',
                'exists:messages,id',
            ],

            'type' => [
                'required',

                Rule::enum(
                    MemoryType::class
                ),
            ],

            'content' => [
                'required',
                'string',
            ],

            'importance' => [
                'required',
                'numeric',
                'min:0',
                'max:1',
            ],

            'confidence' => [
                'required',
                'numeric',
                'min:0',
                'max:1',
            ],

            'embedding' => [
                'nullable',
                'array',

                'size:'
                    .Memory::EMBEDDING_DIMENSIONS,
            ],

            'embedding.*' => [
                'numeric',
            ],

            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureTemporaryMemoryExpires(
        array $validated
    ): void {
        if (
            $validated['type']
                === MemoryType::TemporaryContext->value
            && $validated['expires_at']
                === null
        ) {
            throw ValidationException::withMessages([
                'expires_at' =>
                    'Una memoria temporal necesita una fecha de expiración.',
            ]);
        }
    }

    private function ensureSourceMessageBelongsToProfile(
        UserCharacterProfile $profile,
        ?int $sourceMessageId
    ): void {
        if ($sourceMessageId === null) {
            return;
        }

        $belongsToProfile = Message::query()
            ->whereKey(
                $sourceMessageId
            )
            ->whereHas(
                'conversation',

                function (
                    $query
                ) use (
                    $profile
                ): void {
                    $query->where(
                        'user_character_profile_id',
                        $profile->id
                    );
                }
            )
            ->exists();

        if (! $belongsToProfile) {
            throw ValidationException::withMessages([
                'source_message_id' =>
                    'El mensaje de origen no pertenece a este perfil.',
            ]);
        }
    }
}
