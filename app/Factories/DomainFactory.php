<?php

namespace App\Factories;

use App\Models\Domain;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::DOMAIN_FQDN),
            'description' => $this->faker->text(),
            'domain_ctrl_cnt' => $this->faker->randomNumber(),
            'user_count' => $this->faker->randomNumber(),
            'machine_count' => $this->faker->randomNumber(),
            'relation_inter_domaine' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
