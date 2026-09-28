<?php

namespace App\Livewire;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Memories\CreateMemoryAction;
use App\Actions\Memories\DeleteMemoryAction;
use App\Actions\Memories\UpdateMemoryAction;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Exceptions\AiGatewayException;
use App\Enums\MemoryType;
use App\Models\Character;
use App\Models\Memory;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Memorias')]
final class MemoryManager extends Component
{
    #[Locked]
    public int $profileId;

    public string $typeFilter = 'all';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingMemoryId = null;

    public string $formType =
        MemoryType::UserFact->value;

    public string $formContent = '';

    public string $formImportance = '0.70';

    public string $formConfidence = '1.00';

    public string $formExpiresAt = '';

    public ?string $statusMessage = null;

    public function mount(
        CreateUserCharacterProfileAction $createProfile
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $character = Character::query()
            ->active()
            ->sole();

        $profile = $createProfile->execute(
            $user,
            $character
        );

        $this->profileId =
            $profile->id;
    }

    public function startCreating(): void
    {
        $this->resetEditor();

        $this->showForm = true;
    }

    public function startEditing(
        int $memoryId
    ): void {
        $memory = $this
            ->memoryForCurrentProfile(
                $memoryId,
                'update'
            );

        $this->editingMemoryId =
            $memory->id;

        $this->showForm = true;

        $this->formType =
            $memory->type->value;

        $this->formContent =
            $memory->content;

        $this->formImportance =
            number_format(
                $memory->importance,
                2,
                '.',
                ''
            );

        $this->formConfidence =
            number_format(
                $memory->confidence,
                2,
                '.',
                ''
            );

        $this->formExpiresAt =
            $memory->expires_at
                ? $memory
                    ->expires_at
                    ->format('Y-m-d\TH:i')
                : '';

        $this->statusMessage = null;

        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->resetEditor();
    }

    public function saveMemory(
        CreateMemoryAction $createMemory,
        UpdateMemoryAction $updateMemory,
        EmbeddingGateway $embeddingGateway
    ): void {
        $validated =
            $this->validate();

        /** @var User $user */
        $user = auth()->user();

        $profile =
            $this->currentProfile();

        $type = MemoryType::from(
            $validated['formType']
        );

        $content = trim(
            $validated['formContent']
        );

        $expiresAt =
            $type === MemoryType::TemporaryContext
                ? (
                    $validated['formExpiresAt']
                    ?: null
                )
                : null;

        $attributes = [
            'type' => $type,

            'content' =>
                $content,

            'importance' =>
                (float) $validated[
                    'formImportance'
                ],

            'confidence' =>
                (float) $validated[
                    'formConfidence'
                ],

            'expires_at' =>
                $expiresAt,
        ];

        if (
            $this->editingMemoryId
            === null
        ) {
            $embedding =
                $this->generateEmbedding(
                    $content,
                    $embeddingGateway
                );

            if ($embedding === null) {
                return;
            }

            $createMemory->execute(
                $user,
                $profile,
                [
                    ...$attributes,

                    'source_message_id' =>
                        null,

                    'embedding' =>
                        $embedding,
                ]
            );

            $this->statusMessage =
                'Memoria creada correctamente.';

            $this->resetEditor(
                keepStatus: true
            );

            return;
        }

        $memory = $this
            ->memoryForCurrentProfile(
                $this->editingMemoryId,
                'update'
            );

        $needsEmbedding =
            $memory->content !== $content
            || $memory->embedding === null;

        if ($needsEmbedding) {
            $embedding =
                $this->generateEmbedding(
                    $content,
                    $embeddingGateway
                );

            if ($embedding === null) {
                return;
            }

            $attributes['embedding'] =
                $embedding;
        }

        $updateMemory->execute(
            $user,
            $memory,
            $attributes
        );

        $this->statusMessage =
            'Memoria actualizada correctamente.';

        $this->resetEditor(
            keepStatus: true
        );
    }

