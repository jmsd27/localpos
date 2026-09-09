<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'code' => strtoupper(Str::random(6)),
            'name' => fake()->words(2, true),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_uses' => null,
            'used_count' => 0,
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }

    public function amount(float $value): static
    {
        return $this->state(fn () => ['discount_type' => 'amount', 'discount_value' => $value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['max_uses' => 1, 'used_count' => 1]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }
}
