<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Seller;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registered_name' => fake()->company(),
            'business_style' => null,
            'tin' => (string) fake()->numerify('#########'),
            'branch_code' => '00000',
            'address' => fake()->address(),
            'vat_status' => 'vat_registered',
            'bir_rdo_code' => null,
            'setup_completed_at' => now(),
        ];
    }
}
