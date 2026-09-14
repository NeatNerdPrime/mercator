<?php

namespace App\Factories;

use App\Models\Perimetre;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerimetreFactory extends Factory
{
    protected $model = Perimetre::class;

    public function definition(): array
    {
        return [
            'nom' => $this->faker->unique()->word(),
        ];
    }
}
