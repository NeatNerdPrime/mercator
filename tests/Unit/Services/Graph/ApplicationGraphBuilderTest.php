<?php

use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Database;
use App\Models\User;
use App\Services\Graph\ApplicationGraphBuilder;
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

test('buildDot draws the block-application-service-module-database chain', function () {
    $block = ApplicationBlock::factory()->create();
    $application = Application::factory()->create(['application_block_id' => $block->id]);
    $service = ApplicationService::factory()->create();
    $application->services()->attach($service->id);
    $module = ApplicationModule::factory()->create();
    $service->modules()->attach($module->id);
    $database = Database::factory()->create();
    $application->databases()->attach($database->id);

    $builder = new ApplicationGraphBuilder;
    $dot = $builder->buildDot(
        ApplicationBlock::all(),
        Application::with('services', 'databases')->get(),
        ApplicationService::with('modules')->get(),
        ApplicationModule::all(),
        Database::all(),
    );

    expect($dot)
        ->toContain('AB'.$block->id.' -> A'.$application->id)
        ->toContain('A'.$application->id.' -> AS'.$service->id)
        ->toContain('A'.$application->id.' -> DB'.$database->id)
        ->toContain('AS'.$service->id.' -> M'.$module->id);
});
