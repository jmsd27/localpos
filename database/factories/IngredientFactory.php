<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'unit' => 'kg',
            'stock' => 0,
            'is_active' => true,
        ];
    }
}
