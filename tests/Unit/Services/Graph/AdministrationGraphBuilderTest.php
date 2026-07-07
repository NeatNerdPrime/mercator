<?php

use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Domain;
use App\Models\ForestAd;
use App\Models\User;
use App\Models\ZoneAdmin;
use App\Services\Graph\AdministrationGraphBuilder;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->actingAs(User::query()->where('login', 'admin@admin.com')->first());
});

test('buildDot draws the zone-annuaire-forest-domain-adminUser chain', function () {
    $zone = ZoneAdmin::factory()->create();
    $annuaire = Annuaire::factory()->create(['zone_admin_id' => $zone->id]);
    $forest = ForestAd::factory()->create(['zone_admin_id' => $zone->id]);
    $domain = Domain::factory()->create();
    $forest->domains()->attach($domain->id);
    $adminUser = AdminUser::factory()->create(['domain_id' => $domain->id, 'user_id' => 'jdoe']);

    $builder = new AdministrationGraphBuilder;
    $dot = $builder->buildDot(
        ZoneAdmin::with('annuaires', 'forestAds')->get(),
        Annuaire::all(),
        ForestAd::with('domains')->get(),
        Domain::all(),
        AdminUser::all(),
    );

    expect($dot)
        ->toContain('Z'.$zone->id.' -> A'.$annuaire->id)
        ->toContain('Z'.$zone->id.' -> F'.$forest->id)
        ->toContain('F'.$forest->id.' -> D'.$domain->id)
        ->toContain('D'.$domain->id.' -> U'.$adminUser->id)
        ->toContain('U'.$adminUser->id.' [shape=none label=<')
        ->toContain('jdoe');
});
