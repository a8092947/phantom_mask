<?php

namespace Database\Factories;

use App\Models\Mask;
use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaskFactory extends Factory
{
    protected $model = Mask::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'price' => $this->faker->numberBetween(10, 100),
            'stock' => $this->faker->numberBetween(0, 1000),
            'pharmacy_id' => Pharmacy::factory(),
        ];
    }
} 