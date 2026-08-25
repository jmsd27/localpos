<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory()->create();

        return [
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'name' => fake()->unique()->randomElement(['Cocina', 'Barra', 'Parrilla', 'Cafetería', 'Postres']),
            'code' => fake()->unique()->slug(1),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
