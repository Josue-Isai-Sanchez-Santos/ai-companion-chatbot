<?php

namespace Tests\Feature\Relationships;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\SendMessageAction;
use App\Actions\Messages\StreamMessageAction;
use App\Ai\Agents\RelationshipAnalysisAgent;
use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Prompts\RelationshipPromptBuilder;
use App\Ai\Relationship\RelationshipChange;
use App\Ai\Relationship\RelationshipUpdater;
use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Jobs\UpdateRelationshipState;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class RelationshipProgressionTest extends TestCase
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
            'relationship.analysis.enabled',
            true
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
     *     0: Conversation,
     *     1: Message,
     *     2: Message
     * }
     */
    private function completedTurn(
        UserCharacterProfile $profile,
        string $userContent =
            'Mensaje significativo.',
        string $assistantContent =
            'Respuesta completada.'
    ): array {
        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'Relationship test',
                ]);

        $userMessage =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        $userContent,

                    'status' =>
                        Message::STATUS_COMPLETED,
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
                        $assistantContent,

                    'status' =>
                        Message::STATUS_COMPLETED,
                ]);

        return [
            $conversation,
            $userMessage,
            $assistantMessage,
        ];
    }

    public function test_prompt_contains_state_and_completed_turn(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        [
            ,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile,
            'USER_MARKER',
            'ASSISTANT_MARKER'
        );

        $prompt = app(
            RelationshipPromptBuilder::class
        )->build(
            $profile,
            'SUMMARY_MARKER',
            $userMessage,
            $assistantMessage
        );

        $this->assertStringContainsString(
            'trust=0',
            $prompt
        );

        $this->assertStringContainsString(
            'SUMMARY_MARKER',
            $prompt
        );

        $this->assertStringContainsString(
            'USER_MARKER',
            $prompt
        );

        $this->assertStringContainsString(
            'ASSISTANT_MARKER',
            $prompt
        );
    }

    public function test_backend_clamps_extreme_single_event_changes(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $event = app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento muy positivo.',
                trustDelta: 99,
                affectionDelta: 99,
                familiarityDelta: 99,
                tensionDelta: 99,
            )
        );

        $this->assertNotNull(
            $event
        );

        $profile->refresh();

        $this->assertSame(
            3,
            $profile->trust
        );

        $this->assertSame(
            3,
            $profile->affection
        );

        $this->assertSame(
            3,
            $profile->familiarity
        );

        $this->assertSame(
            3,
            $profile->tension
        );

        $this->assertSame(
            99,
            $event
                ->requested_trust_delta
        );

        $this->assertSame(
            3,
            $event
                ->applied_trust_delta
        );
    }

    public function test_metrics_never_fall_below_minimum(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $profile->update([
            'trust' => 1,
            'affection' => 1,
            'familiarity' => 1,
            'tension' => 1,
        ]);

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento negativo.',
                trustDelta: -99,
                affectionDelta: -99,
                familiarityDelta: -99,
                tensionDelta: -99,
            )
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
            0,
            $profile->familiarity
        );

        $this->assertSame(
            0,
            $profile->tension
        );
    }

    public function test_metrics_never_exceed_maximum(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $profile->update([
            'trust' => 99,
            'affection' => 99,
            'familiarity' => 99,
            'tension' => 99,
        ]);

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento extremo.',
                trustDelta: 99,
                affectionDelta: 99,
                familiarityDelta: 99,
                tensionDelta: 99,
            )
        );

        $profile->refresh();

        $this->assertSame(
            100,
            $profile->trust
        );

        $this->assertSame(
            100,
            $profile->affection
        );

        $this->assertSame(
            100,
            $profile->familiarity
        );

        $this->assertSame(
            100,
            $profile->tension
        );
    }

    public function test_relationship_stage_moves_at_most_one_step_per_event(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $profile->update([
            'relationship_stage' =>
                RelationshipStage::Strangers,

            'trust' => 79,
            'affection' => 79,
            'familiarity' => 79,
            'tension' => 0,
        ]);

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento significativo.',
                trustDelta: 1,
                affectionDelta: 1,
                familiarityDelta: 3,
                tensionDelta: 0,
            )
        );

        $profile->refresh();

        $this->assertSame(
            RelationshipStage::Acquaintances,
            $profile->relationship_stage
        );
    }

    public function test_insignificant_turn_does_not_change_or_create_event(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $event = app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            new RelationshipChange(
                significant: false,
                eventSummary:
                    'Conversación rutinaria.',
                trustDelta: 10,
                affectionDelta: 10,
                familiarityDelta: 10,
                tensionDelta: 10,
            )
        );

        $this->assertNull(
            $event
        );

        $profile->refresh();

        $this->assertSame(
            0,
            $profile->trust
        );

        $this->assertDatabaseCount(
            'relationship_events',
            0
        );
    }

    public function test_same_assistant_turn_cannot_be_applied_twice(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        [
            $conversation,
            $userMessage,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        $change =
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Se fortaleció la confianza.',
                trustDelta: 3,
                affectionDelta: 0,
                familiarityDelta: 0,
                tensionDelta: 0,
            );

        $updater = app(
            RelationshipUpdater::class
        );

        $first = $updater->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            $change
        );

        $second = $updater->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistantMessage,
            $change
        );

        $profile->refresh();

        $this->assertSame(
            3,
            $profile->trust
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'relationship_events',
            1
        );
    }

    public function test_job_uses_agent_proposal_and_backend_applies_it(): void
    {
        $user = User::factory()
            ->create();

        $profile =
            $this->profileFor(
                $user
            );

        [
            $conversation,
            ,
            $assistantMessage,
        ] = $this->completedTurn(
            $profile
        );

        RelationshipAnalysisAgent::fake([
            [
                'significant' =>
                    true,

                'event_summary' =>
                    'El usuario expresó confianza explícita.',

                'trust_delta' =>
                    8,

                'affection_delta' =>
                    2,

                'familiarity_delta' =>
                    2,

                'tension_delta' =>
                    0,
            ],
        ]);

        $job =
            new UpdateRelationshipState(
                $conversation->id,
                $assistantMessage->id
            );

        $job->handle(
            app(
                RelationshipAnalysisAgent::class
            ),
            app(
                RelationshipPromptBuilder::class
            ),
            app(
                RelationshipUpdater::class
            )
        );

        $profile->refresh();

        $this->assertSame(
            3,
            $profile->trust
        );

        $this->assertSame(
            2,
            $profile->affection
        );

        $event =
            RelationshipEvent::query()
                ->firstOrFail();

        $this->assertSame(
            8,
            $event
                ->requested_trust_delta
        );

        $this->assertSame(
            3,
            $event
                ->applied_trust_delta
        );
    }

    public function test_non_streaming_chat_queues_relationship_update(): void
    {
        Queue::fake();

        $user = User::factory()
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
                        'Relationship queue',
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
            'Mensaje de prueba.'
        );

        Queue::assertPushed(
            UpdateRelationshipState::class,

            fn (
                UpdateRelationshipState $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job
                    ->assistantMessageId
                    === $result[
                        'assistant'
                    ]->id
        );
    }

    public function test_streaming_completion_queues_relationship_update(): void
    {
        Queue::fake();

        $user = User::factory()
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
                        'Streaming relationship',
                ]);

        $action = app(
            StreamMessageAction::class
        );

        $started =
            $action->start(
                $user,
                $conversation,
                'Mensaje streaming.'
            );

        $completed =
            $action->complete(
                $started['assistant'],
                new GeneratedReply(
                    content:
                        'Respuesta completada.'
                )
            );

        Queue::assertPushed(
            UpdateRelationshipState::class,

            fn (
                UpdateRelationshipState $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job
                    ->assistantMessageId
                    === $completed->id
        );
    }
}
