<?php

namespace Modules\Game\Database\Factories;

use Modules\Game\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        $slug = \Illuminate\Support\Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1000, 9999);

        return [
            'steam_id' => (string) $this->faker->unique()->numberBetween(100000, 999999999),
            'name' => $name,
            'type' => 'game',
            'slug' => $slug,
            'short_description' => $this->faker->sentence(10),
            'required_age' => $this->faker->randomElement([0, 7, 12, 16, 18]),
            'is_free' => $this->faker->boolean(30),
            'have_dlc' => $this->faker->boolean(40),
            'icon' => $this->faker->imageUrl(184, 69),
            'cover' => $this->faker->imageUrl(600, 900),
            'supported_languages' => $this->faker->randomElements(
                ['English', 'Portuguese', 'Spanish', 'French', 'German', 'Italian', 'Russian'],
                $this->faker->numberBetween(1, 5)
            ),
            'release_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'coming_soon' => false,
            'recommendations' => $this->faker->numberBetween(0, 50000),
            'achievements_count' => $this->faker->numberBetween(0, 100),
            'achievements_highlighted' => $this->faker->optional()->randomElements(
                ['First Achievement', 'Master Achievement', 'Speed Run'],
                $this->faker->numberBetween(0, 3)
            ),
            'positive_reviews' => $this->faker->numberBetween(0, 100000),
            'negative_reviews' => $this->faker->numberBetween(0, 50000),
            'total_reviews' => function (array $attributes) {
                return ($attributes['positive_reviews'] ?? 0) + ($attributes['negative_reviews'] ?? 0);
            },
            'positive_ratio' => function (array $attributes) {
                $total = ($attributes['positive_reviews'] ?? 0) + ($attributes['negative_reviews'] ?? 0);
                if ($total === 0) {
                    return null;
                }
                return round(($attributes['positive_reviews'] ?? 0) / $total * 100, 3);
            },
            'content_descriptors' => $this->faker->optional()->randomElements(
                ['Violence', 'Gore', 'Sexual Content', 'Strong Language', 'Drug Reference'],
                $this->faker->numberBetween(0, 3)
            ),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => false,
        ]);
    }
}

