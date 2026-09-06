<?php

use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationFlow;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Database;
use App\Models\Entity;
use App\Models\User;
use App\Services\Cairn\CairnApplicationDiagramService;
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

    $this->service = new CairnApplicationDiagramService;
});

test('empty selection produces no DSL', function () {
    expect($this->service->build([]))->toBeNull();
});

test('selection referencing nothing visible produces no DSL', function () {
    expect($this->service->build([['type' => 'application', 'id' => 999999]]))->toBeNull();
});

test('DSL starts with the fixed application diagram header', function () {
    $app = Application::factory()->create(['external' => null]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)->toStartWith('diagram application "Cairn - vue applicative"');
});

test('a service is mapped to a nested module inside its application', function () {
    $app = Application::factory()->create(['external' => null]);
    $service = ApplicationService::factory()->create(['name' => 'Billing Service']);
    $app->services()->attach($service);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application APP_'.$app->id)
        ->toContain('module APPSERV_'.$service->id.' "Billing Service"');
});

test('a module is mapped to a nested module inside its service application', function () {
    $app = Application::factory()->create(['external' => null]);
    $service = ApplicationService::factory()->create();
    $module = ApplicationModule::factory()->create(['name' => 'Payment Module']);
    $app->services()->attach($service);
    $service->modules()->attach($module);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)->toContain('module MOD_'.$module->id.' "Payment Module"');
});

test('a database is mapped to a datastore', function () {
    $database = Database::factory()->create(['name' => 'Orders DB', 'external' => null]);

    $dsl = $this->service->build([['type' => 'database', 'id' => $database->id]]);

    expect($dsl)->toContain('datastore DB_'.$database->id.' "Orders DB"');
});

test('an entity is mapped to an external node', function () {
    $entity = Entity::factory()->create(['name' => 'Partner Org']);

    $dsl = $this->service->build([['type' => 'entity', 'id' => $entity->id]]);

    expect($dsl)->toContain('external ENTITY_'.$entity->id.' "Partner Org"');
});

test('a service with zero owning applications present in the graph becomes its own root-level application box', function () {
    $service = ApplicationService::factory()->create(['name' => 'Orphan Service']);

    $dsl = $this->service->build([['type' => 'service', 'id' => $service->id]]);

    expect($dsl)
        ->toContain('application APPSERV_'.$service->id.' "Orphan Service" {')
        ->not->toContain('module APPSERV_'.$service->id)
        ->not->toContain('APPX_');
});

test('a service with two owning applications present in the graph becomes its own root-level application box', function () {
    $service = ApplicationService::factory()->create(['name' => 'Shared Service']);
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    $appA->services()->attach($service);
    $appB->services()->attach($service);

    // Les deux applications sont sélectionnées explicitement pour être "présentes dans le
    // graphe" — sinon aucune des deux ne compterait comme parent (cf. autre test dédié).
    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $appA->id],
        ['type' => 'application', 'id' => $appB->id],
        ['type' => 'service', 'id' => $service->id],
    ]);

    expect($dsl)
        ->toContain('application APPSERV_'.$service->id.' "Shared Service" {')
        ->not->toContain('module APPSERV_'.$service->id)
        ->not->toContain('APPX_');
});

test('a module whose services resolve to two applications present in the graph becomes its own root-level application box', function () {
    $module = ApplicationModule::factory()->create(['name' => 'Shared Module']);
    $serviceA = ApplicationService::factory()->create();
    $serviceB = ApplicationService::factory()->create();
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    $appA->services()->attach($serviceA);
    $appB->services()->attach($serviceB);
    $serviceA->modules()->attach($module);
    $serviceB->modules()->attach($module);

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $appA->id],
        ['type' => 'application', 'id' => $appB->id],
        ['type' => 'module', 'id' => $module->id],
    ]);

    expect($dsl)
        ->toContain('application MOD_'.$module->id.' "Shared Module" {')
        ->not->toContain('module MOD_'.$module->id)
        ->not->toContain('APPX_');
});

