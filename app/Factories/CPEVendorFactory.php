<?php

namespace App\Factories;

use App\Models\CPEVendor;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;

class CPEVendorFactory extends Factory
{
    protected $model = CPEVendor::class;

    public function definition(): array
    {
        return [
            'part' => $this->faker->word(),
            'name' => $this->faker->regexify(FakerPatterns::ORG_NAME),
        ];
    }
}
