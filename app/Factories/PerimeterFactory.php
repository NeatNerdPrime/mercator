<?php

namespace App\Factories;

use App\Models\Perimeter;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerimeterFactory extends Factory
{
    protected $model = Perimeter::class;

    public function definition(): array
    {
        return [
            'nom' => $this->faker->unique()->word(),
        ];
    }
}
