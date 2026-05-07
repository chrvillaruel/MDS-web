<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoicing\Domain\Buyer;

/**
 * @extends Factory<Buyer>
 */
class BuyerFactory extends Factory
{
    protected $model = Buyer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tin' => fake()->numerify('#########'),
            'registered_name' => fake()->name(),
            'business_style' => null,
            'address' => fake()->address(),
            'email' => fake()->safeEmail(),
        ];
    }
}
