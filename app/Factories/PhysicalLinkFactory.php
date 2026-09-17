<?php

namespace App\Factories;

use App\Models\PhysicalLink;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PhysicalLinkFactory extends Factory
{
    protected $model = PhysicalLink::class;

    public function definition(): array
    {
        return [
            'src_port' => $this->faker->regexify(FakerPatterns::PORT),
            'dest_port' => $this->faker->regexify(FakerPatterns::PORT),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
