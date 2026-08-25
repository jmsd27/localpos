<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Terminal>
 */
class TerminalFactory extends Factory
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
            'name' => 'Caja '.fake()->numberBetween(1, 20),
            'code' => fake()->unique()->slug(2),
            'is_active' => true,
        ];
    }
}
