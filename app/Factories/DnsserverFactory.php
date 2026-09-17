<?php

namespace App\Factories;

use App\Models\Dnsserver;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class DnsserverFactory extends Factory
{
    protected $model = Dnsserver::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::HOSTNAME),
            'description' => $this->faker->text(),
            'address_ip' => $this->faker->ipv4(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
