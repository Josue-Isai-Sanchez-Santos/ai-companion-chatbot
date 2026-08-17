<?php

namespace App\Actions\Characters;

use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\User;
use App\Models\UserCharacterProfile;

class CreateUserCharacterProfileAction
{
    public function execute(
        User $user,
        Character $character
    ): UserCharacterProfile {
        $defaultExpression = $character
            ->defaultExpression()
            ->firstOrFail();

        return UserCharacterProfile::query()->createOrFirst(
            [
                'user_id' => $user->id,
                'character_id' => $character->id,
            ],
            [
                'custom_personality' => null,
                'custom_speaking_style' => null,
                'custom_scenario' => null,

                'nickname_for_user' => null,
                'nickname_for_character' => null,

                'current_mood' => CharacterMood::from(
                    config('chatbot.default_mood')
                ),

                'current_expression_id' => $defaultExpression->id,

                'relationship_stage' => RelationshipStage::from(
                    config('relationship.default_stage')
                ),

                'trust' => config(
                    'relationship.metrics.trust.default'
                ),

                'affection' => config(
                    'relationship.metrics.affection.default'
                ),

                'familiarity' => config(
                    'relationship.metrics.familiarity.default'
                ),

                'tension' => config(
                    'relationship.metrics.tension.default'
                ),

                'last_interaction_at' => null,
            ],
        );
    }
}
