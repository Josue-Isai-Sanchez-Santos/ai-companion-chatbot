<?php

namespace Tests\Feature\Memories;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Memories\CreateMemoryAction;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Memory\MemoryRetriever;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Livewire\MemoryManager;
use App\Models\Character;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeEmbeddingGateway;
use Tests\TestCase;

class MemoryManagerTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(
            CharacterSeeder::class
        );

        $this->character =
            Character::query()
                ->where(
                    'slug',
                    'default-companion'
                )
                ->firstOrFail();
    }

    private function profileFor(
        User $user
    ): UserCharacterProfile {
        return app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $this->character
        );
    }

    /**
     * @return list<float>
     */
    private function axis(
        int $index
    ): array {
        $embedding = array_fill(
            0,
            Memory::EMBEDDING_DIMENSIONS,
            0.0
        );

        $embedding[$index] = 1.0;

        return $embedding;
    }

    private function fakeEmbedding(
        array $embedding
    ): FakeEmbeddingGateway {
        $fake =
            new FakeEmbeddingGateway;

        $fake->returnEmbedding(
            $embedding
        );

        $this->app->instance(
            EmbeddingGateway::class,
            $fake
        );

        return $fake;
    }

    private function createMemory(
        User $user,
        UserCharacterProfile $profile,
        string $content,
        MemoryType $type =
            MemoryType::UserFact,
        ?array $embedding = null,
        ?int $sourceMessageId = null
    ): Memory {
        return app(
            CreateMemoryAction::class
        )->execute(
            $user,
            $profile,
            [
                'type' =>
                    $type,

                'content' =>
                    $content,

                'importance' =>
                    0.8,

                'confidence' =>
                    0.95,

                'embedding' =>
                    $embedding,

                'source_message_id' =>
                    $sourceMessageId,
            ]
        );
    }

    public function test_page_lists_only_current_users_memories_and_shows_source(): void
    {
        $owner = User::factory()
            ->create();

        $other = User::factory()
            ->create();

        $profile = $this->profileFor(
            $owner
        );

        $otherProfile =
            $this->profileFor(
                $other
            );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Origen de memoria',
            ]);

        $message = $conversation
            ->messages()
            ->create([
                'role' =>
                    MessageRole::User,

                'content' =>
                    'Este mensaje originó la memoria.',

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        $this->createMemory(
            $owner,
            $profile,
            'Memoria propia.',
            sourceMessageId:
                $message->id
        );

        $this->createMemory(
            $other,
            $otherProfile,
            'Memoria completamente ajena.'
        );

        $this
            ->actingAs($owner)
            ->get('/memories')
            ->assertOk()
            ->assertSee(
                'Memoria propia.'
            )
            ->assertSee(
                'Origen de memoria'
            )
            ->assertSee(
                'Este mensaje originó la memoria.'
            )
            ->assertDontSee(
                'Memoria completamente ajena.'
            );
    }

    public function test_user_can_filter_memories_by_type(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $this->createMemory(
            $user,
            $profile,
            'El usuario vive en Pachuca.',
            MemoryType::UserFact
        );

        $this->createMemory(
            $user,
            $profile,
            'El usuario prefiere café.',
            MemoryType::UserPreference
        );

        $this->actingAs(
            $user
        );

        Livewire::test(
            MemoryManager::class
        )
            ->set(
                'typeFilter',
                MemoryType::UserPreference->value
            )
            ->assertSee(
                'El usuario prefiere café.'
            )
            ->assertDontSee(
                'El usuario vive en Pachuca.'
            );
    }

    public function test_user_can_create_manual_memory_with_embedding(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $this->actingAs(
            $user
        );

        Livewire::test(
            MemoryManager::class
        )
            ->call(
                'startCreating'
            )
            ->set(
                'formType',
                MemoryType::UserPreference->value
            )
            ->set(
                'formContent',
                'El usuario prefiere café sin azúcar.'
            )
            ->set(
                'formImportance',
                '0.85'
            )
            ->set(
                'formConfidence',
                '1.00'
            )
            ->call(
                'saveMemory'
            )
            ->assertHasNoErrors();

        $memory = $profile
            ->memories()
            ->firstOrFail();

        $this->assertSame(
            MemoryType::UserPreference,
            $memory->type
        );

        $this->assertNull(
            $memory->source_message_id
        );

        $this->assertCount(
            Memory::EMBEDDING_DIMENSIONS,
            $memory->embedding
        );

        $retrieved = app(
            MemoryRetriever::class
        )->retrieve(
            $profile->fresh(),
            '¿Cómo prefiero el café?'
        );

        $this->assertSame(
            [
                'El usuario prefiere café sin azúcar.',
            ],
            $retrieved
                ->pluck('content')
                ->all()
        );
    }

    public function test_editing_memory_regenerates_embedding_and_affects_retrieval(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $memory =
            $this->createMemory(
                $user,
                $profile,
                'El usuario prefiere té.',
                MemoryType::UserPreference,
                $this->axis(1)
            );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $this->actingAs(
            $user
        );

        Livewire::test(
            MemoryManager::class
        )
            ->call(
                'startEditing',
                $memory->id
            )
            ->set(
                'formContent',
                'El usuario prefiere café.'
            )
            ->call(
                'saveMemory'
            )
            ->assertHasNoErrors();

        $memory->refresh();

        $this->assertSame(
            'El usuario prefiere café.',
            $memory->content
        );

        $this->assertEqualsWithDelta(
            1.0,
            $memory->embedding[0],
            0.000001
        );

        $retrieved = app(
            MemoryRetriever::class
        )->retrieve(
            $profile->fresh(),
            '¿Qué bebida prefiero?'
        );

        $this->assertSame(
            [
                'El usuario prefiere café.',
            ],
            $retrieved
                ->pluck('content')
                ->all()
        );
    }

    public function test_user_can_edit_importance_and_confidence(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $memory =
            $this->createMemory(
                $user,
                $profile,
                'El usuario trabaja con Laravel.',
                embedding:
                    $this->axis(0)
            );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $this->actingAs(
            $user
        );

        Livewire::test(
            MemoryManager::class
        )
            ->call(
                'startEditing',
                $memory->id
            )
            ->set(
                'formImportance',
                '0.95'
            )
            ->set(
                'formConfidence',
                '0.90'
            )
            ->call(
                'saveMemory'
            )
            ->assertHasNoErrors();

        $memory->refresh();

        $this->assertEqualsWithDelta(
            0.95,
            $memory->importance,
            0.0001
        );

        $this->assertEqualsWithDelta(
            0.90,
            $memory->confidence,
            0.0001
        );
    }

    public function test_user_can_delete_own_memory(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $memory =
            $this->createMemory(
                $user,
                $profile,
                'Memoria que será eliminada.'
            );

        $this->actingAs(
            $user
        );

        Livewire::test(
            MemoryManager::class
        )
            ->call(
                'deleteMemory',
                $memory->id
            );

        $this->assertDatabaseMissing(
            'memories',
            [
                'id' =>
                    $memory->id,
            ]
        );
    }
}
