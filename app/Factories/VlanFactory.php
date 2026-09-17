<?php

namespace App\Factories;

use App\Models\Vlan;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class VlanFactory extends Factory
{
    protected $model = Vlan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'description' => $this->faker->text(),
            'vlan_id' => (int) $this->faker->unique()->regexify(FakerPatterns::VLAN_ID),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
