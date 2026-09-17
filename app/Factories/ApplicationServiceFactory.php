<?php

namespace App\Factories;

use App\Models\ApplicationService;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ApplicationServiceFactory extends Factory
{
    protected $model = ApplicationService::class;

    public function definition(): array
    {
        return [
            'description' => $this->faker->text(),
            'exposition' => $this->faker->word(),
            'name' => $this->faker->regexify(FakerPatterns::ORG_NAME),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
