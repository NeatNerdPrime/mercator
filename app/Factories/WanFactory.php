<?php

namespace App\Factories;

use App\Models\Wan;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class WanFactory extends Factory
{
    protected $model = Wan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
