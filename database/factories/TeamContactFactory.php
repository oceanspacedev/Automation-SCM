<?php

namespace Database\Factories;

use App\Models\TeamContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamContact>
 */
class TeamContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team' => TeamContact::TAX,
            'name' => fake()->name(),
            'whatsapp' => '08'.fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function scm(): static
    {
        return $this->state(fn () => ['team' => TeamContact::SCM]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
