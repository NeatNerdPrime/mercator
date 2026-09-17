<?php

namespace App\Factories;

use App\Models\SecurityControl;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SecurityControlFactory extends Factory
{
    protected $model = SecurityControl::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::ORG_NAME),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
