<?php

namespace Tests\Feature\Memories;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Memories\CreateMemoryAction;
use App\Actions\Memories\DeleteMemoryAction;
use App\Actions\Memories\UpdateMemoryAction;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Models\Character;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemoryStorageTest extends TestCase
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

    private function createMemory(
        User $user,
        UserCharacterProfile $profile,
        array $attributes = []
    ): Memory {
        return app(
            CreateMemoryAction::class
        )->execute(
            $user,
            $profile,
            array_replace(
                [
                    'type' =>
                        MemoryType::UserFact,

                    'content' =>
                        'El usuario estudia sistemas computacionales.',
                ],
                $attributes
            )
        );
    }

    public function test_user_can_create_manual_memory(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $memory = $this->createMemory(
            $user,
            $profile,
            [
                'content' =>
                    'El usuario prefiere explicaciones detalladas.',

                'importance' => 0.8,

                'confidence' => 0.95,
            ]
        );

        $this->assertDatabaseHas(
            'memories',
            [
                'id' => $memory->id,

                'user_character_profile_id' =>
                    $profile->id,

                'type' =>
                    MemoryType::UserFact->value,

                'content' =>
                    'El usuario prefiere explicaciones detalladas.',

                'access_count' => 0,
            ]
        );

        $this->assertSame(
            MemoryType::UserFact,
            $memory->type
        );

        $this->assertEqualsWithDelta(
            0.8,
            $memory->importance,
            0.0001
        );

        $this->assertEqualsWithDelta(
            0.95,
            $memory->confidence,
            0.0001
        );

        $this->assertNull(
            $memory->embedding
        );

        $this->assertNull(
            $memory->expires_at
        );
    }

    public function test_vector_embedding_can_be_stored_and_read(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $embedding = array_fill(
            0,
            Memory::EMBEDDING_DIMENSIONS,
            0.125
        );

        $memory = $this->createMemory(
            $user,
            $profile,
            [
                'embedding' =>
                    $embedding,
            ]
        )->fresh();

        $this->assertIsArray(
            $memory->embedding
        );

        $this->assertCount(
            Memory::EMBEDDING_DIMENSIONS,
            $memory->embedding
        );

        $this->assertEqualsWithDelta(
            0.125,
            $memory->embedding[0],
            0.000001
        );

        $this->assertEqualsWithDelta(
            0.125,
            $memory->embedding[
                Memory::EMBEDDING_DIMENSIONS - 1
            ],
            0.000001
        );
    }

    public function test_user_can_update_own_memory(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $memory = $this->createMemory(
            $user,
            $profile,
            [
                'embedding' => array_fill(
                    0,
                    Memory::EMBEDDING_DIMENSIONS,
                    0.25
                ),
            ]
        );

        $updated = app(
            UpdateMemoryAction::class
        )->execute(
            $user,
            $memory,
            [
                'content' =>
                    'El usuario trabaja con sistemas computacionales.',

                'importance' => 0.9,
            ]
        );

        $this->assertSame(
            'El usuario trabaja con sistemas computacionales.',
            $updated->content
        );

        $this->assertEqualsWithDelta(
            0.9,
            $updated->importance,
            0.0001
        );

        $this->assertNull(
            $updated->embedding
        );
    }

    public function test_user_can_delete_own_memory(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $memory = $this->createMemory(
            $user,
            $profile
        );

        app(
            DeleteMemoryAction::class
        )->execute(
            $user,
            $memory
        );

        $this->assertDatabaseMissing(
            'memories',
            [
                'id' => $memory->id,
            ]
        );
    }

    public function test_user_cannot_create_memory_for_another_users_profile(): void
    {
        $owner = User::factory()
            ->create();

        $intruder = User::factory()
            ->create();

        $profile = $this->profileFor(
            $owner
        );

        $this->expectException(
            AuthorizationException::class
        );

        $this->createMemory(
            $intruder,
            $profile
        );
    }

    public function test_user_cannot_update_another_users_memory(): void
    {
        $owner = User::factory()
            ->create();

        $intruder = User::factory()
            ->create();

        $profile = $this->profileFor(
            $owner
        );

        $memory = $this->createMemory(
            $owner,
            $profile
        );

        $this->expectException(
            AuthorizationException::class
        );

        app(
            UpdateMemoryAction::class
        )->execute(
            $intruder,
            $memory,
            [
                'content' =>
                    'Intento de modificación ajena.',
            ]
        );
    }

    public function test_user_cannot_delete_another_users_memory(): void
    {
        $owner = User::factory()
            ->create();

        $intruder = User::factory()
            ->create();

        $profile = $this->profileFor(
            $owner
        );

        $memory = $this->createMemory(
            $owner,
            $profile
        );

        $this->expectException(
            AuthorizationException::class
        );

        app(
            DeleteMemoryAction::class
        )->execute(
            $intruder,
            $memory
        );
    }

    public function test_memory_can_reference_source_message_from_same_profile(): void
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
                    'Origen de memoria',
            ]);

        $message = $conversation
            ->messages()
            ->create([
                'role' =>
                    MessageRole::User,

                'content' =>
                    'Mi comida favorita es el sushi.',

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        $memory = $this->createMemory(
            $user,
            $profile,
            [
                'source_message_id' =>
                    $message->id,

                'type' =>
                    MemoryType::UserPreference,

                'content' =>
                    'La comida favorita del usuario es el sushi.',
            ]
        );

        $this->assertSame(
            $message->id,
            $memory->source_message_id
        );

        $this->assertTrue(
            $memory
                ->sourceMessage
                ->is($message)
        );

        $this->assertTrue(
            $message
                ->sourceMemories()
                ->whereKey(
                    $memory->id
                )
                ->exists()
        );
    }

    public function test_source_message_cannot_come_from_another_profile(): void
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

        $otherConversation = $otherProfile
            ->conversations()
            ->create([
                'title' =>
                    'Otra conversación',
            ]);

        $foreignMessage = $otherConversation
            ->messages()
            ->create([
                'role' =>
                    MessageRole::User,

                'content' =>
                    'Mensaje ajeno',

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        try {
            $this->createMemory(
                $owner,
                $ownerProfile,
                [
                    'source_message_id' =>
                        $foreignMessage->id,
                ]
            );

            $this->fail(
                'Se esperaba ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'source_message_id',
                $exception
                    ->errors()
            );
        }

        $this->assertSame(
            0,
            $ownerProfile
                ->memories()
                ->count()
        );
    }

    public function test_temporary_memory_requires_expiration(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        try {
            $this->createMemory(
                $user,
                $profile,
                [
                    'type' =>
                        MemoryType::TemporaryContext,

                    'content' =>
                        'Contexto válido únicamente durante un tiempo.',
                ]
            );

            $this->fail(
                'Se esperaba ValidationException.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'expires_at',
                $exception
                    ->errors()
            );
        }

        $this->assertSame(
            0,
            $profile
                ->memories()
                ->count()
        );
    }

    public function test_temporary_memory_can_have_future_expiration(): void
    {
        $user = User::factory()
            ->create();

        $profile = $this->profileFor(
            $user
        );

        $memory = $this->createMemory(
            $user,
            $profile,
            [
                'type' =>
                    MemoryType::TemporaryContext,

                'content' =>
                    'El usuario está preparando una prueba.',

                'expires_at' =>
                    now()->addHour(),
            ]
        );

        $this->assertSame(
            MemoryType::TemporaryContext,
            $memory->type
        );

        $this->assertNotNull(
            $memory->expires_at
        );

        $this->assertTrue(
            $profile
                ->memories()
                ->available()
                ->whereKey(
                    $memory->id
                )
                ->exists()
        );
    }
}
