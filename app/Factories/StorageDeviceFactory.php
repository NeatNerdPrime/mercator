<?php

namespace App\Factories;

use App\Models\Bay;
use App\Models\Building;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class StorageDeviceFactory extends Factory
{
    protected $model = StorageDevice::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'type' => $this->faker->word(),
            'description' => $this->faker->text(),
            'vendor' => $this->faker->word(),
            'product' => $this->faker->word(),
            'version' => $this->faker->regexify(FakerPatterns::SEMVER),
            'address_ip' => $this->faker->ipv4(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'site_id' => Site::factory(),
            'building_id' => Building::factory(),
            'bay_id' => Bay::factory(),
        ];
    }
}
