<?php

namespace App\Factories;

use App\Models\Bay;
use App\Models\Building;
use App\Models\PhysicalRouter;
use App\Models\Site;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PhysicalRouterFactory extends Factory
{
    protected $model = PhysicalRouter::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'description' => $this->faker->text(),
            'vendor' => $this->faker->word(),
            'product' => $this->faker->word(),
            'version' => $this->faker->regexify(FakerPatterns::SEMVER),
            'type' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'site_id' => Site::factory(),
            'building_id' => Building::factory(),
            'bay_id' => Bay::factory(),
        ];
    }
}
