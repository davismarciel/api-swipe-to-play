<?php

namespace Modules\Recommendation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Recommendation\Models\GameInteraction;
use Modules\User\Models\User;
use Modules\Game\Models\Game;

class GameInteractionFactory extends Factory
{
    protected $model = GameInteraction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'type' => $this->faker->randomElement(['view', 'like', 'dislike', 'favorite', 'skip']),
            'interaction_score' => function (array $attributes) {
                return match($attributes['type']) {
                    'like' => 10,
                    'favorite' => 15,
                    'view' => 1,
                    'dislike' => -5,
                    'skip' => -2,
                    default => 0,
                };
            },
            'interacted_at' => now(),
        ];
    }
}