test('no module ever appears at the root level, regardless of selection', function () {
    // Invariant Cairn E0213 : un `module` racine casse le rendu. Toute ligne "module ..."
    // du DSL doit provenir d'une imbrication (indentation), jamais du niveau racine.
    $orphanService = ApplicationService::factory()->create();
    $orphanModule = ApplicationModule::factory()->create();
    $app = Application::factory()->create(['external' => null]);
    $nestedService = ApplicationService::factory()->create();
    $app->services()->attach($nestedService);

    $dsl = $this->service->build([
        ['type' => 'service', 'id' => $orphanService->id],
        ['type' => 'module', 'id' => $orphanModule->id],
        ['type' => 'application', 'id' => $app->id],
    ]);

    $rootLevelModuleLines = collect(explode("\n", $dsl))
        ->filter(fn (string $line) => str_starts_with($line, 'module '));

    expect($rootLevelModuleLines)->toBeEmpty();
});

test('an application with external set and no drawn children becomes an external node', function () {
    $app = Application::factory()->create(['name' => 'SaaS App', 'external' => 'Externe']);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('external APP_'.$app->id.' "SaaS App"')
        ->not->toContain('application APP_'.$app->id);
});

test('the "extern" match is case-insensitive', function () {
    $app = Application::factory()->create(['name' => 'SaaS App', 'external' => 'EXTERNAL']);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)->toContain('external APP_'.$app->id.' "SaaS App"');
});

