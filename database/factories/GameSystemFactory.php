<?php

namespace Database\Factories;

use App\Models\GameSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameSystem>
 */
class GameSystemFactory extends Factory
{
    protected $model = GameSystem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'user_uuid' => null, // or create a user if needed
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
