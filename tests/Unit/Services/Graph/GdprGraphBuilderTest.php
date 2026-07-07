<?php

use App\Models\Application;
use App\Models\DataProcessing;
use App\Models\MacroProcessus;
use App\Models\Process;
use App\Models\User;
use App\Services\Graph\GdprGraphBuilder;
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

test('buildDot draws the macroprocess-process-dataProcessing-application chain', function () {
    $macroProcess = MacroProcessus::factory()->create();
    $process = Process::factory()->create(['macroprocess_id' => $macroProcess->id]);
    $dataProcessing = DataProcessing::factory()->create();
    $process->dataProcesses()->attach($dataProcessing->id);
    $application = Application::factory()->create();
    $dataProcessing->applications()->attach($application->id);

    $builder = new GdprGraphBuilder;
    $dot = $builder->buildDot(
        MacroProcessus::all(),
        Process::with('dataProcesses')->get(),
        DataProcessing::with('applications')->get(),
        Application::all(),
    );

    expect($dot)
        ->toContain('MP'.$macroProcess->id.' -> P'.$process->id)
        ->toContain('P'.$process->id.' -> DP'.$dataProcessing->id)
        ->toContain('DP'.$dataProcessing->id.' -> APP'.$application->id)
        ->toContain('#'.$dataProcessing->getUID());
});
