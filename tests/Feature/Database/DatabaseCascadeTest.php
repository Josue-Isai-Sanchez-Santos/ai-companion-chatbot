<?php

namespace Tests\Feature\Database;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Enums\AssetType;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\GeneratedAsset;
use App\Models\Memory;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseCascadeTest extends TestCase
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

    public function test_deleting_profile_cascades_all_profile_owned_database_records(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'Cascade test',

                    'summary' =>
                        'Summary to delete',

                    'summary_updated_at' =>
                        now(),
                ]);

        $userMessage =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Cascade user message',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        $assistantMessage =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $userMessage->id,

                    'role' =>
                        MessageRole::Assistant,

                    'content' =>
                        'Cascade assistant message',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
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
                        'Cascade memory',

                    'importance' =>
                        0.8,

                    'confidence' =>
                        1.0,

                    'embedding' =>
                        null,
                ]);

        $event =
            $profile
                ->relationshipEvents()
                ->create([
                    'conversation_id' =>
                        $conversation->id,

                    'user_message_id' =>
                        $userMessage->id,

                    'assistant_message_id' =>
                        $assistantMessage->id,

                    'event_summary' =>
                        'Cascade relationship event',

                    'from_stage' =>
                        RelationshipStage::Strangers,

                    'to_stage' =>
                        RelationshipStage::Acquaintances,

                    'requested_trust_delta' => 1,
                    'applied_trust_delta' => 1,
                    'trust_before' => 0,
                    'trust_after' => 1,

                    'requested_affection_delta' => 0,
                    'applied_affection_delta' => 0,
                    'affection_before' => 0,
                    'affection_after' => 0,

                    'requested_familiarity_delta' => 1,
                    'applied_familiarity_delta' => 1,
                    'familiarity_before' => 0,
                    'familiarity_after' => 1,

                    'requested_tension_delta' => 0,
                    'applied_tension_delta' => 0,
                    'tension_before' => 0,
                    'tension_after' => 0,
                ]);

        $asset =
            GeneratedAsset::registerForProfile(
                $profile,
                AssetType::Other,
                mimeType:
                    'text/plain',
                sizeBytes:
                    4
            );

        $profileId =
            $profile->id;

        $conversationId =
            $conversation->id;

        $userMessageId =
            $userMessage->id;

        $assistantMessageId =
            $assistantMessage->id;

        $memoryId =
            $memory->id;

        $eventId =
            $event->id;

        $assetId =
            $asset->id;

        $profile->delete();

        $this->assertDatabaseMissing(
            'user_character_profiles',
            [
                'id' =>
                    $profileId,
            ]
        );

        $this->assertDatabaseMissing(
            'conversations',
            [
                'id' =>
                    $conversationId,
            ]
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $userMessageId,
            ]
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $assistantMessageId,
            ]
        );

        $this->assertDatabaseMissing(
            'memories',
            [
                'id' =>
                    $memoryId,
            ]
        );

        $this->assertDatabaseMissing(
            'relationship_events',
            [
                'id' =>
                    $eventId,
            ]
        );

        $this->assertDatabaseMissing(
            'generated_assets',
            [
                'id' =>
                    $assetId,
            ]
        );

        /*
         * The account and global character are not
         * owned by the profile and must survive.
         */
        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $user->id,
            ]
        );

        $this->assertDatabaseHas(
            'characters',
            [
                'id' =>
                    $this->character->id,
            ]
        );
    }

    public function test_deleting_user_cascades_character_profile_and_descendants(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'User cascade',
                ]);

        $message =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Delete with user',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        $profileId =
            $profile->id;

        $conversationId =
            $conversation->id;

        $messageId =
            $message->id;

        $characterId =
            $this->character->id;

        $user->delete();

        $this->assertDatabaseMissing(
            'user_character_profiles',
            [
                'id' =>
                    $profileId,
            ]
        );

        $this->assertDatabaseMissing(
            'conversations',
            [
                'id' =>
                    $conversationId,
            ]
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $messageId,
            ]
        );

        /*
         * Deleting a user must never delete the
         * reusable base character.
         */
        $this->assertDatabaseHas(
            'characters',
            [
                'id' =>
                    $characterId,
            ]
        );
    }
}
