<?php

namespace App\Factories;

use App\Models\Annuaire;
use App\Models\ZoneAdmin;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AnnuaireFactory extends Factory
{
    protected $model = Annuaire::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::ORG_NAME),
            'description' => $this->faker->text(),
            'solution' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'zone_admin_id' => ZoneAdmin::factory(),
        ];
    }
}