test('a value that does not contain "extern" stays internal, even a typo like "Inerne"', function () {
    $app = Application::factory()->create(['name' => 'On-prem App', 'external' => 'Inerne']);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application APP_'.$app->id.' "On-prem App" {')
        ->not->toContain('external APP_'.$app->id);
});

test('an application with external set but with drawn children stays an application', function () {
    $app = Application::factory()->create(['name' => 'SaaS App', 'external' => 'Externe']);
    $service = ApplicationService::factory()->create();
    $app->services()->attach($service);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application APP_'.$app->id.' "SaaS App" {')
        ->toContain('module APPSERV_'.$service->id);
});

test('an application belonging to an ApplicationBlock is nested inside a group named after it', function () {
    $block = ApplicationBlock::factory()->create(['name' => 'RH']);
    $app = Application::factory()->create(['name' => 'Payroll', 'external' => null, 'application_block_id' => $block->id]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application BLOCK_'.$block->id.' "RH" {')
        ->toContain('application APP_'.$app->id.' "Payroll" {');

    $blockPos = strpos($dsl, 'BLOCK_'.$block->id);
    $appPos = strpos($dsl, 'APP_'.$app->id.' "Payroll"');
    expect($appPos)->toBeGreaterThan($blockPos);
});

test('an application without an ApplicationBlock stays at the root, ungrouped', function () {
    $app = Application::factory()->create(['name' => 'Standalone', 'external' => null, 'application_block_id' => null]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application APP_'.$app->id.' "Standalone" {')
        ->not->toContain('BLOCK_');
});

test('two applications sharing the same ApplicationBlock are grouped in a single container', function () {
    $block = ApplicationBlock::factory()->create(['name' => 'Finance']);
    $appA = Application::factory()->create(['name' => 'AppA', 'external' => null, 'application_block_id' => $block->id]);
    $appB = Application::factory()->create(['name' => 'AppB', 'external' => null, 'application_block_id' => $block->id]);

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $appA->id],
        ['type' => 'application', 'id' => $appB->id],
    ]);

    expect(substr_count($dsl, 'BLOCK_'.$block->id))->toBe(1);
});

test('an external application with an ApplicationBlock is still nested inside its group', function () {
    $block = ApplicationBlock::factory()->create(['name' => 'SaaS Vendors']);
    $app = Application::factory()->create(['name' => 'CRM', 'external' => 'Externe', 'application_block_id' => $block->id]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application BLOCK_'.$block->id.' "SaaS Vendors" {')
        ->toContain('external APP_'.$app->id.' "CRM"');
});

test('an ApplicationBlock group has a light yellow fill and a legend note', function () {
    $block = ApplicationBlock::factory()->create(['name' => 'RH']);
    $app = Application::factory()->create(['name' => 'Payroll', 'external' => null, 'application_block_id' => $block->id]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('style { fill: #FFF9C4  stroke: #C9A227 }')
        ->toContain('legend {')
        ->toContain('note "Le cadre jaune représente un groupe applicatif (bloc)."');
});

test('no legend note is added when no application belongs to a block', function () {
    $app = Application::factory()->create(['name' => 'Standalone', 'external' => null, 'application_block_id' => null]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->not->toContain('legend {')
        ->not->toContain('style { fill:');
});

test('an orphan node gets a gray style and a localized legend note (FR)', function () {
    app()->setLocale('fr');
    $service = ApplicationService::factory()->create(['name' => 'Orphan Service']);

    $dsl = $this->service->build([['type' => 'service', 'id' => $service->id]]);

    expect($dsl)
        ->toContain('application APPSERV_'.$service->id.' "Orphan Service" {')
        ->toContain('style { fill: #E8E8E8  stroke: #8A8A8A }')
        ->toContain('legend {')
        ->toContain('note "Les blocs gris représentent un service ou un module sans application propriétaire unique dans le graphe."');
});

test('the orphan legend note is localized under the EN locale', function () {
    app()->setLocale('en');
    $service = ApplicationService::factory()->create(['name' => 'Orphan Service']);

    $dsl = $this->service->build([['type' => 'service', 'id' => $service->id]]);

    expect($dsl)->toContain('note "Gray boxes represent a service or module without a single owning application in the graph."');
});

test('no gray style and no legend note when there is no orphan', function () {
    $app = Application::factory()->create(['name' => 'App', 'external' => null, 'application_block_id' => null]);
    $service = ApplicationService::factory()->create();
    $app->services()->attach($service);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->not->toContain('#E8E8E8')
        ->not->toContain('legend {');
});

test('a real application never gets the orphan gray style', function () {
    $app = Application::factory()->create(['name' => 'Real App', 'external' => null, 'application_block_id' => null]);
    $orphanService = ApplicationService::factory()->create();

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $app->id],
        ['type' => 'service', 'id' => $orphanService->id],
    ]);

    // La vraie application (sans enfant ici) tient sur 2 lignes propres : sa déclaration et
    // son accolade fermante, jamais de bloc `style` gris entre les deux.
    $realAppBlock = "application APP_{$app->id} \"Real App\" {\n}";
    expect($dsl)
        ->toContain($realAppBlock)
        ->toContain('application APPSERV_'.$orphanService->id.' "'.$orphanService->name.'" {'."\n  style { fill: #E8E8E8  stroke: #8A8A8A }\n}");
});

test('block yellow and orphan gray legend notes coexist in a single legend block when both are present', function () {
    app()->setLocale('fr');
    $block = ApplicationBlock::factory()->create(['name' => 'RH']);
    $app = Application::factory()->create(['name' => 'Payroll', 'external' => null, 'application_block_id' => $block->id]);
    $orphanService = ApplicationService::factory()->create();

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $app->id],
        ['type' => 'service', 'id' => $orphanService->id],
    ]);

    expect(substr_count($dsl, 'legend {'))->toBe(1)
        ->and($dsl)
        ->toContain('note "Le cadre jaune représente un groupe applicatif (bloc)."')
        ->toContain('note "Les blocs gris représentent un service ou un module sans application propriétaire unique dans le graphe."');
});

test('a database with external set becomes an external node instead of a datastore', function () {
    $database = Database::factory()->create(['name' => 'Vendor DB', 'external' => 'Externe']);

    $dsl = $this->service->build([['type' => 'database', 'id' => $database->id]]);

    expect($dsl)
        ->toContain('external DB_'.$database->id.' "Vendor DB"')
        ->not->toContain('datastore DB_'.$database->id);
});

test('an entity linked to a drawn application produces a dashed structural edge', function () {
    $entity = Entity::factory()->create();
    $app = Application::factory()->create(['external' => null]);
    $entity->applications()->attach($app);

    $dsl = $this->service->build([['type' => 'entity', 'id' => $entity->id]]);

    expect($dsl)->toContain('ENTITY_'.$entity->id.' -> APP_'.$app->id.' : "utilise" { stroke: dashed }');
});

test('a bidirectional flow produces two edges', function () {
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    ApplicationFlow::factory()->create([
        'name' => 'Sync',
        'bidirectional' => true,
        'application_source_id' => $appA->id,
        'application_dest_id' => $appB->id,
    ]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $appA->id]]);

    expect($dsl)
        ->toContain('APP_'.$appA->id.' -> APP_'.$appB->id.' : "Sync"')
        ->toContain('APP_'.$appB->id.' -> APP_'.$appA->id.' : "Sync"');
});

