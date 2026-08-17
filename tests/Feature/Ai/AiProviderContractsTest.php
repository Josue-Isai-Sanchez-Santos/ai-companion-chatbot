<?php

namespace Tests\Feature\Ai;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\SendMessageAction;
use App\Ai\Contracts\ChatGateway;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\DTOs\CharacterContext;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Gateways\SimulatedChatGateway;
use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeChatGateway;
use Tests\Fakes\FakeEmbeddingGateway;
use Tests\TestCase;

class AiProviderContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_chat_gateway_generates_simulated_reply_through_contract(): void
    {
        $gateway = app(
            ChatGateway::class
        );

        $this->assertInstanceOf(
            SimulatedChatGateway::class,
            $gateway
        );

        $reply = $gateway->generate(
            new ChatContext(
                conversationId: 1,
                character: new CharacterContext(
                    name: 'Test Character',
                    description: null,
                    personality: [],
                    backstory: null,
                    speakingStyle: [],
                    scenario: null,
                    systemRules: null,
                    mood: 'neutral',
                    relationshipStage: 'strangers',
                    nicknameForUser: null,
                    nicknameForCharacter: null,
                ),
                messages: [
                    [
                        'role' => 'user',
                        'content' => 'Hola',
                    ],
                ],
            )
        );

        $this->assertSame(
            'Respuesta simulada: recibí tu mensaje correctamente.',
            $reply->content
        );

        $this->assertTrue(
            $reply->metadata['simulated']
        );
    }

    public function test_chat_gateway_can_be_switched_by_configuration(): void
    {
        config()->set(
            'ai.chat.driver',
            'fake'
        );

        config()->set(
            'ai.chat.drivers.fake',
            FakeChatGateway::class
        );

        $this->app->forgetInstance(
            ChatGateway::class
        );

        $gateway = app(
            ChatGateway::class
        );

        $this->assertInstanceOf(
            FakeChatGateway::class,
            $gateway
        );
    }

    public function test_embedding_gateway_can_be_switched_by_configuration(): void
    {
        config()->set(
            'ai.embedding.driver',
            'fake'
        );

        config()->set(
            'ai.embedding.drivers.fake',
            FakeEmbeddingGateway::class
        );

        $this->app->forgetInstance(
            EmbeddingGateway::class
        );

        /** @var FakeEmbeddingGateway $gateway */
        $gateway = app(
            EmbeddingGateway::class
        );

        $gateway->returnEmbedding([
            0.4,
            0.5,
            0.6,
        ]);

        $this->assertSame(
            [
                0.4,
                0.5,
                0.6,
            ],
            $gateway->embed(
                'texto de prueba'
            )
        );

        $this->assertSame(
            [
                'texto de prueba',
            ],
            $gateway->inputs
        );
    }

    public function test_send_message_action_uses_chat_gateway_contract(): void
    {
        $this->seed(
            CharacterSeeder::class
        );

        $user = User::factory()->create();

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
                'title' => 'Contrato de IA',
            ]);

        config()->set(
            'ai.chat.driver',
            'fake'
        );

        config()->set(
            'ai.chat.drivers.fake',
            FakeChatGateway::class
        );

        $this->app->forgetInstance(
            ChatGateway::class
        );

        /** @var FakeChatGateway $gateway */
        $gateway = app(
            ChatGateway::class
        );

        $gateway->replyWith(
            new GeneratedReply(
                content: 'Respuesta controlada por fake.',
                metadata: [
                    'fake' => true,
                ],
                tokenCount: 12,
            )
        );

        $result = app(
            SendMessageAction::class
        )->execute(
            $user,
            $conversation,
            'Mensaje para el gateway'
        );

        $this->assertSame(
            'Respuesta controlada por fake.',
            $result['assistant']->content
        );

        $this->assertSame(
            12,
            $result['assistant']->token_count
        );

        $this->assertTrue(
            $result['assistant']
                ->metadata['fake']
        );

        $this->assertCount(
            1,
            $gateway->contexts
        );

        $context = $gateway
            ->contexts[0];

        $this->assertSame(
            $conversation->id,
            $context->conversationId
        );

        $this->assertSame(
            'Mensaje para el gateway',
            $context->messages[
                count($context->messages) - 1
            ]['content']
        );

        $this->assertSame(
            'user',
            $context->messages[
                count($context->messages) - 1
            ]['role']
        );
    }
}
