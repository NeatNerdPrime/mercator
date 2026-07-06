<?php

use App\Models\Application;
use App\Models\DataProcessing;
use App\Models\Entity;
use App\Models\MacroProcessus;
use App\Models\Network;
use App\Models\Site;
use App\Models\User;
use App\Models\ZoneAdmin;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
});

function assertDocxDownload($response): void
{
    $response->assertOk();

    $base = $response->baseResponse;
    expect($base)->toBeInstanceOf(BinaryFileResponse::class);

    $content = file_get_contents($base->getFile()->getPathname());
    expect(strlen($content))->toBeGreaterThan(100)
        ->and(substr($content, 0, 2))->toBe('PK');
}

describe('cartography report generation', function () {
    test('generates a report with no vues selected (defaults to all)', function () {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.report.cartography'), []);

        assertDocxDownload($response);
    });

    test('generates a report with a single vue selected', function () {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['1']]);

        assertDocxDownload($response);
    });

    test('generates a report with all 7 vues selected', function () {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.report.cartography'), [
            'vues' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        assertDocxDownload($response);
    });

    test('generates a report without a granularity parameter', function () {
        $this->actingAs($this->admin);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['2']]);

        assertDocxDownload($response);
    });

    test('excludes graphs by default (the "with schemas" checkbox is unchecked)', function () {
        $this->actingAs($this->admin);

        // No Entity/Relation created: vue 1's own content stays empty (addEntities()/addRelations()
        // bail out early), isolating the graph as the only possible image source in this assertion.
        $response = $this->put(route('admin.report.cartography'), ['vues' => ['1']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->not->toContain('<w:pict');
    });

    test('includes graphs when the "with schemas" checkbox is checked, with the media actually embedded in the docx', function () {
        $this->actingAs($this->admin);
        // A parent/child pair (rather than a single isolated Entity) so each one gets its own
        // family graph under EcosystemSection's per-entity split (an isolated entity with neither
        // a parent nor children is skipped entirely and would produce no graph at all).
        $parent = Entity::factory()->create(['name' => 'Graph Embed Parent']);
        Entity::factory()->create(['name' => 'Graph Embed Child', 'parent_entity_id' => $parent->id]);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['1'], 'graph' => '1']);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');

        // Regression guard: insertGraph() used to delete its temp PNG before PhpWord's writer ever
        // read it at save() time, so the relationship/reference was written but "word/media/*.png"
        // stayed empty — every graph rendered as a blank placeholder in Word. A real graph's
        // rasterized PNG is tens of KB; a missing/blank one is a broken reference or near-empty file.
        $mediaSizes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'word/media/') && str_ends_with($name, '.png')) {
                $mediaSizes[] = strlen($zip->getFromName($name));
            }
        }
        $zip->close();

        // Expect 3 embedded PNGs: both entities share the same default icon fallback path, deduped
        // by PhpWord's Media registry into 1 entry, plus one family graph each for the parent and
        // the child (from insertGraph) — asserting a count, not just "at least one", because the
        // entity icon alone was enough to make a weaker assertion pass while the graph's own media
        // was still silently missing.
        expect($xml)->toContain('<w:pict');
        expect($mediaSizes)->toHaveCount(3);
        expect(min($mediaSizes))->toBeGreaterThan(1000);
    });

    test('includes real Ecosystem content when vue 1 is selected', function () {
        $this->actingAs($this->admin);
        $entity = Entity::factory()->create(['name' => 'Cartography Test Entity']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['1']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test Entity')
            ->toContain($entity->getUID());
    });

    test('includes real Information System content when vue 2 is selected', function () {
        $this->actingAs($this->admin);
        $macroProcess = MacroProcessus::factory()->create(['name' => 'Cartography Test MacroProcess']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['2']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test MacroProcess')
            ->toContain($macroProcess->getUID());
    });

    test('includes real Application content when vue 3 is selected', function () {
        $this->actingAs($this->admin);
        $application = Application::factory()->create(['name' => 'Cartography Test Application']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['3']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test Application')
            ->toContain($application->getUID());
    });

    test('includes real Administration content when vue 4 is selected', function () {
        $this->actingAs($this->admin);
        $zoneAdmin = ZoneAdmin::factory()->create(['name' => 'Cartography Test ZoneAdmin']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['4']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test ZoneAdmin')
            ->toContain($zoneAdmin->getUID());
    });

    test('includes real Logical Infrastructure content when vue 5 is selected', function () {
        $this->actingAs($this->admin);
        $network = Network::factory()->create(['name' => 'Cartography Test Network']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['5']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test Network')
            ->toContain($network->getUID());
    });

    test('includes real Physical Infrastructure content when vue 6 is selected', function () {
        $this->actingAs($this->admin);
        $site = Site::factory()->create(['name' => 'Cartography Test Site']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['6']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test Site')
            ->toContain($site->getUID());
    });

    test('includes real GDPR content when vue 7 is selected', function () {
        $this->actingAs($this->admin);
        $dataProcessing = DataProcessing::factory()->create(['name' => 'Cartography Test DataProcessing']);

        $response = $this->put(route('admin.report.cartography'), ['vues' => ['7']]);

        assertDocxDownload($response);

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)
            ->toContain('Cartography Test DataProcessing')
            ->toContain($dataProcessing->getUID());
    });

    test('denies access without permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->put(route('admin.report.cartography'), []);

        $response->assertForbidden();
    });
});
