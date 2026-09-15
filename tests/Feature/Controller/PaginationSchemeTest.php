<?php

use App\Models\Application;
use App\Models\Cartographer;
use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('pagination link stays https on native TLS termination without X-Forwarded-Proto', function () {
    Cache::forget('permissions_roles_map');
    Cache::forget('cartographers_last_update');

    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    URL::forceScheme('https');

    $admin = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($admin);

    $user = User::factory()->create();
    for ($i = 0; $i < 60; $i++) {
        $app = Application::factory()->create();
        Cartographer::create(['cartographiable_type' => Application::class, 'cartographiable_id' => $app->id, 'user_id' => $user->id]);
    }

    // Simulate a native Apache TLS termination: HTTPS=on, no X-Forwarded-Proto header.
    $response = $this->call('GET', 'https://mercator.local/admin/cartographers', [], [], [], ['HTTPS' => 'on']);

    $response->assertOk();
    $response->assertDontSee('http://mercator.local/admin/cartographers?page=2', false);
    $response->assertSee('https://mercator.local/admin/cartographers?page=2', false);
});
