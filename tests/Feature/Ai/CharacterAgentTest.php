<?php

namespace Tests\Feature\Ai;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Ai\Agents\CharacterAgent;
use App\Ai\Contracts\ChatGateway;
use App\Enums\MessageRole;
use App\Models\Character;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class CharacterAgentTest extends TestCase
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

    public function test_context_contains_only_current_users_conversation_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerProfile = $this->profileFor(
            $owner
        );

        $otherProfile = $this->profileFor(
            $other
        );

        $ownerProfile->update([
            'custom_personality' => [
                'marker' => 'OWNER_PERSONALITY_MARKER',
            ],
        ]);

        $otherProfile->update([
            'custom_personality' => [
                'marker' => 'OTHER_PERSONALITY_MARKER',
            ],
        ]);

        $ownerConversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'Owner',
                'summary' => 'OWNER_SUMMARY_MARKER',
            ]);

        $otherConversation = $otherProfile
            ->conversations()
            ->create([
                'title' => 'Other',
                'summary' => 'OTHER_SUMMARY_MARKER',
            ]);

        $ownerConversation
            ->messages()
            ->create([
                'role' => MessageRole::User,
                'content' => 'OWNER_MESSAGE_MARKER',
                'status' => Message::STATUS_COMPLETED,
            ]);

        $otherConversation
            ->messages()
            ->create([
                'role' => MessageRole::User,
                'content' => 'OTHER_MESSAGE_MARKER',
                'status' => Message::STATUS_COMPLETED,
            ]);

        $context = app(
            CharacterAgent::class
        )->contextFor(
            $owner,
            $ownerConversation,
            'OWNER_NEW_MESSAGE_MARKER'
        );

        $this->assertStringContainsString(
            'OWNER_PERSONALITY_MARKER',
            $context->systemPrompt
        );

        $this->assertStringContainsString(
            'OWNER_SUMMARY_MARKER',
            $context->systemPrompt
        );

        $this->assertStringNotContainsString(
            'OTHER_PERSONALITY_MARKER',
            $context->systemPrompt
        );

        $this->assertStringNotContainsString(
            'OTHER_SUMMARY_MARKER',
            $context->systemPrompt
        );

        $contents = array_column(
            $context->messages,
            'content'
        );

        $this->assertContains(
            'OWNER_MESSAGE_MARKER',
            $contents
        );

        $this->assertContains(
            'OWNER_NEW_MESSAGE_MARKER',
            $contents
        );

        $this->assertNotContains(
            'OTHER_MESSAGE_MARKER',
            $contents
        );
    }

    public function test_context_limits_recent_messages_and_preserves_order(): void
    {
        config()->set(
            'chatbot.recent_message_limit',
            3
        );

        $user = User::factory()->create();

        $profile = $this->profileFor(
            $user
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Recent messages',
            ]);

        foreach (
            [
                'MESSAGE_1',
                'MESSAGE_2',
                'MESSAGE_3',
                'MESSAGE_4',
            ] as $content
        ) {
            $conversation
                ->messages()
                ->create([
                    'role' => MessageRole::User,
                    'content' => $content,
                    'status' => Message::STATUS_COMPLETED,
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
                'MESSAGE_3',
                'MESSAGE_4',
                'CURRENT_MESSAGE',
            ],
            array_column(
                $context->messages,
                'content'
            )
        );
    }

    public function test_agent_rejects_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $profile = $this->profileFor(
            $owner
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Privada',
            ]);

        $this->expectException(
            AuthorizationException::class
        );

        app(CharacterAgent::class)
            ->contextFor(
                $intruder,
                $conversation,
                'Intento no autorizado'
            );
    }

    public function test_agent_sends_inspectable_prompt_to_gateway(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileFor(
            $user
        );

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Prompt inspection',
            ]);

        $fake = new FakeChatGateway;

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        $reply = app(
            CharacterAgent::class
        )->reply(
            $user,
            $conversation,
            'Hola desde CharacterAgent'
        );

        $this->assertSame(
            'Respuesta del FakeChatGateway.',
            $reply->content
        );

        $this->assertCount(
            1,
            $fake->contexts
        );

        $context = $fake
            ->contexts[0];

        $this->assertStringContainsString(
            '## 01_GLOBAL_RULES',
            $context->systemPrompt
        );

        $this->assertStringContainsString(
            '## 14_RESPONSE_PROTOCOL',
            $context->systemPrompt
        );

        $this->assertSame(
            'Hola desde CharacterAgent',
            $context->messages[
                count($context->messages) - 1
            ]['content']
        );
    }

    public function test_context_does_not_mix_conversations_from_same_user(): void
    {
        $user = User::factory()->create();

        $profile = $this->profileFor(
            $user
        );

        $firstConversation = $profile
            ->conversations()
            ->create([
                'title' => 'Primera',
                'summary' => 'FIRST_SUMMARY_MARKER',
            ]);

        $secondConversation = $profile
            ->conversations()
            ->create([
                'title' => 'Segunda',
                'summary' => 'SECOND_SUMMARY_MARKER',
            ]);

        $firstConversation
            ->messages()
            ->create([
                'role' => MessageRole::User,
                'content' => 'FIRST_MESSAGE_MARKER',
                'status' => Message::STATUS_COMPLETED,
            ]);

        $secondConversation
            ->messages()
            ->create([
                'role' => MessageRole::User,
                'content' => 'SECOND_MESSAGE_MARKER',
                'status' => Message::STATUS_COMPLETED,
            ]);

        $context = app(
            CharacterAgent::class
        )->contextFor(
            $user,
            $firstConversation,
            'CURRENT_MESSAGE_MARKER'
        );

        $this->assertStringContainsString(
            'FIRST_SUMMARY_MARKER',
            $context->systemPrompt
        );

        $this->assertStringNotContainsString(
            'SECOND_SUMMARY_MARKER',
            $context->systemPrompt
        );

        $contents = array_column(
            $context->messages,
            'content'
        );

        $this->assertContains(
            'FIRST_MESSAGE_MARKER',
            $contents
        );

        $this->assertContains(
            'CURRENT_MESSAGE_MARKER',
            $contents
        );

        $this->assertNotContains(
            'SECOND_MESSAGE_MARKER',
            $contents
        );
    }
}