test('a non-bidirectional flow produces a single edge', function () {
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    ApplicationFlow::factory()->create([
        'name' => 'OneWay',
        'bidirectional' => false,
        'application_source_id' => $appA->id,
        'application_dest_id' => $appB->id,
    ]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $appA->id]]);

    expect(substr_count($dsl, 'OneWay'))->toBe(1);
});

test('labels are sanitized: quotes stripped, newlines collapsed, # neutralized', function () {
    $app = Application::factory()->create([
        'name' => "Weird \"Na#me\"\nWith Break",
        'external' => null,
    ]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('application APP_'.$app->id.' "Weird Name With Break"')
        ->not->toContain('"Weird "Na#me""');
});

test('flow labels are sanitized too', function () {
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    ApplicationFlow::factory()->create([
        'name' => "Bad\"Label\nHere",
        'bidirectional' => false,
        'application_source_id' => $appA->id,
        'application_dest_id' => $appB->id,
    ]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $appA->id]]);

    expect($dsl)->toContain('"BadLabel Here"');
});

test('an application and a database sharing the same numeric id get distinct UIDs', function () {
    $app = Application::factory()->create(['external' => null]);
    $database = Database::factory()->create(['id' => $app->id, 'external' => null]);

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $app->id],
        ['type' => 'database', 'id' => $database->id],
    ]);

    expect($dsl)
        ->toContain('APP_'.$app->id)
        ->toContain('DB_'.$database->id);
});

test('selecting an application expands its services and their modules as seeds', function () {
    $app = Application::factory()->create(['external' => null]);
    $service = ApplicationService::factory()->create();
    $module = ApplicationModule::factory()->create();
    $app->services()->attach($service);
    $service->modules()->attach($module);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect($dsl)
        ->toContain('module APPSERV_'.$service->id)
        ->toContain('module MOD_'.$module->id);
});

test('selecting an entity expands all its applications as seeds', function () {
    $entity = Entity::factory()->create();
    $app = Application::factory()->create(['name' => 'Entity App', 'external' => null]);
    $entity->applications()->attach($app);

    $dsl = $this->service->build([['type' => 'entity', 'id' => $entity->id]]);

    expect($dsl)->toContain('application APP_'.$app->id.' "Entity App"');
});

test('a flow terminal is drawn but not expanded (non-recursive)', function () {
    $seedApp = Application::factory()->create(['external' => null]);
    $terminalApp = Application::factory()->create(['external' => null]);
    $terminalService = ApplicationService::factory()->create();
    $terminalApp->services()->attach($terminalService);

    ApplicationFlow::factory()->create([
        'bidirectional' => false,
        'application_source_id' => $seedApp->id,
        'application_dest_id' => $terminalApp->id,
    ]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $seedApp->id]]);

    expect($dsl)
        ->toContain('APP_'.$terminalApp->id)
        ->not->toContain('APPSERV_'.$terminalService->id);
});

