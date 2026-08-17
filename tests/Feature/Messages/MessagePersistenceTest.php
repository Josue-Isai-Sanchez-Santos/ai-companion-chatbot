<?php

namespace Tests\Feature\Messages;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Messages\SendMessageAction;
use App\Enums\MessageRole;
use App\Livewire\Chat\ChatPage;
use App\Livewire\Chat\MessageList;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessagePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private User $user;

    private UserCharacterProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CharacterSeeder::class);

        $this->character = Character::query()
            ->where('slug', 'default-companion')
            ->firstOrFail();

        $this->user = User::factory()->create();

        $this->profile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $this->user,
            $this->character
        );
    }

    private function conversation(
        string $title = 'Conversación de prueba'
    ): Conversation {
        return $this->profile
            ->conversations()
            ->create([
                'title' => $title,
            ]);
    }

    public function test_user_can_send_message_and_simulated_response_is_created(): void
    {
        $conversation = $this->conversation();

        Livewire::actingAs($this->user)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $conversation->id
            )
            ->set(
                'message',
                'Hola desde la interfaz'
            )
            ->call('sendMessage')
            ->assertSet('message', '');

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get();

        $this->assertCount(2, $messages);

        $this->assertSame(
            MessageRole::User,
            $messages[0]->role
        );

        $this->assertSame(
            'Hola desde la interfaz',
            $messages[0]->content
        );

        $this->assertNull(
            $messages[0]->parent_message_id
        );

        $this->assertSame(
            MessageRole::Assistant,
            $messages[1]->role
        );

        $this->assertSame(
            $messages[0]->id,
            $messages[1]->parent_message_id
        );

        $this->assertTrue(
            $messages[1]->metadata['simulated']
        );

        $this->assertNotNull(
            $conversation->fresh()->last_message_at
        );
    }

    public function test_messages_survive_a_fresh_component_load(): void
    {
        $conversation = $this->conversation();

        app(SendMessageAction::class)
            ->execute(
                $this->user,
                $conversation,
                'Mensaje persistente'
            );

        Livewire::actingAs($this->user)
            ->test(
                MessageList::class,
                [
                    'conversationId' => $conversation->id,
                ]
            )
            ->assertSee('Mensaje persistente')
            ->assertSee(
                'Respuesta simulada: recibí tu mensaje correctamente.'
            );
    }

    public function test_messages_are_returned_in_chronological_order(): void
    {
        $conversation = $this->conversation();

        $action = app(
            SendMessageAction::class
        );

        $action->execute(
            $this->user,
            $conversation,
            'Primer mensaje'
        );

        $action->execute(
            $this->user,
            $conversation,
            'Segundo mensaje'
        );

        $contents = $conversation
            ->messages()
            ->chronological()
            ->pluck('content')
            ->all();

        $this->assertSame(
            [
                'Primer mensaje',
                'Respuesta simulada: recibí tu mensaje correctamente.',
                'Segundo mensaje',
                'Respuesta simulada: recibí tu mensaje correctamente.',
            ],
            $contents
        );
    }

    public function test_conversations_do_not_mix_messages(): void
    {
        $first = $this->conversation('Primera');
        $second = $this->conversation('Segunda');

        $action = app(
            SendMessageAction::class
        );

        $action->execute(
            $this->user,
            $first,
            'Mensaje exclusivo A'
        );

        $action->execute(
            $this->user,
            $second,
            'Mensaje exclusivo B'
        );

        Livewire::actingAs($this->user)
            ->test(
                MessageList::class,
                [
                    'conversationId' => $first->id,
                ]
            )
            ->assertSee('Mensaje exclusivo A')
            ->assertDontSee('Mensaje exclusivo B');
    }

    public function test_user_cannot_render_another_users_messages(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $owner,
            $this->character
        );

        $conversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'Privada',
            ]);

        app(SendMessageAction::class)
            ->execute(
                $owner,
                $conversation,
                'Mensaje privado'
            );

        Livewire::actingAs($intruder)
            ->test(
                MessageList::class,
                [
                    'conversationId' => $conversation->id,
                ]
            )
            ->assertForbidden();
    }

    public function test_user_cannot_send_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $owner,
            $this->character
        );

        $conversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'Privada',
            ]);

        $this->expectException(
            AuthorizationException::class
        );

        app(SendMessageAction::class)
            ->execute(
                $intruder,
                $conversation,
                'Intento no autorizado'
            );
    }

    public function test_empty_message_is_rejected(): void
    {
        $conversation = $this->conversation();

        Livewire::actingAs($this->user)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $conversation->id
            )
            ->set('message', '   ')
            ->call('sendMessage')
            ->assertHasErrors([
                'message' => 'required',
            ]);

        $this->assertSame(
            0,
            $conversation->messages()->count()
        );
    }

    public function test_message_over_maximum_length_is_rejected(): void
    {
        $conversation = $this->conversation();

        $content = str_repeat(
            'a',
            config('chatbot.message_max_length') + 1
        );

        Livewire::actingAs($this->user)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $conversation->id
            )
            ->set(
                'message',
                $content
            )
            ->call('sendMessage')
            ->assertHasErrors([
                'message' => 'max',
            ]);

        $this->assertSame(
            0,
            $conversation->messages()->count()
        );
    }

    public function test_deleting_conversation_deletes_its_messages(): void
    {
        $conversation = $this->conversation();

        app(SendMessageAction::class)
            ->execute(
                $this->user,
                $conversation,
                'Mensaje temporal'
            );

        $messageIds = $conversation
            ->messages()
            ->pluck('id');

        $conversation->delete();

        foreach ($messageIds as $messageId) {
            $this->assertDatabaseMissing(
                'messages',
                [
                    'id' => $messageId,
                ]
            );
        }
    }

    public function test_multiple_turns_form_a_continuous_parent_chain(): void
    {
        $conversation = $this->conversation();

        $action = app(
            SendMessageAction::class
        );

        $action->execute(
            $this->user,
            $conversation,
            'Primer turno'
        );

        $action->execute(
            $this->user,
            $conversation,
            'Segundo turno'
        );

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get();

        $this->assertCount(
            4,
            $messages
        );

        $this->assertNull(
            $messages[0]->parent_message_id
        );

        $this->assertSame(
            $messages[0]->id,
            $messages[1]->parent_message_id
        );

        $this->assertSame(
            $messages[1]->id,
            $messages[2]->parent_message_id
        );

        $this->assertSame(
            $messages[2]->id,
            $messages[3]->parent_message_id
        );
    }
}
