<?php

namespace Tests\Feature\Ai;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\SendMessageAction;
use App\Ai\Contracts\ChatGateway;
use App\Ai\Exceptions\AiGatewayException;
use App\Enums\MessageRole;
use App\Livewire\Chat\ChatPage;
use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeChatGateway;
use Tests\TestCase;

class AiResponseFailureTest extends TestCase
{
    use RefreshDatabase;

    private function setupConversation(): array
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
                'title' => 'Proveedor con error',
            ]);

        return [
            $user,
            $conversation,
        ];
    }

    public function test_provider_failure_keeps_user_message(): void
    {
        [
            $user,
            $conversation,
        ] = $this->setupConversation();

        $fake = new FakeChatGateway;

        $fake->failWith(
            new AiGatewayException(
                'Simulated provider outage.'
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
            'Este mensaje debe sobrevivir'
        );

        $this->assertNull(
            $result['assistant']
        );

        $this->assertNotNull(
            $result['error']
        );

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get();

        $this->assertCount(
            1,
            $messages
        );

        $this->assertSame(
            MessageRole::User,
            $messages[0]->role
        );

        $this->assertSame(
            'Este mensaje debe sobrevivir',
            $messages[0]->content
        );

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversation->id,
            ]
        );

        $this->assertNotNull(
            $conversation
                ->fresh()
                ->last_message_at
        );
    }

    public function test_livewire_shows_provider_error_without_losing_message(): void
    {
        [
            $user,
            $conversation,
        ] = $this->setupConversation();

        $fake = new FakeChatGateway;

        $fake->failWith(
            new AiGatewayException(
                'Simulated timeout.'
            )
        );

        $this->app->instance(
            ChatGateway::class,
            $fake
        );

        Livewire::actingAs($user)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $conversation->id
            )
            ->set(
                'message',
                'Mensaje durante error'
            )
            ->call('sendMessage')
            ->assertSet(
                'message',
                ''
            )
            ->assertHasErrors(
                'message'
            );

        $this->assertDatabaseHas(
            'messages',
            [
                'conversation_id' => $conversation->id,

                'role' => MessageRole::User->value,

                'content' => 'Mensaje durante error',
            ]
        );
    }
}
