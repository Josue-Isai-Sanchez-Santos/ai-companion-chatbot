<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Characters\DeleteCharacterAssets;
use App\Actions\Characters\ResetCharacterAction;
use App\Ai\Relationship\RelationshipChange;
use App\Ai\Relationship\RelationshipUpdater;
use App\Enums\CharacterMood;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Livewire\ResetCharacterModal;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\Memory;
use App\Models\Message;
use App\Models\ResetAudit;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ResetCharacterTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Storage::fake(
            'public'
        );

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

    public function test_complete_reset_recreates_clean_profile_and_deletes_character_state(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $oldProfileId =
            $profile->id;

        $happy =
            $this->character
                ->expressions()
                ->where(
                    'name',
                    'happy'
                )
                ->firstOrFail();

        $profile->update([
            'custom_personality' => [
                'marker' =>
                    'CUSTOM_PERSONALITY',
            ],

            'custom_speaking_style' => [
                'marker' =>
                    'CUSTOM_STYLE',
            ],

            'custom_scenario' =>
                'CUSTOM_SCENARIO',

            'nickname_for_user' =>
                'USER_NICKNAME',

            'nickname_for_character' =>
                'CHARACTER_NICKNAME',

            'current_mood' =>
                CharacterMood::Happy,

            'current_expression_id' =>
                $happy->id,

            'relationship_stage' =>
                RelationshipStage::Friends,

            'trust' => 40,
            'affection' => 35,
            'familiarity' => 50,
            'tension' => 12,

            'last_interaction_at' =>
                now(),
        ]);

        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'Conversación anterior',

                    'summary' =>
                        'OLD_SUMMARY',

                    'summary_updated_at' =>
                        now(),

                    'last_message_at' =>
                        now(),
                ]);

        $userMessage =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Mensaje anterior',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        $assistant =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $userMessage->id,

                    'role' =>
                        MessageRole::Assistant,

                    'content' =>
                        'Respuesta anterior',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        /*
         * Also prove that an inactive regeneration
         * branch disappears with the conversation.
         */
        $conversation
            ->messages()
            ->create([
                'parent_message_id' =>
                    $userMessage->id,

                'role' =>
                    MessageRole::Assistant,

                'content' =>
                    'Rama alternativa antigua',

                'status' =>
                    Message::STATUS_COMPLETED,

                'is_active_branch' =>
                    false,
            ]);

        $memory =
            $profile
                ->memories()
                ->create([
                    'source_message_id' =>
                        $userMessage->id,

                    'type' =>
                        MemoryType::UserFact,

                    'content' =>
                        'OLD_MEMORY',

                    'importance' =>
                        0.8,

                    'confidence' =>
                        1.0,

                    'embedding' =>
                        array_fill(
                            0,
                            Memory::EMBEDDING_DIMENSIONS,
                            0.01
                        ),
                ]);

        $relationshipEvent = app(
            RelationshipUpdater::class
        )->apply(
            $profile->fresh(),
            $conversation,
            $userMessage,
            $assistant,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento previo al reset.',
                trustDelta: 3,
                affectionDelta: 2,
                familiarityDelta: 1,
                tensionDelta: 0,
            )
        );

        $this->assertNotNull(
            $relationshipEvent
        );

        $this->character->update([
            'avatar_path' =>
                'characters/base-avatar.png',
        ]);

        $neutral =
            $this->character
                ->defaultExpression()
                ->firstOrFail();

        $neutral->update([
            'image_path' =>
                'characters/neutral.png',
        ]);

        Storage::disk('public')
            ->put(
                'characters/base-avatar.png',
                'BASE_AVATAR'
            );

        Storage::disk('public')
            ->put(
                'characters/neutral.png',
                'BASE_EXPRESSION'
            );

        $assetDirectory = app(
            DeleteCharacterAssets::class
        )->directoryFor(
            $oldProfileId
        );

        Storage::disk('public')
            ->put(
                $assetDirectory
                    .'/generated-image.png',
                'GENERATED'
            );

        $userCountBefore =
            User::query()->count();

        $characterCountBefore =
            Character::query()->count();

        $result = app(
            ResetCharacterAction::class
        )->execute(
            $user,
            $profile->fresh()
        );

        $newProfile =
            $result['profile']
                ->fresh([
                    'currentExpression',
                ]);

        $newConversation =
            $result['conversation'];

        $this->assertNotSame(
            $oldProfileId,
            $newProfile->id
        );

        $this->assertSame(
            $user->id,
            $newProfile->user_id
        );

        $this->assertSame(
            $this->character->id,
            $newProfile->character_id
        );

        $this->assertNull(
            $newProfile->custom_personality
        );

        $this->assertNull(
            $newProfile->custom_speaking_style
        );

        $this->assertNull(
            $newProfile->custom_scenario
        );

        $this->assertNull(
            $newProfile->nickname_for_user
        );

        $this->assertNull(
            $newProfile->nickname_for_character
        );

        $this->assertSame(
            CharacterMood::Neutral,
            $newProfile->current_mood
        );

        $this->assertSame(
            'neutral',
            $newProfile
                ->currentExpression
                ->name
        );

        $this->assertSame(
            RelationshipStage::Strangers,
            $newProfile
                ->relationship_stage
        );

        $this->assertSame(
            0,
            $newProfile->trust
        );

        $this->assertSame(
            0,
            $newProfile->affection
        );

        $this->assertSame(
            0,
            $newProfile->familiarity
        );

        $this->assertSame(
            0,
            $newProfile->tension
        );

        $this->assertNull(
            $newProfile->last_interaction_at
        );

        $this->assertDatabaseMissing(
            'user_character_profiles',
            [
                'id' =>
                    $oldProfileId,
            ]
        );

        $this->assertDatabaseMissing(
            'conversations',
            [
                'id' =>
                    $conversation->id,
            ]
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $userMessage->id,
            ]
        );

        $this->assertDatabaseMissing(
            'memories',
            [
                'id' =>
                    $memory->id,
            ]
        );

        $this->assertDatabaseMissing(
            'relationship_events',
            [
                'id' =>
                    $relationshipEvent->id,
            ]
        );

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' =>
                    $newConversation->id,

                'user_character_profile_id' =>
                    $newProfile->id,

                'title' =>
                    'Nueva conversación',

                'summary' =>
                    null,
            ]
        );

        $this->assertSame(
            0,
            $newConversation
                ->messages()
                ->count()
        );

        $this->assertSame(
            $userCountBefore,
            User::query()->count()
        );

        $this->assertSame(
            $characterCountBefore,
            Character::query()->count()
        );

        $this->assertDatabaseHas(
            'characters',
            [
                'id' =>
                    $this->character->id,

                'avatar_path' =>
                    'characters/base-avatar.png',
            ]
        );

        $this->assertDatabaseHas(
            'character_expressions',
            [
                'id' =>
                    $neutral->id,

                'image_path' =>
                    'characters/neutral.png',

                'is_default' =>
                    true,
            ]
        );

        Storage::disk('public')
            ->assertMissing(
                $assetDirectory
                    .'/generated-image.png'
            );

        Storage::disk('public')
            ->assertExists(
                'characters/base-avatar.png'
            );

        Storage::disk('public')
            ->assertExists(
                'characters/neutral.png'
            );

        $this->assertTrue(
            $result[
                'assets_deleted'
            ]
        );

        $audit =
            ResetAudit::query()
                ->sole();

        $this->assertSame(
            $user->id,
            $audit->user_id
        );

        $this->assertSame(
            $this->character->id,
            $audit->character_id
        );

        $this->assertSame(
            $oldProfileId,
            $audit
                ->previous_profile_id
        );

        $this->assertSame(
            $newProfile->id,
            $audit
                ->new_profile_id
        );

        $this->assertSame(
            1,
            $audit
                ->deleted_conversations
        );

        $this->assertSame(
            3,
            $audit
                ->deleted_messages
        );

        $this->assertSame(
            1,
            $audit
                ->deleted_memories
        );

        $this->assertSame(
            1,
            $audit
                ->deleted_relationship_events
        );
    }

    public function test_reset_requires_exact_confirmation_word(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $this
            ->actingAs($user)
            ->from('/chat')
            ->post(
                route(
                    'character.reset'
                ),
                [
                    'profile_id' =>
                        $profile->id,

                    'confirmation' =>
                        'borrar',
                ]
            )
            ->assertRedirect(
                '/chat'
            )
            ->assertSessionHasErrors(
                'confirmation'
            );

        $this->assertDatabaseHas(
            'user_character_profiles',
            [
                'id' =>
                    $profile->id,
            ]
        );

        $this->assertDatabaseCount(
            'reset_audits',
            0
        );
    }

    public function test_user_cannot_reset_another_users_profile(): void
    {
        $owner =
            User::factory()
                ->create();

        $intruder =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $owner
            );

        $this->expectException(
            AuthorizationException::class
        );

        app(
            ResetCharacterAction::class
        )->execute(
            $intruder,
            $profile
        );
    }

    public function test_database_failure_rolls_back_entire_reset_and_keeps_assets(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $oldProfileId =
            $profile->id;

        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'Debe sobrevivir',
                ]);

        $conversation
            ->messages()
            ->create([
                'role' =>
                    MessageRole::User,

                'content' =>
                    'Debe sobrevivir',

                'status' =>
                    Message::STATUS_COMPLETED,

                'is_active_branch' =>
                    true,
            ]);

        $assetDirectory = app(
            DeleteCharacterAssets::class
        )->directoryFor(
            $oldProfileId
        );

        Storage::disk('public')
            ->put(
                $assetDirectory
                    .'/keep-me.txt',
                'KEEP'
            );

        /*
         * Force the failure after the old profile has
         * been deleted and the new one has been
         * created, but before commit.
         */
        Conversation::creating(
            function (
                Conversation $conversation
            ): void {
                if (
                    $conversation->title
                    === 'Nueva conversación'
                ) {
                    throw new RuntimeException(
                        'Forced reset failure.'
                    );
                }
            }
        );

        try {
            app(
                ResetCharacterAction::class
            )->execute(
                $user,
                $profile
            );

            $this->fail(
                'The forced reset failure was not thrown.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Forced reset failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas(
            'user_character_profiles',
            [
                'id' =>
                    $oldProfileId,

                'user_id' =>
                    $user->id,
            ]
        );

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' =>
                    $conversation->id,

                'user_character_profile_id' =>
                    $oldProfileId,
            ]
        );

        $this->assertDatabaseCount(
            'reset_audits',
            0
        );

        Storage::disk('public')
            ->assertExists(
                $assetDirectory
                    .'/keep-me.txt'
            );
    }

    public function test_reset_modal_explains_destructive_confirmation(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $this->actingAs(
            $user
        );

        Livewire::test(
            ResetCharacterModal::class,
            [
                'profileId' =>
                    $profile->id,
            ]
        )
            ->assertSee(
                'Restablecer personaje'
            )
            ->call(
                'openModal'
            )
            ->assertSet(
                'open',
                true
            )
            ->assertSee(
                'BORRAR'
            )
            ->assertSee(
                'Conversaciones, mensajes y ramas.'
            )
            ->assertSee(
                'Borrar y restablecer'
            );
    }

    public function test_successful_http_reset_redirects_to_fresh_chat(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $oldProfileId =
            $profile->id;

        $profile
            ->conversations()
            ->create([
                'title' =>
                    'Vieja conversación',
            ]);

        $this
            ->actingAs(
                $user
            )
            ->post(
                route(
                    'character.reset'
                ),
                [
                    'profile_id' =>
                        $oldProfileId,

                    'confirmation' =>
                        'BORRAR',
                ]
            )
            ->assertRedirect(
                route('chat')
            )
            ->assertSessionHas(
                'status'
            );

        $newProfile =
            UserCharacterProfile::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'character_id',
                    $this->character->id
                )
                ->sole();

        $this->assertNotSame(
            $oldProfileId,
            $newProfile->id
        );

        $this->assertSame(
            1,
            $newProfile
                ->conversations()
                ->count()
        );
    }
}
