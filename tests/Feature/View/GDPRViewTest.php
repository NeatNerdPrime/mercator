<?php

use App\Models\DataProcessing;
use App\Models\MacroProcessus;
use App\Models\Process;
use App\Models\User;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed base permissions/roles and users as in other feature tests
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    // Login as an admin (id=1 seeded by UsersTableSeeder)
    $this->user = User::query()->where('login', 'admin@admin.com')->first();
    $this->actingAs($this->user);
});

describe('GDPR View', function () {
    test('can display gdpr view page', function () {
        $response = $this->get(route('admin.report.gdpr'));

        $response->assertOk();
        $response->assertViewIs('admin.reports.gdpr');
        $response->assertViewHasAll(['all_macroprocess', 'macroProcessuses', 'processes', 'dataProcessings', 'applications']);
    });

    test('can display filtered view for a selected macroprocess with a data processing', function () {
        $macroProcess = MacroProcessus::factory()->create();
        $process = Process::factory()->create(['macroprocess_id' => $macroProcess->id]);
        $dataProcessing = DataProcessing::factory()->create();
        $process->dataProcesses()->attach($dataProcessing->id);

        $response = $this->get(route('admin.report.gdpr', ['macroprocess' => $macroProcess->id]));

        $response->assertOk();
        $response->assertViewHas('dataProcessings', function ($dataProcessings) use ($dataProcessing) {
            return $dataProcessings->contains('id', $dataProcessing->id);
        });
    });

    test('denies access without permission', function () {
        // New user without the reports_access permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.report.gdpr'));

        $response->assertForbidden();
    });
});
