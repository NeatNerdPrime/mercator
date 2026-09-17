<?php

namespace App\Factories;

use App\Models\Lan;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class LanFactory extends Factory
{
    protected $model = Lan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
