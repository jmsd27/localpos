<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\TableArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableArea>
 */
class TableAreaFactory extends Factory
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
            'name' => 'Salón '.fake()->unique()->word(),
            'sort_order' => 0,
        ];
    }
}
