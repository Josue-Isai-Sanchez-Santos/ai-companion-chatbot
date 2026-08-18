<?php

namespace Tests\Feature\Ai;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\StreamMessageAction;
use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Exceptions\AiProviderException;
use App\Enums\MessageRole;
use App\Models\Character;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class StreamedAiResponseTest extends TestCase
{
    use RefreshDatabase;

    private function setupConversation(): array
    {
        $this->seed(
            CharacterSeeder::class
        );

        $user = User::factory()
            ->create();

        $character = Character::query()
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

        $conversation = $profile
            ->conversations()
            ->create([
                'title' =>
                    'Prueba streaming',
            ]);

        return [
            $user,
            $conversation,
        ];
    }

    public function test_streaming_response_is_persisted_once(): void
    {
        [
            $user,
            $conversation,
        ] = $this->setupConversation();

        $fake = new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'Hola desde streaming.',

                metadata: [
                    'fake' => true,
                ],

                tokenCount: 4,
            )
        );

        $fake->streamWith([
            'Hola ',
            'desde ',
            'streaming.',
        ]);

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route('chat.stream'),

                [
                    'conversation_id' =>
                        $conversation->id,

                    'message' =>
                        'Respóndeme progresivamente',
                ],

                [
                    'Accept' =>
                        'application/json, text/event-stream',
                ]
            );

        $response->assertOk();

        $body = $response
            ->streamedContent();

        $this->assertStringContainsString(
            'event: started',
            $body
        );

        $this->assertStringContainsString(
            'event: delta',
            $body
        );

        $this->assertStringContainsString(
            '"delta":"Hola "',
            $body
        );

        $this->assertStringContainsString(
            'event: completed',
            $body
        );

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get();

        $this->assertCount(
            2,
            $messages
        );

        $userMessage = $messages[0];
        $assistant = $messages[1];

        $this->assertSame(
            MessageRole::User,
            $userMessage->role
        );

        $this->assertSame(
            MessageRole::Assistant,
            $assistant->role
        );

        $this->assertSame(
            $userMessage->id,
            $assistant->parent_message_id
        );

        $this->assertSame(
            'Hola desde streaming.',
            $assistant->content
        );

        $this->assertSame(
            Message::STATUS_COMPLETED,
            $assistant->status
        );

        $this->assertSame(
            4,
            $assistant->token_count
        );

        $this->assertSame(
            1,
            $conversation
                ->messages()
                ->where(
                    'role',
                    MessageRole::Assistant->value
                )
                ->count()
        );

        $this->assertCount(
            1,
            $fake->contexts
        );
    }

    public function test_failed_stream_can_retry_without_duplicate_assistant(): void
    {
        [
            $user,
            $conversation,
        ] = $this->setupConversation();

        $fake = new FakeChatGateway;

        $fake->failStreamWith(
            new AiProviderException(
                'Simulated streamed failure.'
            ),

            [
                'Respuesta parcial ',
            ]
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $failedResponse = $this
            ->actingAs($user)
            ->post(
                route('chat.stream'),

                [
                    'conversation_id' =>
                        $conversation->id,

                    'message' =>
                        'Provoca un fallo',
                ],

                [
                    'Accept' =>
                        'application/json, text/event-stream',
                ]
            );

        $failedResponse->assertOk();

        $failedBody = $failedResponse
            ->streamedContent();

        $this->assertStringContainsString(
            'event: failed',
            $failedBody
        );

        $assistant = $conversation
            ->messages()
            ->where(
                'role',
                MessageRole::Assistant->value
            )
            ->sole();

        $assistantId = $assistant->id;

        $this->assertSame(
            Message::STATUS_FAILED,
            $assistant->status
        );

        $this->assertSame(
            'Respuesta parcial ',
            $assistant->content
        );

        $fake->resetFailures();

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'Respuesta recuperada.',

                metadata: [
                    'fake' => true,
                ],

                tokenCount: 3,
            )
        );

        $fake->streamWith([
            'Respuesta ',
            'recuperada.',
        ]);

        $retryResponse = $this
            ->actingAs($user)
            ->post(
                route(
                    'chat.stream.retry'
                ),

                [
                    'assistant_message_id' =>
                        $assistantId,
                ],

                [
                    'Accept' =>
                        'application/json, text/event-stream',
                ]
            );

        $retryResponse->assertOk();

        $retryBody = $retryResponse
            ->streamedContent();

        $this->assertStringContainsString(
            'event: completed',
            $retryBody
        );

        $assistant = Message::query()
            ->findOrFail(
                $assistantId
            );

        $this->assertSame(
            $assistantId,
            $assistant->id
        );

        $this->assertSame(
            Message::STATUS_COMPLETED,
            $assistant->status
        );

        $this->assertSame(
            'Respuesta recuperada.',
            $assistant->content
        );

        $this->assertSame(
            1,
            $conversation
                ->messages()
                ->where(
                    'role',
                    MessageRole::Assistant->value
                )
                ->count()
        );

        $this->assertSame(
            1,
            $conversation
                ->messages()
                ->where(
                    'role',
                    MessageRole::User->value
                )
                ->count()
        );
    }

    public function test_interrupted_stream_reuses_same_assistant_on_retry(): void
    {
        [
            $user,
            $conversation,
        ] = $this->setupConversation();

        $streamMessage = app(
            StreamMessageAction::class
        );

        $state = $streamMessage->start(
            $user,
            $conversation,
            'Interrumpe esta respuesta'
        );

        $assistantId =
            $state['assistant']->id;

        $streamMessage->interrupt(
            $state['assistant'],
            'Fragmento antes de cortar'
        );

        $this->assertSame(
            Message::STATUS_INTERRUPTED,
            Message::query()
                ->findOrFail(
                    $assistantId
                )
                ->status
        );

        $fake = new FakeChatGateway;

        $fake->replyWith(
            new GeneratedReply(
                content:
                    'Respuesta después de reconectar.',

                metadata: [
                    'fake' => true,
                ],

                tokenCount: 5,
            )
        );

        $fake->streamWith([
            'Respuesta ',
            'después de ',
            'reconectar.',
        ]);

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'chat.stream.retry'
                ),

                [
                    'assistant_message_id' =>
                        $assistantId,
                ],

                [
                    'Accept' =>
                        'application/json, text/event-stream',
                ]
            );

        $response->assertOk();

        $body = $response
            ->streamedContent();

        $this->assertStringContainsString(
            'event: completed',
            $body
        );

        $assistant = Message::query()
            ->findOrFail(
                $assistantId
            );

        $this->assertSame(
            $assistantId,
            $assistant->id
        );

        $this->assertSame(
            Message::STATUS_COMPLETED,
            $assistant->status
        );

        $this->assertSame(
            'Respuesta después de reconectar.',
            $assistant->content
        );

        $this->assertSame(
            1,
            $conversation
                ->messages()
                ->where(
                    'role',
                    MessageRole::Assistant->value
                )
                ->count()
        );
    }
}
