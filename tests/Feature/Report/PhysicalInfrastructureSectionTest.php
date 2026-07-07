<?php

use App\Models\AdminUser;
use App\Models\Bay;
use App\Models\Building;
use App\Models\Peripheral;
use App\Models\PhysicalLink;
use App\Models\PhysicalServer;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Models\User;
use App\Models\Workstation;
use App\Models\Zone;
use App\Services\Report\PhysicalInfrastructureSection;
use App\Services\Report\WordHelper;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\IOFactory;

uses(RefreshDatabase::class);

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

/**
 * @return array{0: string, 1: string, 2: array<int, int>} [document.xml, document.xml.rels, media PNG sizes]
 */
function renderPhysicalInfrastructureSectionXml(array $selectedVues = ['6']): array
{
    $helper = new WordHelper;
    $phpWord = $helper->newDocument();
    $section = $phpWord->addSection();

    (new PhysicalInfrastructureSection)->build($section, $helper, $selectedVues);

    $path = tempnam(sys_get_temp_dir(), 'docx');
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    $helper->cleanupTempFiles();

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $rels = $zip->getFromName('word/_rels/document.xml.rels');
    $mediaSizes = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_starts_with($name, 'word/media/') && str_ends_with($name, '.png')) {
            $mediaSizes[] = $zip->statIndex($i)['size'];
        }
    }
    $zip->close();
    unlink($path);

    return [$xml, $rels, $mediaSizes];
}

describe('PhysicalInfrastructureSection content', function () {
    test('renders the site-to-building-to-bay chain with the 6 bay-equipment relations as separate rows', function () {
        $site = Site::factory()->create(['name' => 'Paris DC']);
        $building = Building::factory()->create(['name' => 'Building A', 'site_id' => $site->id]);
        $bay = Bay::factory()->create(['name' => 'Rack 1', 'site_id' => $site->id, 'building_id' => $building->id]);
        $server = PhysicalServer::factory()->create(['name' => 'srv-01', 'bay_id' => $bay->id, 'site_id' => $site->id, 'building_id' => $building->id]);
        $storage = StorageDevice::factory()->create(['name' => 'san-01', 'bay_id' => $bay->id, 'site_id' => $site->id, 'building_id' => $building->id]);

        [$xml] = renderPhysicalInfrastructureSectionXml();

        expect($xml)
            ->toContain('Paris DC')
            ->toContain('Building A')
            ->toContain('Rack 1')
            ->toContain('srv-01')
            ->toContain('san-01')
            ->toContain($site->getUID())
            ->toContain($building->getUID())
            ->toContain($bay->getUID())
            ->toContain($server->getUID())
            ->toContain($storage->getUID());
    });

    test('renders Zone parent/child zones and admin users labeled by user_id', function () {
        $parentZone = Zone::factory()->create(['name' => 'Parent Zone']);
        $childZone = Zone::factory()->create(['name' => 'Child Zone']);
        $parentZone->childZones()->attach($childZone->id);
        $adminUser = AdminUser::factory()->create(['user_id' => 'jsmith']);
        $parentZone->adminUsers()->attach($adminUser->id);

        [$xml] = renderPhysicalInfrastructureSectionXml();

        expect($xml)
            ->toContain('Parent Zone')
            ->toContain('Child Zone')
            ->toContain('jsmith')
            ->toContain($parentZone->getUID())
            ->toContain($childZone->getUID());
    });

    test('renders PhysicalServer HTML configuration fields, Workstation user link by user_id, and Peripheral version (not the broken pversion)', function () {
        $physicalServer = PhysicalServer::factory()->create(['name' => 'app-srv', 'cpu' => '<p>8 vCPU</p>']);
        $adminUser = AdminUser::factory()->create(['user_id' => 'wuser']);
        $workstation = Workstation::factory()->create(['name' => 'WKS-01', 'user_id' => $adminUser->id]);
        $peripheral = Peripheral::factory()->create(['name' => 'Printer 1', 'version' => 'v2.3']);

        [$xml] = renderPhysicalInfrastructureSectionXml();

        expect($xml)
            ->toContain('app-srv')
            ->toContain('8 vCPU')
            ->toContain('WKS-01')
            ->toContain('wuser')
            ->toContain('Printer 1')
            ->toContain('v2.3')
            ->toContain($physicalServer->getUID())
            ->toContain($workstation->getUID())
            ->toContain($peripheral->getUID());
    });

    test('renders a standalone PhysicalLink with an ad hoc bookmark (no getUID) resolved via its polymorphic endpoints', function () {
        $server = PhysicalServer::factory()->create(['name' => 'link-src-server']);
        $storage = StorageDevice::factory()->create(['name' => 'link-dest-storage']);
        $link = PhysicalLink::factory()->create([
            'type' => 'fiber',
            'color' => '#ff0000',
            'physical_server_src_id' => $server->id,
            'storage_device_dest_id' => $storage->id,
        ]);

        [$xml] = renderPhysicalInfrastructureSectionXml();

        expect($xml)
            ->toContain('link-src-server')
            ->toContain('link-dest-storage')
            ->toContain('fiber')
            ->toContain('#ff0000')
            ->toContain('PHYSICAL_LINK_'.$link->id)
            ->toContain($server->getUID())
            ->toContain($storage->getUID());
    });

    test('splits location/connectivity graphs per building-under-site while the security-zone graph stays a single global graph', function () {
        $siteA = Site::factory()->create(['name' => 'Site A']);
        $buildingA1 = Building::factory()->create(['name' => 'Building A1', 'site_id' => $siteA->id]);
        $buildingA2 = Building::factory()->create(['name' => 'Building A2', 'site_id' => $siteA->id]);
        // Each building needs at least 2 of its own devices: WordHelper::insertGraph() now skips
        // graphs with fewer than 2 nodes, and a building's own root-building connectivity subgraph
        // draws only its devices (never the building itself as a node — only the location-hierarchy
        // graph does that), so a bare building would otherwise be filtered out entirely.
        Workstation::factory()->create(['name' => 'WKS-A1', 'site_id' => $siteA->id, 'building_id' => $buildingA1->id]);
        PhysicalServer::factory()->create(['name' => 'SRV-A1', 'site_id' => $siteA->id, 'building_id' => $buildingA1->id]);
        Workstation::factory()->create(['name' => 'WKS-A2', 'site_id' => $siteA->id, 'building_id' => $buildingA2->id]);
        PhysicalServer::factory()->create(['name' => 'SRV-A2', 'site_id' => $siteA->id, 'building_id' => $buildingA2->id]);
        Site::factory()->create(['name' => 'Site B (no buildings)']);

        [, , $mediaSizes] = renderPhysicalInfrastructureSectionXml();

        // Security-zone graph: always exactly 1 (2 building nodes, unconditionally drawn regardless
        // of zones), unchanged.
        // Location hierarchy: 1 graph per building of Site A (2, each with building+workstation+server
        // = 3 nodes), none for Site B (no buildings).
        // Connectivity: Site A gets 1 site-wide graph (4 device nodes) + 1 per root building (2, each
        // with its own workstation+server = 2 nodes) = 3; Site B is skipped entirely (no buildings).
        // Total graphs: 1 + 2 + 3 = 6.
        // Icons: site/building/workstation/physicalServer each have no custom icon, so each type's
        // fallback path dedupes to 1 entry: site.png + building.png + workstation.png + server.png = 4.
        // Total media: 6 + 4 = 10.
        expect($mediaSizes)->toHaveCount(10);
    });
});
