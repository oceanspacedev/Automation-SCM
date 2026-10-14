<?php

namespace Database\Factories;

use App\Area;
use App\Models\ArContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArContact>
 */
class ArContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area' => Area::Jabar->value,
            'region' => 'BIG '.strtoupper(fake()->unique()->city()),
            'name' => strtoupper(fake()->name()),
            'whatsapp' => '08'.fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function forArea(Area $area): static
    {
        return $this->state(fn () => ['area' => $area->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
