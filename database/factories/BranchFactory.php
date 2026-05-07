<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Domain\Branch;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('BR??')),
            'name' => fake()->city().' Branch',
            'address' => fake()->address(),
            'current_serial' => 0,
            'reset_counter' => 0,
            'max_serial' => 999_999,
        ];
    }
}
