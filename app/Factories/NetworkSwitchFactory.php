<?php

namespace App\Factories;

use App\Models\NetworkSwitch;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class NetworkSwitchFactory extends Factory
{
    protected $model = NetworkSwitch::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'ip' => $this->faker->ipv4(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
