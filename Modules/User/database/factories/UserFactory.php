<?php

namespace Modules\User\Database\Factories;

use Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'google_id' => (string) $this->faker->unique()->numberBetween(100000000000000000000, 999999999999999999999),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'avatar' => $this->faker->imageUrl(200, 200),
            'provider' => 'google',
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

