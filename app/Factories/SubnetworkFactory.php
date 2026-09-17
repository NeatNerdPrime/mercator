<?php

namespace App\Factories;

use App\Models\Gateway;
use App\Models\Network;
use App\Models\Subnetwork;
use App\Models\Vlan;
use App\Support\FakerPatterns;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SubnetworkFactory extends Factory
{
    protected $model = Subnetwork::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify(FakerPatterns::NETWORK_SEGMENT),
            'description' => $this->faker->text(),
            'address' => $this->faker->regexify(FakerPatterns::SUBNET_CIDR),
            'ip_allocation_type' => $this->faker->regexify(FakerPatterns::IP_ALLOCATION_TYPE),
            'responsible_exp' => $this->faker->name(),
            'dmz' => $this->faker->regexify(FakerPatterns::YES_NO_FR),
            'wifi' => $this->faker->regexify(FakerPatterns::YES_NO_FR),
            'zone' => $this->faker->regexify(FakerPatterns::SECURITY_ZONE),
            'default_gateway' => $this->faker->ipv4(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'gateway_id' => Gateway::factory(),
            'vlan_id' => Vlan::factory(),
            'network_id' => Network::factory(),
        ];
    }
}
