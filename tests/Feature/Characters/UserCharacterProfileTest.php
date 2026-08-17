<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCharacterProfileTest extends TestCase
{
    use RefreshDatabase;

    private function baseCharacter(): Character
    {
        $this->seed(CharacterSeeder::class);

        return Character::query()
            ->where('slug', 'default-companion')
            ->firstOrFail();
    }

    public function test_profile_starts_with_neutral_values(): void
    {
        $character = $this->baseCharacter();
        $user = User::factory()->create();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute($user, $character);

        $this->assertSame(
            CharacterMood::Neutral,
            $profile->current_mood
        );

        $this->assertSame(
            RelationshipStage::Strangers,
            $profile->relationship_stage
        );

        $this->assertSame('neutral', $profile->currentExpression->name);

        $this->assertSame(0, $profile->trust);
        $this->assertSame(0, $profile->affection);
        $this->assertSame(0, $profile->familiarity);
        $this->assertSame(0, $profile->tension);

        $this->assertNull($profile->custom_personality);
        $this->assertNull($profile->custom_speaking_style);
        $this->assertNull($profile->custom_scenario);
        $this->assertNull($profile->nickname_for_user);
        $this->assertNull($profile->nickname_for_character);
        $this->assertNull($profile->last_interaction_at);
    }

    public function test_profile_creation_action_is_idempotent(): void
    {
        $character = $this->baseCharacter();
        $user = User::factory()->create();

        $action = app(
            CreateUserCharacterProfileAction::class
        );

        $first = $action->execute($user, $character);
        $second = $action->execute($user, $character);

        $this->assertTrue($first->is($second));

        $this->assertSame(
            1,
            UserCharacterProfile::query()
                ->where('user_id', $user->id)
                ->where('character_id', $character->id)
                ->count()
        );
    }

    public function test_database_rejects_duplicate_profile(): void
    {
        $character = $this->baseCharacter();
        $user = User::factory()->create();

        app(CreateUserCharacterProfileAction::class)
            ->execute($user, $character);

        $this->expectException(QueryException::class);

        UserCharacterProfile::query()->create([
            'user_id' => $user->id,
            'character_id' => $character->id,
            'current_mood' => CharacterMood::Neutral,
            'current_expression_id' => $character
                ->defaultExpression()
                ->firstOrFail()
                ->id,
            'relationship_stage' => RelationshipStage::Strangers,
            'trust' => 0,
            'affection' => 0,
            'familiarity' => 0,
            'tension' => 0,
        ]);
    }

    public function test_profile_relationships_work(): void
    {
        $character = $this->baseCharacter();
        $user = User::factory()->create();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute($user, $character);

        $this->assertTrue(
            $profile->user->is($user)
        );

        $this->assertTrue(
            $profile->character->is($character)
        );

        $this->assertTrue(
            $profile->currentExpression->is(
                $character->defaultExpression
            )
        );

        $this->assertTrue(
            $user->characterProfiles->first()->is($profile)
        );

        $this->assertTrue(
            $character->userProfiles->first()->is($profile)
        );
    }

    public function test_first_chat_visit_creates_profile_without_duplicates(): void
    {
        $character = $this->baseCharacter();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get('/chat')
            ->assertOk();

        $this->assertDatabaseHas(
            'user_character_profiles',
            [
                'user_id' => $user->id,
                'character_id' => $character->id,
            ]
        );

        $this
            ->actingAs($user)
            ->get('/chat')
            ->assertOk();

        $this->assertSame(
            1,
            UserCharacterProfile::query()
                ->where('user_id', $user->id)
                ->where('character_id', $character->id)
                ->count()
        );
    }
}
