<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Character>
 */
class CharacterFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->firstName().' Companion';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),

            'description' => fake()->sentence(),

            'base_personality' => [
                'traits' => [
                    'curious',
                    'calm',
                    'observant',
                ],
                'temperament' => 'balanced',
            ],

            'base_backstory' => fake()->paragraph(),

            'base_speaking_style' => [
                'language' => 'es',
                'tone' => 'natural',
                'message_length' => 'medium',
                'uses_actions' => true,
                'uses_emojis' => false,
            ],

            'base_scenario' => fake()->paragraph(),

            'system_rules' => implode("\n", [
                'Maintain the character identity.',
                'Do not invent memories not present in context.',
                'Do not decide actions for the user.',
            ]),

            'initial_message' => fake()->sentence(),

            'avatar_path' => null,

            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
