<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'assigned_to' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_at' => now()->addDays(5),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'not_started',
        ];
    }

    public function assignedToMember(User $user): static
    {
        return $this->state([
            'assigned_to' => $user->getKey(),
        ])->afterMaking(function (Task $task) use ($user): void {
            EventMember::factory()->create([
                'event_id' => $task->event_id,
                'user_id' => $user->getKey(),
            ]);
        });
    }
}
