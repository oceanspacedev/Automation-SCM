<?php

namespace Database\Factories;

use App\Area;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
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
            'name' => 'BIG '.strtoupper(fake()->unique()->city()),
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
