<?php

namespace App\Factories;

use App\Models\Container;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'type' => $this->faker->word(),
            'description' => $this->faker->text(),
            'icon_id' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
