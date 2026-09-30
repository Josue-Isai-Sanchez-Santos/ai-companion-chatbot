<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Ai\Relationship\RelationshipChange;
use App\Ai\Relationship\RelationshipUpdater;
use App\Ai\State\CharacterStateResolver;
use App\Ai\State\ExpressionResolver;
use App\Enums\CharacterMood;
use App\Enums\MessageRole;
use App\Models\Character;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterStateResolverTest extends TestCase
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
    }

    private function profile(): UserCharacterProfile
    {
        $user =
            User::factory()
                ->create();

        return app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $this->character
        );
    }

    /**
     * @return RelationshipEvent
     */
    private function relationshipEvent(
        UserCharacterProfile $profile,
        int $trust = 0,
        int $affection = 0,
        int $familiarity = 0,
        int $tension = 0
    ): RelationshipEvent {
        $conversation =
            $profile
                ->conversations()
                ->create([
                    'title' =>
                        'State resolver test',
                ]);

        $userMessage =
            $conversation
                ->messages()
                ->create([
                    'role' =>
                        MessageRole::User,

                    'content' =>
                        'Mensaje del usuario.',

                    'status' =>
                        Message::STATUS_COMPLETED,
                ]);

        $assistant =
            $conversation
                ->messages()
                ->create([
                    'parent_message_id' =>
                        $userMessage->id,

                    'role' =>
                        MessageRole::Assistant,

                    'content' =>
                        'Respuesta del personaje.',

                    'status' =>
                        Message::STATUS_COMPLETED,
                ]);

        $event = app(
            RelationshipUpdater::class
        )->apply(
            $profile,
            $conversation,
            $userMessage,
            $assistant,
            new RelationshipChange(
                significant: true,
                eventSummary:
                    'Evento significativo para prueba.',
                trustDelta:
                    $trust,
                affectionDelta:
                    $affection,
                familiarityDelta:
                    $familiarity,
                tensionDelta:
                    $tension,
            )
        );

        $this->assertNotNull(
            $event
        );

        return $event;
    }

    public function test_expression_resolver_returns_expression_for_character(): void
    {
        $expression = app(
            ExpressionResolver::class
        )->resolve(
            $this->character,
            CharacterMood::Happy
        );

        $this->assertSame(
            'happy',
            $expression->name
        );

        $this->assertSame(
            $this->character->id,
            $expression->character_id
        );
    }

    public function test_expression_resolver_falls_back_to_neutral_default(): void
    {
        $character =
            Character::factory()
                ->create();

        $neutral =
            $character
                ->expressions()
                ->create([
                    'name' =>
                        CharacterMood::Neutral->value,

                    'description' =>
                        'Neutral.',

                    'is_default' =>
                        true,
                ]);

        $resolved = app(
            ExpressionResolver::class
        )->resolve(
            $character,
            CharacterMood::Happy
        );

        $this->assertSame(
            $neutral->id,
            $resolved->id
        );

        $this->assertSame(
            'neutral',
            $resolved->name
        );
    }

    public function test_no_event_resolves_to_neutral(): void
    {
        $this->assertSame(
            CharacterMood::Neutral,
            app(
                CharacterStateResolver::class
            )->determineMood(
                null
            )
        );
    }

    public function test_weak_relationship_signal_stays_neutral(): void
    {
        $profile =
            $this->profile();

        $event =
            $this->relationshipEvent(
                $profile,
                trust: 1
            );

        $this->assertSame(
            CharacterMood::Neutral,
            app(
                CharacterStateResolver::class
            )->determineMood(
                $event
            )
        );
    }

    public function test_positive_trust_resolves_to_happy(): void
    {
        $profile =
            $this->profile();

        $event =
            $this->relationshipEvent(
                $profile,
                trust: 3
            );

        $this->assertSame(
            CharacterMood::Happy,
            app(
                CharacterStateResolver::class
            )->determineMood(
                $event
            )
        );
    }

    public function test_increasing_tension_resolves_to_angry(): void
    {
        $profile =
            $this->profile();

        $event =
            $this->relationshipEvent(
                $profile,
                tension: 3
            );

        $this->assertSame(
            CharacterMood::Angry,
            app(
                CharacterStateResolver::class
            )->determineMood(
                $event
            )
        );
    }

    public function test_lost_trust_resolves_to_sad(): void
    {
        $profile =
            $this->profile();

        /*
         * Relationship metrics cannot go below zero,
         * so give trust enough room for a negative
         * applied delta.
         */
        $profile->update([
            'trust' => 10,
        ]);

        $event =
            $this->relationshipEvent(
                $profile,
                trust: -3
            );

        $this->assertSame(
            CharacterMood::Sad,
            app(
                CharacterStateResolver::class
            )->determineMood(
                $event
            )
        );
    }

    public function test_growing_familiarity_resolves_to_curious(): void
    {
        $profile =
            $this->profile();

        $event =
            $this->relationshipEvent(
                $profile,
                familiarity: 3
            );

        $this->assertSame(
            CharacterMood::Curious,
            app(
                CharacterStateResolver::class
            )->determineMood(
                $event
            )
        );
    }

    public function test_apply_updates_mood_and_matching_expression(): void
    {
        $profile =
            $this->profile();

        $event =
            $this->relationshipEvent(
                $profile,
                affection: 3
            );

        $resolved = app(
            CharacterStateResolver::class
        )->apply(
            $profile->fresh(),
            $event
        );

        $this->assertSame(
            CharacterMood::Happy,
            $resolved->current_mood
        );

        $this->assertSame(
            'happy',
            $resolved
                ->currentExpression
                ->name
        );
    }

    public function test_reset_returns_mood_and_expression_to_neutral(): void
    {
        $profile =
            $this->profile();

        $happy =
            $this->character
                ->expressions()
                ->where(
                    'name',
                    'happy'
                )
                ->firstOrFail();

        $profile->update([
            'current_mood' =>
                CharacterMood::Happy,

            'current_expression_id' =>
                $happy->id,
        ]);

        $resolved = app(
            CharacterStateResolver::class
        )->reset(
            $profile->fresh()
        );

        $this->assertSame(
            CharacterMood::Neutral,
            $resolved->current_mood
        );

        $this->assertSame(
            'neutral',
            $resolved
                ->currentExpression
                ->name
        );
    }
}