test('a service joins its parent application container when that application is present via a flow terminal, without pulling sibling services', function () {
    $seedApp = Application::factory()->create(['external' => null]);
    $app = Application::factory()->create(['external' => null]);
    $selectedService = ApplicationService::factory()->create();
    $siblingService = ApplicationService::factory()->create();
    $app->services()->attach($selectedService);
    $app->services()->attach($siblingService);

    // `$app` n'est présent que comme terminal de flux (donc jamais expansé) : sans le fix,
    // seul un vrai APP_<id> pour `$app` prouve que la présence "graphe" est bien respectée,
    // et l'absence du sibling prouve qu'un terminal ne tire toujours rien.
    ApplicationFlow::factory()->create([
        'bidirectional' => false,
        'application_source_id' => $seedApp->id,
        'application_dest_id' => $app->id,
    ]);

    $dsl = $this->service->build([
        ['type' => 'application', 'id' => $seedApp->id],
        ['type' => 'service', 'id' => $selectedService->id],
    ]);

    expect($dsl)
        ->toContain('application APP_'.$app->id)
        ->toContain('module APPSERV_'.$selectedService->id)
        ->not->toContain('APPSERV_'.$siblingService->id);
});

test('a service whose sole linked application is not itself present in the graph becomes its own root-level application box', function () {
    $app = Application::factory()->create(['external' => null, 'name' => 'Unselected App']);
    $service = ApplicationService::factory()->create(['name' => 'Lonely Service']);
    $app->services()->attach($service);

    // Seul le service est sélectionné : `$app` n'est ni graine, ni terminal de flux — elle
    // n'est donc pas "présente dans le graphe" et ne doit jamais être récupérée en silence
    // pour servir de conteneur.
    $dsl = $this->service->build([['type' => 'service', 'id' => $service->id]]);

    expect($dsl)
        ->toContain('application APPSERV_'.$service->id.' "Lonely Service" {')
        ->not->toContain('APP_'.$app->id)
        ->not->toContain('APPX_');
});

test('selecting a flux directly draws both endpoints as terminals', function () {
    $appA = Application::factory()->create(['external' => null]);
    $appB = Application::factory()->create(['external' => null]);
    $flow = ApplicationFlow::factory()->create([
        'name' => 'DirectFlux',
        'bidirectional' => false,
        'application_source_id' => $appA->id,
        'application_dest_id' => $appB->id,
    ]);

    $dsl = $this->service->build([['type' => 'flux', 'id' => $flow->id]]);

    expect($dsl)
        ->toContain('APP_'.$appA->id)
        ->toContain('APP_'.$appB->id)
        ->toContain('APP_'.$appA->id.' -> APP_'.$appB->id.' : "DirectFlux"');
});

test('a database selected alone is drawn without pulling unrelated flows', function () {
    $database = Database::factory()->create(['external' => null]);
    $otherApp = Application::factory()->create(['external' => null]);
    $otherDatabase = Database::factory()->create(['external' => null]);
    ApplicationFlow::factory()->create([
        'bidirectional' => false,
        'application_source_id' => $otherApp->id,
        'database_dest_id' => $otherDatabase->id,
    ]);

    $dsl = $this->service->build([['type' => 'database', 'id' => $database->id]]);

    expect($dsl)
        ->toContain('datastore DB_'.$database->id)
        ->not->toContain('DB_'.$otherDatabase->id)
        ->not->toContain('APP_'.$otherApp->id);
});

test('ends with the fixed style block', function () {
    $app = Application::factory()->create(['external' => null]);

    $dsl = $this->service->build([['type' => 'application', 'id' => $app->id]]);

    expect(trim($dsl))->toEndWith('style { lang: fr }');
});