    public function deleteMemory(
        int $memoryId,
        DeleteMemoryAction $deleteMemory
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $memory = $this
            ->memoryForCurrentProfile(
                $memoryId,
                'delete'
            );

        $deleteMemory->execute(
            $user,
            $memory
        );

        if (
            $this->editingMemoryId
            === $memoryId
        ) {
            $this->resetEditor();
        }

        $this->statusMessage =
            'Memoria eliminada correctamente.';
    }

    public function render(): View
    {
        $profile =
            $this->currentProfile();

        Gate::authorize(
            'viewAny',
            [
                Memory::class,
                $profile,
            ]
        );

        $query = $profile
            ->memories()
            ->with([
                'sourceMessage.conversation',
            ]);

        if (
            $this->typeFilter !== 'all'
            && MemoryType::tryFrom(
                $this->typeFilter
            ) !== null
        ) {
            $query->where(
                'type',
                $this->typeFilter
            );
        }

        $memories = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return view(
            'livewire.memory-manager',
            [
                'profile' =>
                    $profile,

                'character' =>
                    $profile->character,

                'memories' =>
                    $memories,

                'typeLabels' =>
                    $this->typeLabels(),
            ]
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'formType' => [
                'required',

                Rule::enum(
                    MemoryType::class
                ),
            ],

            'formContent' => [
                'required',
                'string',
                'max:1000',
            ],

            'formImportance' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'formConfidence' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'formExpiresAt' => [
                Rule::requiredIf(
                    $this->formType
                        === MemoryType::TemporaryContext->value
                ),

                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    private function currentProfile(): UserCharacterProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return UserCharacterProfile::query()
            ->whereKey(
                $this->profileId
            )
            ->where(
                'user_id',
                $user->id
            )
            ->with('character')
            ->firstOrFail();
    }

    private function memoryForCurrentProfile(
        int $memoryId,
        string $ability
    ): Memory {
        $profile =
            $this->currentProfile();

        $memory = $profile
            ->memories()
            ->with([
                'sourceMessage.conversation',
            ])
            ->findOrFail(
                $memoryId
            );

        Gate::authorize(
            $ability,
            $memory
        );

        return $memory;
    }

    /**
     * @return list<float>|null
     */
    private function generateEmbedding(
        string $content,
        EmbeddingGateway $embeddingGateway
    ): ?array {
        try {
            $embedding =
                $embeddingGateway
                    ->embed(
                        $content
                    );
        } catch (
            AiGatewayException $exception
        ) {
            report(
                $exception
            );

            $this->addError(
                'formContent',
                'No fue posible generar el embedding de la memoria.'
            );

            return null;
        }

        if (
            count($embedding)
            !== Memory::EMBEDDING_DIMENSIONS
        ) {
            $this->addError(
                'formContent',
                'El proveedor devolvió un embedding inválido.'
            );

            return null;
        }

        return $embedding;
    }

    /**
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        return [
            MemoryType::UserFact->value =>
                'Hecho del usuario',

            MemoryType::UserPreference->value =>
                'Preferencia del usuario',

            MemoryType::CharacterFact->value =>
                'Hecho del personaje',

            MemoryType::SharedEvent->value =>
                'Evento compartido',

            MemoryType::Promise->value =>
                'Promesa',

            MemoryType::RelationshipEvent->value =>
                'Evento de relación',

            MemoryType::WorldFact->value =>
                'Hecho del mundo',

            MemoryType::TemporaryContext->value =>
                'Contexto temporal',
        ];
    }

    private function resetEditor(
        bool $keepStatus = false
    ): void {
        $this->showForm = false;

        $this->editingMemoryId =
            null;

        $this->formType =
            MemoryType::UserFact->value;

        $this->formContent = '';

        $this->formImportance =
            '0.70';

        $this->formConfidence =
            '1.00';

        $this->formExpiresAt = '';

        if (! $keepStatus) {
            $this->statusMessage =
                null;
        }

        $this->resetValidation();
    }
}
