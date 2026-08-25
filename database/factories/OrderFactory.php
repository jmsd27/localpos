<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'user_id' => User::factory()->create(['branch_id' => $branch->id])->id,
            'folio' => 'VENTA-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'order_type' => 'mostrador',
            'status' => 'completed',
            'subtotal' => 100,
            'total' => 100,
            'completed_at' => now(),
        ];
    }
}
