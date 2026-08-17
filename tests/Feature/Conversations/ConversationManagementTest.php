<?php

namespace Tests\Feature\Conversations;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Conversations\CreateConversationAction;
use App\Livewire\Chat\ChatPage;
use App\Models\Character;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CharacterSeeder::class);

        $this->character = Character::query()
            ->where('slug', 'default-companion')
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

    public function test_user_can_create_multiple_conversations(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChatPage::class)
            ->call('createConversation')
            ->call('createConversation');

        $profile = $this->profileFor($user);

        $this->assertSame(
            2,
            $profile->conversations()->count()
        );
    }

    public function test_user_can_select_own_conversation(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $first = $profile
            ->conversations()
            ->create([
                'title' => 'Primera conversación',
            ]);

        $profile
            ->conversations()
            ->create([
                'title' => 'Segunda conversación',
            ]);

        Livewire::actingAs($user)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $first->id
            )
            ->assertSet(
                'conversationId',
                $first->id
            )
            ->assertSee(
                'Primera conversación'
            );
    }

    public function test_user_cannot_select_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = $this->profileFor($owner);

        $conversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'Conversación privada',
            ]);

        Livewire::actingAs($intruder)
            ->test(ChatPage::class)
            ->call(
                'selectConversation',
                $conversation->id
            )
            ->assertForbidden();
    }

    public function test_user_can_rename_own_conversation(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Título anterior',
            ]);

        Livewire::actingAs($user)
            ->test(ChatPage::class)
            ->call(
                'startRenamingConversation',
                $conversation->id
            )
            ->set(
                'renamingTitle',
                'Título nuevo'
            )
            ->call('renameConversation');

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversation->id,
                'title' => 'Título nuevo',
            ]
        );
    }

    public function test_user_cannot_rename_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = $this->profileFor($owner);

        $conversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'No tocar',
            ]);

        Livewire::actingAs($intruder)
            ->test(ChatPage::class)
            ->call(
                'startRenamingConversation',
                $conversation->id
            )
            ->assertForbidden();
    }

    public function test_user_can_delete_own_conversation(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Eliminarme',
            ]);

        Livewire::actingAs($user)
            ->test(ChatPage::class)
            ->call(
                'deleteConversation',
                $conversation->id
            );

        $this->assertDatabaseMissing(
            'conversations',
            [
                'id' => $conversation->id,
            ]
        );
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = $this->profileFor($owner);

        $conversation = $ownerProfile
            ->conversations()
            ->create([
                'title' => 'Privada',
            ]);

        Livewire::actingAs($intruder)
            ->test(ChatPage::class)
            ->call(
                'deleteConversation',
                $conversation->id
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversation->id,
            ]
        );
    }

    public function test_policy_allows_only_profile_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $profile = $this->profileFor($owner);

        $conversation = app(
            CreateConversationAction::class
        )->execute(
            $owner,
            $profile,
            'Autorizada'
        );

        $this->assertTrue(
            Gate::forUser($owner)
                ->allows(
                    'view',
                    $conversation
                )
        );

        $this->assertTrue(
            Gate::forUser($owner)
                ->allows(
                    'update',
                    $conversation
                )
        );

        $this->assertTrue(
            Gate::forUser($owner)
                ->allows(
                    'delete',
                    $conversation
                )
        );

        $this->assertFalse(
            Gate::forUser($intruder)
                ->allows(
                    'view',
                    $conversation
                )
        );

        $this->assertFalse(
            Gate::forUser($intruder)
                ->allows(
                    'update',
                    $conversation
                )
        );

        $this->assertFalse(
            Gate::forUser($intruder)
                ->allows(
                    'delete',
                    $conversation
                )
        );
    }

    public function test_deleting_profile_deletes_its_conversations(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $conversation = $profile
            ->conversations()
            ->create([
                'title' => 'Será eliminada',
            ]);

        $profile->delete();

        $this->assertDatabaseMissing(
            'conversations',
            [
                'id' => $conversation->id,
            ]
        );
    }

    public function test_user_cannot_create_conversation_for_another_users_profile(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerProfile = $this->profileFor($owner);

        $this->expectException(
            AuthorizationException::class
        );

        app(CreateConversationAction::class)
            ->execute(
                $intruder,
                $ownerProfile,
                'No autorizada'
            );
    }
}
