<?php

namespace Database\Seeders;

use App\Enums\CharacterMood;
use App\Models\Character;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $character = Character::query()->updateOrCreate(
                [
                    'slug' => 'default-companion',
                ],
                [
                    'name' => 'Default Companion',

                    'description' => 'Base development character for AI Companion Chatbot.',

                    'base_personality' => [
                        'traits' => [
                            'curious',
                            'calm',
                            'observant',
                            'friendly',
                        ],
                        'temperament' => 'balanced',
                    ],

                    'base_backstory' => 'This is the default development character. '
                        .'Its final identity and backstory will be defined later.',

                    'base_speaking_style' => [
                        'language' => 'es',
                        'tone' => 'natural',
                        'message_length' => 'medium',
                        'uses_actions' => true,
                        'uses_emojis' => false,
                    ],

                    'base_scenario' => 'The character is meeting the user for the first time.',

                    'system_rules' => implode("\n", [
                        'Maintain the defined character identity.',
                        'Do not invent memories that are not present in context.',
                        'Do not decide actions, thoughts, or feelings for the user.',
                        'Keep continuity with the supplied conversation context.',
                    ]),

                    'initial_message' => 'Hola. Parece que es la primera vez que hablamos. '
                        .'¿Cómo te gustaría que te llame?',

                    'avatar_path' => null,

                    'is_active' => true,
                ],
            );

            $character->expressions()->update([
                'is_default' => false,
            ]);

            $expressions = [
                [
                    'name' => CharacterMood::Neutral->value,
                    'description' => 'Expresión neutral y estado visual predeterminado.',
                    'is_default' => true,
                ],
                [
                    'name' => CharacterMood::Happy->value,
                    'description' => 'Expresión alegre y positiva.',
                    'is_default' => false,
                ],
                [
                    'name' => CharacterMood::Angry->value,
                    'description' => 'Expresión de enojo o molestia.',
                    'is_default' => false,
                ],
                [
                    'name' => CharacterMood::Embarrassed->value,
                    'description' => 'Expresión de vergüenza o timidez.',
                    'is_default' => false,
                ],
                [
                    'name' => CharacterMood::Sad->value,
                    'description' => 'Expresión triste o melancólica.',
                    'is_default' => false,
                ],
                [
                    'name' => CharacterMood::Surprised->value,
                    'description' => 'Expresión de sorpresa.',
                    'is_default' => false,
                ],
            ];

            foreach ($expressions as $expression) {
                $character->expressions()->updateOrCreate(
                    [
                        'name' => $expression['name'],
                    ],
                    [
                        'description' => $expression['description'],
                        'image_path' => null,
                        'is_default' => $expression['is_default'],
                    ],
                );
            }
        });
    }
}
