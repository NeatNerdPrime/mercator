<?php

namespace App\Factories;

use App\Models\Network;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class NetworkFactory extends Factory
{
    protected $model = Network::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'description' => $this->faker->text(),
            'protocol_type' => $this->faker->word(),
            'responsible' => $this->faker->word(),
            'responsible_sec' => $this->faker->word(),
            'security_need_c' => $this->faker->optional()->numberBetween(0, 4),
            'security_need_i' => $this->faker->optional()->numberBetween(0, 4),
            'security_need_a' => $this->faker->optional()->numberBetween(0, 4),
            'security_need_t' => $this->faker->optional()->numberBetween(0, 4),
            'security_need_auth' => $this->faker->optional()->numberBetween(0, 4),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
