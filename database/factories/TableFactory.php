<?php

namespace Database\Factories;

use App\Models\Table;
use App\Models\TableArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $area = TableArea::factory()->create();

        return [
            'business_id' => $area->business_id,
            'branch_id' => $area->branch_id,
            'table_area_id' => $area->id,
            'name' => 'Mesa '.fake()->unique()->numberBetween(1, 200),
            'capacity' => 4,
            'status' => 'available',
        ];
    }
}
