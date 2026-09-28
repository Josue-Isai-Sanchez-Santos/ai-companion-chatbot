<?php

namespace Tests\Feature\Conversations;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\SendMessageAction;
use App\Actions\Messages\StreamMessageAction;
use App\Ai\Agents\CharacterAgent;
use App\Ai\Agents\ConversationSummaryAgent;
use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Prompts\SummaryPromptBuilder;
use App\Enums\MessageRole;
use App\Jobs\RefreshConversationSummary;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class ConversationSummaryTest extends TestCase
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
            'chatbot.summary.enabled',
            true
        );

        config()->set(
            'chatbot.summary.message_threshold',
            4
        );

        config()->set(
            'chatbot.summary.recent_message_limit',
            4
        );

        config()->set(
            'chatbot.summary.max_messages_per_refresh',
            20
        );

        config()->set(
            'chatbot.summary.max_characters',
            1500
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
     * @return array{0: Message, 1: Message}
     */
    private function addTurn(
        Conversation $conversation,
        string $userContent,
        string $assistantContent
    ): array {
        $parentId = $conversation
            ->messages()
            ->latest('created_at')
            ->latest('id')
            ->value('id');

        $userMessage = $conversation
            ->messages()
            ->create([
                'parent_message_id' =>
                    $parentId,

                'role' =>
                    MessageRole::User,

                'content' =>
                    $userContent,

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
                    $assistantContent,

                'status' =>
                    Message::STATUS_COMPLETED,
            ]);

        return [
            $userMessage,
            $assistantMessage,
        ];
    }

    public function test_summary_prompt_builder_merges_previous_summary_and_new_messages(): void
    {
        $prompt = app(
            SummaryPromptBuilder::class
        )->build(
            'RESUMEN_ANTERIOR_MARKER',
            [
                [
                    'id' => 10,
                    'role' => 'user',
                    'content' =>
                        'MENSAJE_NUEVO_MARKER',
                ],
            ],
            1500
        );

        $this->assertStringContainsString(
            'RESUMEN_ANTERIOR_MARKER',
            $prompt
        );

        $this->assertStringContainsString(
            'MENSAJE_NUEVO_MARKER',
            $prompt
        );

        $this->assertStringContainsString(
            'No copies toda la conversación',
            $prompt
        );

        $this->assertStringContainsString(
            'El resumen no sustituye las memorias permanentes',
            $prompt
        );
    }

    public function test_job_does_not_summarize_before_threshold(): void
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
                    'Below threshold',
            ]);

        [
            ,
            $assistant,
        ] = $this->addTurn(
            $conversation,
            'Hola',
            'Hola.'
        );

        ConversationSummaryAgent::fake([
            [
                'summary' =>
                    'ESTO_NO_DEBE_GUARDARSE',
            ],
        ]);

        $job =
            new RefreshConversationSummary(
                $conversation->id,
                $assistant->id
            );

        $job->handle(
            app(
                ConversationSummaryAgent::class
            ),
            app(
                SummaryPromptBuilder::class
            )
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

    public function test_job_updates_summary_after_threshold(): void
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
                    'Summary threshold',
            ]);

        $this->addTurn(
            $conversation,
            'Estoy planeando un viaje.',
            '¿A dónde quieres ir?'
        );

        [
            ,
            $assistant,
        ] = $this->addTurn(
            $conversation,
            'Quiero visitar Japón.',
            'Podemos planearlo.'
        );

        ConversationSummaryAgent::fake([
            [
                'summary' =>
                    'El usuario está planeando un viaje a Japón.',
            ],
        ]);

        $job =
            new RefreshConversationSummary(
                $conversation->id,
                $assistant->id
            );

        $job->handle(
            app(
                ConversationSummaryAgent::class
            ),
            app(
                SummaryPromptBuilder::class
            )
        );

        $conversation->refresh();

        $this->assertSame(
            'El usuario está planeando un viaje a Japón.',
            $conversation->summary
        );

        $this->assertNotNull(
            $conversation
                ->summary_updated_at
        );

        $this->assertTrue(
            $conversation
                ->summary_updated_at
                ->equalTo(
                    $assistant->created_at
                )
        );

        /*
         * Summarization must not create permanent
         * memories by itself.
         */
        $this->assertDatabaseCount(
            'memories',
            0
        );
    }

    public function test_non_streaming_chat_queues_summary_refresh(): void
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
                    'Summary queue',
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
            RefreshConversationSummary::class,

            fn (
                RefreshConversationSummary $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job->throughMessageId
                    === $result['assistant']->id
        );
    }

    public function test_streaming_completion_queues_summary_refresh(): void
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
                    'Streaming summary queue',
            ]);

        $action = app(
            StreamMessageAction::class
        );

        $started = $action->start(
            $user,
            $conversation,
            'Mensaje streaming.'
        );

        $completed = $action->complete(
            $started['assistant'],
            new GeneratedReply(
                content:
                    'Respuesta streaming.'
            )
        );

        Queue::assertPushed(
            RefreshConversationSummary::class,

            fn (
                RefreshConversationSummary $job
            ): bool =>
                $job->conversationId
                    === $conversation->id
                && $job->throughMessageId
                    === $completed->id
        );
    }

    public function test_existing_summary_reduces_recent_history_sent_to_character(): void
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

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Reduced history',

                'summary' =>
                    'RESUMEN_DE_CONTINUIDAD_MARKER',
            ]);

        foreach (
            [
                'MESSAGE_1',
                'MESSAGE_2',
                'MESSAGE_3',
                'MESSAGE_4',
                'MESSAGE_5',
                'MESSAGE_6',
            ] as $content
        ) {
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        $content,

                    'status' =>
                        Message::STATUS_COMPLETED,
                ]);
        }

        $context = app(
            CharacterAgent::class
        )->contextFor(
            $user,
            $conversation,
            'CURRENT_MESSAGE'
        );

        $this->assertSame(
            [
                'MESSAGE_4',
                'MESSAGE_5',
                'MESSAGE_6',
                'CURRENT_MESSAGE',
            ],
            array_column(
                $context->messages,
                'content'
            )
        );

        $this->assertStringContainsString(
            'RESUMEN_DE_CONTINUIDAD_MARKER',
            $context->systemPrompt
        );

        $this->assertCount(
            4,
            $context->messages
        );
    }

    public function test_summary_jobs_use_distinct_checkpoint_ids(): void
    {
        $first =
            new RefreshConversationSummary(
                15,
                40
            );

        $second =
            new RefreshConversationSummary(
                15,
                56
            );

        $this->assertSame(
            '15:40',
            $first->uniqueId()
        );

        $this->assertSame(
            '15:56',
            $second->uniqueId()
        );

        $this->assertNotSame(
            $first->uniqueId(),
            $second->uniqueId()
        );
    }

}
