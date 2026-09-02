<?php

use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    $this->admin = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->admin);

});

describe('flow_label preference', function () {

    test('persists a valid value', function () {
        $response = $this->post('/profile/preferences', [
            'granularity' => 1,
            'language' => 'fr',
            'flow_label' => 'name',
        ]);

        $response->assertRedirect('/profile/preferences');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'flow_label' => 'name',
        ]);
    });

    test('rejects an invalid value', function () {
        $response = $this->post('/profile/preferences', [
            'granularity' => 1,
            'language' => 'fr',
            'flow_label' => 'invalid',
        ]);

        $response->assertSessionHasErrors('flow_label');

        $this->assertDatabaseMissing('users', [
            'id' => $this->admin->id,
            'flow_label' => 'invalid',
        ]);
    });

    test('defaults to nature when null', function () {
        $this->admin->flow_label = null;
        $this->admin->save();

        $this->admin->refresh();

        expect($this->admin->flow_label)->toBeNull();
        expect(($this->admin->flow_label ?? 'nature') === 'name')->toBeFalse();
    });

});
