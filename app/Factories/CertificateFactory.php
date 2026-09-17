<?php

namespace App\Factories;

use App\Models\Certificate;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::ORG_NAME),
            'type' => $this->faker->word(),
            'description' => $this->faker->text(),
            'start_validity' => Carbon::now(),
            'end_validity' => Carbon::now(),
            'last_notification' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'status' => $this->faker->randomNumber(),
        ];
    }
}
