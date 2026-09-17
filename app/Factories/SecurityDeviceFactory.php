<?php

namespace App\Factories;

use App\Models\SecurityDevice;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SecurityDeviceFactory extends Factory
{
    protected $model = SecurityDevice::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'description' => $this->faker->text(),
            'address_ip' => $this->faker->ipv4(),
            'vendor' => $this->faker->word(),
            'product' => $this->faker->word(),
            'version' => $this->faker->regexify(FakerPatterns::SEMVER),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
