<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['academic', 'sports', 'arts', 'community']),
            'location' => fake()->city(),
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(3),
            'status' => 'draft',
        ];
    }
}
