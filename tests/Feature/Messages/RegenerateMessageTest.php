<?php

namespace Tests\Feature\Messages;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\DeleteMessageBranchAction;
use App\Actions\Messages\RegenerateMessageAction;
use App\Ai\Agents\CharacterAgent;
use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Relationship\RelationshipChange;
use App\Ai\Relationship\RelationshipUpdater;
use App\Ai\State\CharacterStateResolver;
use App\Enums\CharacterMood;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Models\Character;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class RegenerateMessageTest extends TestCase
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

        /*
         * These tests focus on branch behavior.
         * Semantic retrieval is tested separately.
         */
        config()->set(
            'memory.enabled',
            false
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
     * @return array{
     *     0: UserCharacterProfile,
     *     1: \App\Models\Conversation,
     *     2: Message,
     *     3: Message
     * }
     */
    private function conversationWithTurn(
        User $user
    ): array {
        $profile =
            $this->profileFor(
                $user
            );

        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'Regeneration test',
                ]);

        $userMessage =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Dame una respuesta.',

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
                        'RESPUESTA_ANTERIOR',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        return [
            $profile,
            $conversation,
            $userMessage,
            $assistant,
        ];
    }

    public function test_regeneration_creates_alternative_and_preserves_previous_branch(): void
    {
        Queue::fake();

        $user =
            User::factory()
                ->create();

        [
            ,
            $conversation,
            $userMessage,
            $previous,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $fake =
            new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'RESPUESTA_ALTERNATIVA'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $result = app(
            RegenerateMessageAction::class
        )->execute(
            $user,
            $previous
        );

        $this->assertNull(
            $result['error']
        );

        $previous->refresh();

        $replacement =
            $result['assistant'];

        $this->assertNotNull(
            $replacement
        );

        $this->assertFalse(
            $previous
                ->is_active_branch
        );

        $this->assertTrue(
            $replacement
                ->is_active_branch
        );

        $this->assertSame(
            $userMessage->id,
            $replacement
                ->parent_message_id
        );

        $this->assertSame(
            'RESPUESTA_ALTERNATIVA',
            $replacement->content
        );

        $this->assertSame(
            2,
            $conversation
                ->messages()
                ->where(
                    'parent_message_id',
                    $userMessage->id
                )
                ->count()
        );

        $this->assertSame(
            $previous->id,
            data_get(
                $replacement->metadata,
                'regeneration.replaces_message_id'
            )
        );

        Queue::assertNothingPushed();
    }

    public function test_inactive_alternative_does_not_enter_future_context(): void
    {
        $user =
            User::factory()
                ->create();

        [
            ,
            $conversation,
            ,
            $previous,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $fake =
            new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'RAMA_ACTIVA'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        app(
            RegenerateMessageAction::class
        )->execute(
            $user,
            $previous
        );

        $context = app(
            CharacterAgent::class
        )->contextFor(
            $user,
            $conversation->fresh(),
            'SIGUIENTE_MENSAJE'
        );

        $contents =
            array_column(
                $context->messages,
                'content'
            );

        $this->assertContains(
            'RAMA_ACTIVA',
            $contents
        );

        $this->assertNotContains(
            'RESPUESTA_ANTERIOR',
            $contents
        );
    }

    public function test_regeneration_invalidates_summary_that_contains_old_branch(): void
    {
        $user =
            User::factory()
                ->create();

        [
            ,
            $conversation,
            ,
            $previous,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $conversation
            ->forceFill([
                'summary' =>
                    'SUMMARY_WITH_OLD_BRANCH',

                'summary_updated_at' =>
                    $previous
                        ->created_at,
            ])
            ->save();

        $fake =
            new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'NUEVA_RESPUESTA'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        app(
            RegenerateMessageAction::class
        )->execute(
            $user,
            $previous
        );

        $conversation->refresh();

        $this->assertNull(
            $conversation->summary
        );

        $this->assertNull(
            $conversation
                ->summary_updated_at
        );
    }

    public function test_regeneration_rolls_back_relationship_effect_from_old_branch(): void
    {
        $user =
            User::factory()
                ->create();

        [
            $profile,
            $conversation,
            $userMessage,
            $previous,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $event = app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $previous,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Se fortaleció la confianza.',
                trustDelta: 3,
                affectionDelta: 2,
                familiarityDelta: 0,
                tensionDelta: 0,
            )
        );

        $this->assertNotNull(
            $event
        );

        app(
            CharacterStateResolver::class
        )->apply(
            $profile->fresh(),
            $event
        );

        $profile->refresh();

        $this->assertSame(
            3,
            $profile->trust
        );

        $this->assertSame(
            CharacterMood::Happy,
            $profile->current_mood
        );

        $fake =
            new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'RESPUESTA_REGENERADA'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $result = app(
            RegenerateMessageAction::class
        )->execute(
            $user,
            $previous
        );

        $profile->refresh();

        $this->assertSame(
            0,
            $profile->trust
        );

        $this->assertSame(
            0,
            $profile->affection
        );

        $this->assertSame(
            CharacterMood::Neutral,
            $profile->current_mood
        );

        /*
         * The old branch event remains as historical
         * branch data, but no new event is generated.
         */
        $this->assertDatabaseCount(
            'relationship_events',
            1
        );

        $this->assertDatabaseMissing(
            'relationship_events',
            [
                'assistant_message_id' =>
                    $result[
                        'assistant'
                    ]->id,
            ]
        );
    }

    public function test_cannot_regenerate_response_that_is_not_latest_active_message(): void
    {
        $user =
            User::factory()
                ->create();

        [
            ,
            $conversation,
            ,
            $firstAssistant,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $secondUser =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $firstAssistant->id,

                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Segundo mensaje.',

                    'status' =>
                        Message::STATUS_COMPLETED,

                    'is_active_branch' =>
                        true,
                ]);

        $conversation
            ->messages()
            ->create([
                'parent_message_id' =>
                    $secondUser->id,

                'role' =>
                    MessageRole::Assistant,

                'content' =>
                    'Segunda respuesta.',

                'status' =>
                    Message::STATUS_COMPLETED,

                'is_active_branch' =>
                    true,
            ]);

        $this->expectException(
            ValidationException::class
        );

        app(
            RegenerateMessageAction::class
        )->execute(
            $user,
            $firstAssistant
        );
    }

    public function test_delete_branch_removes_inactive_descendants_and_derived_memories(): void
    {
        $user =
            User::factory()
                ->create();

        [
            $profile,
            $conversation,
            ,
            $previous,
        ] = $this
            ->conversationWithTurn(
                $user
            );

        $previous->update([
            'is_active_branch' =>
                false,
        ]);

        $inactiveUser =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $previous->id,

                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Inactive user.',
                    'status' =>
                        Message::STATUS_COMPLETED,
                    'is_active_branch' =>
                        false,
                ]);

        $inactiveAssistant =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $inactiveUser->id,

                    'role' =>
                        MessageRole::Assistant,

                    'content' =>
                        'Inactive assistant.',
                    'status' =>
                        Message::STATUS_COMPLETED,
                    'is_active_branch' =>
                        false,
                ]);

        $profile
            ->memories()
            ->create([
                'source_message_id' =>
                    $inactiveAssistant->id,

                'type' =>
                    MemoryType::CharacterFact,

                'content' =>
                    'Inactive branch memory.',

                'importance' =>
                    0.8,

                'confidence' =>
                    1.0,

                'embedding' =>
                    null,
            ]);

        $deleted = app(
            DeleteMessageBranchAction::class
        )->execute(
            $user,
            $previous
        );

        $this->assertSame(
            3,
            $deleted
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $previous->id,
            ]
        );

        $this->assertDatabaseMissing(
            'messages',
            [
                'id' =>
                    $inactiveAssistant->id,
            ]
        );

        $this->assertDatabaseMissing(
            'memories',
            [
                'content' =>
                    'Inactive branch memory.',
            ]
        );
    }
}
