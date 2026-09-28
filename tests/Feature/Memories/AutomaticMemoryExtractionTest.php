<?php

namespace Tests\Feature\Memories;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Memories\CreateMemoryAction;
use App\Actions\Messages\SendMessageAction;
use App\Actions\Messages\StreamMessageAction;
use App\Ai\Contracts\ChatGateway;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Memory\MemoryExtractionAgent;
use App\Ai\Memory\MemoryExtractor;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Jobs\ExtractConversationMemories;
use App\Models\Character;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatGateway;
use Tests\Fakes\FakeEmbeddingGateway;
use Tests\TestCase;

class AutomaticMemoryExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

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

        config()->set(
            'memory.extraction.enabled',
            true
        );

        config()->set(
            'memory.extraction.minimum_importance',
            0.45
        );

        config()->set(
            'memory.extraction.minimum_confidence',
            0.70
        );

        config()->set(
            'memory.extraction.max_memories_per_job',
            4
        );

        config()->set(
            'memory.extraction.duplicate_similarity',
            0.92
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
    private function embedding(): array
    {
        $embedding = array_fill(
            0,
            Memory::EMBEDDING_DIMENSIONS,
            0.0
        );

        $embedding[0] = 1.0;

        return $embedding;
    }

    private function fakeEmbedding(): FakeEmbeddingGateway
    {
        $fake = new FakeEmbeddingGateway;

        $fake->returnEmbedding(
            $this->embedding()
        );

        $this->app->instance(
            EmbeddingGateway::class,
            $fake
        );

        return $fake;
    }

    /**
     * @return array{0: Message, 1: Message}
     */
    private function completedTurn(
        UserCharacterProfile $profile
    ): array {
        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Extracción automática',
            ]);

        $userMessage = $conversation
            ->messages()
            ->create([
                'role' =>
                    MessageRole::User,

                'content' =>
                    'Mi comida favorita es el sushi.',

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        $assistantMessage = $conversation
            ->messages()
            ->create([
                'parent_message_id' =>
                    $userMessage->id,

                'role' =>
                    MessageRole::Assistant,

                'content' =>
                    'Entendido.',

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        return [
            $userMessage,
            $assistantMessage,
        ];
    }

    public function test_job_extracts_and_stores_stable_memory(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        [
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $this->fakeEmbedding();

        MemoryExtractionAgent::fake([
            [
                'memories' => [
                    [
                        'type' =>
                            MemoryType::UserPreference->value,

                        'content' =>
                            'La comida favorita del usuario es el sushi.',

                        'importance' =>
                            0.85,

                        'confidence' =>
                            0.98,

                        'source_message_id' =>
                            $userMessage->id,
                    ],
                ],
            ],
        ]);

        $job =
            new ExtractConversationMemories(
                $assistantMessage
                    ->conversation_id,

                $assistantMessage
                    ->id
            );

        $job->handle(
            app(
                MemoryExtractor::class
            )
        );

        $memory = $profile
            ->memories()
            ->firstOrFail();

        $this->assertSame(
            MemoryType::UserPreference,
            $memory->type
        );

        $this->assertSame(
            'La comida favorita del usuario es el sushi.',
            $memory->content
        );

        $this->assertEqualsWithDelta(
            0.85,
            $memory->importance,
            0.0001
        );

        $this->assertEqualsWithDelta(
            0.98,
            $memory->confidence,
            0.0001
        );

        $this->assertSame(
            $userMessage->id,
            $memory->source_message_id
        );

        $this->assertCount(
            Memory::EMBEDDING_DIMENSIONS,
            $memory->embedding
        );
    }

    public function test_low_quality_and_temporary_candidates_are_rejected(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        [
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $fakeEmbedding =
            $this->fakeEmbedding();

        MemoryExtractionAgent::fake([
            [
                'memories' => [
                    [
                        'type' =>
                            MemoryType::TemporaryContext->value,

                        'content' =>
                            'El usuario tiene sueño ahora.',

                        'importance' =>
                            0.8,

                        'confidence' =>
                            0.9,

                        'source_message_id' =>
                            $userMessage->id,
                    ],

                    [
                        'type' =>
                            MemoryType::UserFact->value,

                        'content' =>
                            'Quizá el usuario prefiera pizza.',

                        'importance' =>
                            0.8,

                        'confidence' =>
                            0.3,

                        'source_message_id' =>
                            $userMessage->id,
                    ],
                ],
            ],
        ]);

        $job =
            new ExtractConversationMemories(
                $assistantMessage
                    ->conversation_id,

                $assistantMessage
                    ->id
            );

        $job->handle(
            app(
                MemoryExtractor::class
            )
        );

        $this->assertSame(
            0,
            $profile
                ->memories()
                ->count()
        );

        $this->assertSame(
            [],
            $fakeEmbedding->inputs
        );
    }

    public function test_semantic_duplicate_is_not_created(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        [
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $embedding =
            $this->embedding();

        app(
            CreateMemoryAction::class
        )->execute(
            $user,
            $profile,
            [
                'type' =>
                    MemoryType::UserPreference,

                'content' =>
                    'El usuario prefiere sushi.',

                'importance' =>
                    0.9,

                'confidence' =>
                    1.0,

                'embedding' =>
                    $embedding,
            ]
        );

        $this->fakeEmbedding();

        MemoryExtractionAgent::fake([
            [
                'memories' => [
                    [
                        'type' =>
                            MemoryType::UserPreference->value,

                        'content' =>
                            'La comida favorita del usuario es sushi.',

                        'importance' =>
                            0.9,

                        'confidence' =>
                            0.98,

                        'source_message_id' =>
                            $userMessage->id,
                    ],
                ],
            ],
        ]);

        $job =
            new ExtractConversationMemories(
                $assistantMessage
                    ->conversation_id,

                $assistantMessage
                    ->id
            );

        $job->handle(
            app(
                MemoryExtractor::class
            )
        );

        $this->assertSame(
            1,
            $profile
                ->memories()
                ->count()
        );
    }

    public function test_non_streaming_chat_only_queues_extraction(): void
    {
        Queue::fake();

        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Queue test',
            ]);

        $fakeChat =
            new FakeChatGateway;

        $fakeChat->replyWith(
            new GeneratedReply(
                content:
                    'Respuesta completada.'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fakeChat
        );

        $result = app(
            SendMessageAction::class
        )->execute(
            $user,
            $conversation,
            'Mi color favorito es azul.'
        );

        Queue::assertPushed(
            ExtractConversationMemories::class,

            fn (
                ExtractConversationMemories $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job->throughMessageId
                    === $result['assistant']->id
        );

        $this->assertDatabaseCount(
            'memories',
            0
        );
    }

    public function test_streaming_completion_queues_extraction(): void
    {
        Queue::fake();

        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Streaming queue test',
            ]);

        $action = app(
            StreamMessageAction::class
        );

        $started = $action->start(
            $user,
            $conversation,
            'Mi bebida favorita es café.'
        );

        $completed = $action->complete(
            $started['assistant'],
            new GeneratedReply(
                content:
                    'Lo tendré en cuenta.'
            )
        );

        Queue::assertPushed(
            ExtractConversationMemories::class,

            fn (
                ExtractConversationMemories $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job->throughMessageId
                    === $completed->id
        );

        $this->assertDatabaseCount(
            'memories',
            0
        );
    }
}
