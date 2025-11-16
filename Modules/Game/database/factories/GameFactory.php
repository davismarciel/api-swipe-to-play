<?php

namespace Modules\Game\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Game\Models\Game;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'steam_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'name' => $name,
            'type' => 'game',
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'short_description' => fake()->sentence(),
            'required_age' => fake()->randomElement([0, 7, 12, 16, 18]),
            'is_free' => fake()->boolean(30),
            'have_dlc' => fake()->boolean(40),
            'icon' => fake()->imageUrl(),
            'cover' => fake()->imageUrl(),
            'supported_languages' => ['en', 'pt', 'es'],
            'release_date' => fake()->date(),
            'coming_soon' => false,
            'recommendations' => fake()->numberBetween(0, 10000),
            'achievements_count' => fake()->numberBetween(0, 100),
            'positive_reviews' => fake()->numberBetween(0, 50000),
            'negative_reviews' => fake()->numberBetween(0, 10000),
            'total_reviews' => function (array $attributes) {
                return $attributes['positive_reviews'] + $attributes['negative_reviews'];
            },
            'positive_ratio' => function (array $attributes) {
                if ($attributes['total_reviews'] === 0) {
                    return null;
                }
                return round($attributes['positive_reviews'] / $attributes['total_reviews'], 3);
            },
            'content_descriptors' => null,
            'is_active' => true,
        ];
    }
}

