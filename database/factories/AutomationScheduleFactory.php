<?php

namespace Database\Factories;

use App\Models\AutomationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationSchedule>
 */
class AutomationScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'is_active' => true,
            'mode' => AutomationSchedule::MODE_INTERVAL,
            'interval_minutes' => 15,
            'times' => [],
            'days' => [],
            'options' => [],
        ];
    }

    /**
     * Runs at the given clock times (e.g. "08:00").
     *
     * @param  list<string>  $times
     */
    public function atTimes(array $times): static
    {
        return $this->state(fn () => ['mode' => AutomationSchedule::MODE_TIMES, 'times' => $times, 'interval_minutes' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
