<?php

namespace App\Factories;

use App\Models\Man;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ManFactory extends Factory
{
    protected $model = Man::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
