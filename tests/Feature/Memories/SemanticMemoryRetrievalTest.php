<?php

namespace Tests\Feature\Memories;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Memories\CreateMemoryAction;
use App\Ai\Agents\CharacterAgent;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Memory\MemoryRetriever;
use App\Enums\MemoryType;
use App\Models\Character;
use App\Models\Memory;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeEmbeddingGateway;
use Tests\TestCase;

class SemanticMemoryRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CharacterSeeder::class
        );

        $this->character = Character::query()
            ->where(
                'slug',
                'default-companion'
            )
            ->firstOrFail();

        config()->set(
            'memory.enabled',
            true
        );

        config()->set(
            'memory.retrieval_limit',
            8
        );

        config()->set(
            'memory.minimum_similarity',
            0.65
        );

        config()->set(
            'memory.minimum_importance',
            0.40
        );
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
        $vector = array_fill(
            0,
            Memory::EMBEDDING_DIMENSIONS,
            0.0
        );

        $vector[$index] = 1.0;

        return $vector;
    }

    /**
     * @return list<float>
     */
    private function mixedVector(
        float $first,
        int $secondIndex,
        float $second
    ): array {
        $vector = array_fill(
            0,
            Memory::EMBEDDING_DIMENSIONS,
            0.0
        );

        $vector[0] = $first;
        $vector[$secondIndex] =
            $second;

        return $vector;
    }

    private function fakeEmbedding(
        array $embedding
    ): FakeEmbeddingGateway {
        $fake = new FakeEmbeddingGateway;

        $fake->returnEmbedding(
            $embedding
        );

        $this->app->instance(
            EmbeddingGateway::class,
            $fake
        );

        return $fake;
    }

    private function memoryFor(
        User $user,
        UserCharacterProfile $profile,
        string $content,
        array $embedding,
        float $importance = 0.8,
        float $confidence = 0.95
    ): Memory {
        return app(
            CreateMemoryAction::class
        )->execute(
            $user,
            $profile,
            [
                'type' =>
                    MemoryType::UserFact,

                'content' =>
                    $content,

                'importance' =>
                    $importance,

                'confidence' =>
                    $confidence,

                'embedding' =>
                    $embedding,
            ]
        );
    }

    public function test_related_question_retrieves_correct_memory_and_registers_access(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $fake = $this->fakeEmbedding(
            $this->axis(0)
        );

        $sushi = $this->memoryFor(
            $user,
            $profile,
            'La comida favorita del usuario es el sushi.',
            $this->axis(0)
        );

        $programming = $this->memoryFor(
            $user,
            $profile,
            'El usuario disfruta programar aplicaciones.',
            $this->axis(1)
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            '¿Cuál es mi comida favorita?'
        );

        $this->assertSame(
            [
                $sushi->id,
            ],
            $results->modelKeys()
        );

        $this->assertSame(
            [
                '¿Cuál es mi comida favorita?',
            ],
            $fake->inputs
        );

        $sushi = $sushi->fresh();
        $programming = $programming
            ->fresh();

        $this->assertSame(
            1,
            $sushi->access_count
        );

        $this->assertNotNull(
            $sushi->last_accessed_at
        );

        $this->assertSame(
            0,
            $programming->access_count
        );
    }

    public function test_unrelated_question_does_not_load_irrelevant_memories(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $this->fakeEmbedding(
            $this->axis(2)
        );

        $this->memoryFor(
            $user,
            $profile,
            'La comida favorita del usuario es sushi.',
            $this->axis(0)
        );

        $this->memoryFor(
            $user,
            $profile,
            'El usuario programa en Laravel.',
            $this->axis(1)
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Pregunta completamente no relacionada'
        );

        $this->assertTrue(
            $results->isEmpty()
        );

        $this->assertSame(
            0,
            $profile
                ->memories()
                ->sum(
                    'access_count'
                )
        );
    }

    public function test_retrieval_never_reads_another_profiles_memories(): void
    {
        $owner = User::factory()
            ->create();

        $other = User::factory()
            ->create();

        $ownerProfile = $this->profileFor(
            $owner
        );

        $otherProfile = $this->profileFor(
            $other
        );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $ownMemory = $this->memoryFor(
            $owner,
            $ownerProfile,
            'MEMORIA_PRIVADA_OWNER',
            $this->axis(0)
        );

        $foreignMemory = $this->memoryFor(
            $other,
            $otherProfile,
            'MEMORIA_PRIVADA_OTRO_USUARIO',
            $this->axis(0)
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $ownerProfile,
            'Consulta relacionada'
        );

        $this->assertSame(
            [
                $ownMemory->id,
            ],
            $results->modelKeys()
        );

        $this->assertFalse(
            $results->contains(
                fn (Memory $memory): bool =>
                    $memory->id
                    === $foreignMemory->id
            )
        );

        $this->assertSame(
            0,
            $foreignMemory
                ->fresh()
                ->access_count
        );
    }

    public function test_expired_memories_are_excluded(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $active = $this->memoryFor(
            $user,
            $profile,
            'Memoria vigente.',
            $this->axis(0)
        );

        $expired = $profile
            ->memories()
            ->create([
                'type' =>
                    MemoryType::UserFact,

                'content' =>
                    'Memoria expirada.',

                'importance' => 0.9,
                'confidence' => 1.0,

                'embedding' =>
                    $this->axis(0),

                'expires_at' =>
                    now()->subMinute(),
            ]);

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Consulta relacionada'
        );

        $this->assertSame(
            [
                $active->id,
            ],
            $results->modelKeys()
        );

        $this->assertSame(
            0,
            $expired
                ->fresh()
                ->access_count
        );
    }

    public function test_memories_below_minimum_importance_are_excluded(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $lowImportance = $this->memoryFor(
            $user,
            $profile,
            'Dato poco importante.',
            $this->axis(0),
            0.20
        );

        $important = $this->memoryFor(
            $user,
            $profile,
            'Dato suficientemente importante.',
            $this->axis(0),
            0.90
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Consulta relacionada'
        );

        $this->assertSame(
            [
                $important->id,
            ],
            $results->modelKeys()
        );

        $this->assertSame(
            0,
            $lowImportance
                ->fresh()
                ->access_count
        );
    }

    public function test_retrieval_respects_limit_and_ranking(): void
    {
        config()->set(
            'memory.retrieval_limit',
            2
        );

        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $first = $this->memoryFor(
            $user,
            $profile,
            'Memoria de mayor similitud.',
            $this->axis(0)
        );

        $second = $this->memoryFor(
            $user,
            $profile,
            'Memoria con similitud intermedia.',
            $this->mixedVector(
                0.90,
                1,
                0.435889894
            )
        );

        $third = $this->memoryFor(
            $user,
            $profile,
            'Memoria con similitud menor.',
            $this->mixedVector(
                0.80,
                2,
                0.60
            )
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Consulta'
        );

        $this->assertCount(
            2,
            $results
        );

        $this->assertSame(
            [
                $first->id,
                $second->id,
            ],
            $results->modelKeys()
        );

        $this->assertFalse(
            $results->contains(
                fn (Memory $memory): bool =>
                    $memory->id
                    === $third->id
            )
        );
    }

    public function test_semantic_duplicates_are_removed(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $preferred = $this->memoryFor(
            $user,
            $profile,
            'La comida favorita es sushi.',
            $this->axis(0),
            0.90
        );

        $duplicate = $this->memoryFor(
            $user,
            $profile,
            'La comida favorita es sushi.',
            $this->axis(0),
            0.80
        );

        $unique = $this->memoryFor(
            $user,
            $profile,
            'El usuario también disfruta ramen.',
            $this->mixedVector(
                0.80,
                1,
                0.60
            )
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Comida'
        );

        $this->assertCount(
            2,
            $results
        );

        $this->assertTrue(
            $results->contains(
                'id',
                $preferred->id
            )
        );

        $this->assertTrue(
            $results->contains(
                'id',
                $unique->id
            )
        );

        $this->assertFalse(
            $results->contains(
                'id',
                $duplicate->id
            )
        );
    }

    public function test_retrieved_memory_is_added_to_character_prompt(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Prueba memoria semántica',
            ]);

        $this->fakeEmbedding(
            $this->axis(0)
        );

        $memory = $this->memoryFor(
            $user,
            $profile,
            'La bebida favorita del usuario es café.',
            $this->axis(0)
        );

        $context = app(
            CharacterAgent::class
        )->contextFor(
            $user,
            $conversation,
            '¿Cuál es mi bebida favorita?'
        );

        $this->assertSame(
            [
                $memory->content,
            ],
            $context->relevantMemories
        );

        $this->assertStringContainsString(
            '## 13_RELEVANT_MEMORIES',
            $context->systemPrompt
        );

        $this->assertStringContainsString(
            $memory->content,
            $context->systemPrompt
        );

        $this->assertSame(
            1,
            $memory
                ->fresh()
                ->access_count
        );
    }

    public function test_disabled_memory_system_does_not_generate_embedding(): void
    {
        config()->set(
            'memory.enabled',
            false
        );

        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $fake = $this->fakeEmbedding(
            $this->axis(0)
        );

        $this->memoryFor(
            $user,
            $profile,
            'Memoria que no debe recuperarse.',
            $this->axis(0)
        );

        $results = app(
            MemoryRetriever::class
        )->retrieve(
            $profile,
            'Consulta'
        );

        $this->assertTrue(
            $results->isEmpty()
        );

        $this->assertSame(
            [],
            $fake->inputs
        );
    }
}
