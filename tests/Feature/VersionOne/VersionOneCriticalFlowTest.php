<?php

namespace Tests\Feature\VersionOne;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Conversations\CreateConversationAction;
use App\Actions\Messages\SendMessageAction;
use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Enums\CharacterMood;
use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

final class VersionOneCriticalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(
            CharacterSeeder::class
        );

        /*
         * This smoke test verifies persistence and
         * integration. Background AI-derived features
         * have their own dedicated test suites.
         */
        Queue::fake();

        config()->set(
            'memory.enabled',
            false
        );
    }

    public function test_version_one_basic_chat_flow_works_without_external_ai(): void
    {
        $user =
            User::factory()
                ->create();

        $character =
            Character::query()
                ->where(
                    'slug',
                    'default-companion'
                )
                ->firstOrFail();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $character
        );

        $this->assertSame(
            CharacterMood::Neutral,
            $profile->current_mood
        );

        $this->assertSame(
            RelationshipStage::Strangers,
            $profile->relationship_stage
        );

        $this->assertSame(
            'neutral',
            $profile
                ->currentExpression
                ->name
        );

        $conversation = app(
            CreateConversationAction::class
        )->execute(
            $user,
            $profile,
            'Flujo crítico v1'
        );

        $fake =
            new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'Respuesta generada sin proveedor externo.',

                metadata: [
                    'test' =>
                        true,
                ],

                tokenCount:
                    7
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $result = app(
            SendMessageAction::class
        )->execute(
            $user,
            $conversation,
            'Hola, esta es una prueba v1.'
        );

        $this->assertNull(
            $result['error']
        );

        $this->assertNotNull(
            $result['assistant']
        );

        $this->assertSame(
            MessageRole::User,
            $result[
                'user'
            ]->role
        );

        $this->assertSame(
            MessageRole::Assistant,
            $result[
                'assistant'
            ]->role
        );

        $this->assertSame(
            'Respuesta generada sin proveedor externo.',
            $result[
                'assistant'
            ]->content
        );

        $this->assertSame(
            $result[
                'user'
            ]->id,
            $result[
                'assistant'
            ]->parent_message_id
        );

        $this->assertTrue(
            $result[
                'user'
            ]->is_active_branch
        );

        $this->assertTrue(
            $result[
                'assistant'
            ]->is_active_branch
        );

        $this->assertSame(
            2,
            $conversation
                ->messages()
                ->count()
        );

        $this->assertSame(
            [
                'Hola, esta es una prueba v1.',
                'Respuesta generada sin proveedor externo.',
            ],
            $conversation
                ->messages()
                ->chronological()
                ->pluck(
                    'content'
                )
                ->all()
        );

        $this->assertCount(
            1,
            $fake->contexts
        );

        $context =
            $fake->contexts[0];

        $this->assertSame(
            'Hola, esta es una prueba v1.',
            $context
                ->messages[
                    count(
                        $context->messages
                    ) - 1
                ][
                    'content'
                ]
        );

        /*
         * No real API credential is needed or used.
         */
        $this->assertSame(
            'simulated',
            config(
                'ai.chat.driver'
            )
        );

        $this->assertSame(
            'simulated',
            config(
                'ai.embedding.driver'
            )
        );
    }

    public function test_each_test_starts_without_previous_users(): void
    {
        /*
         * RefreshDatabase must isolate this test from
         * the previous smoke test regardless of order.
         */
        $this->assertSame(
            0,
            User::query()
                ->count()
        );

        $this->assertSame(
            0,
            Message::query()
                ->count()
        );
    }
}
