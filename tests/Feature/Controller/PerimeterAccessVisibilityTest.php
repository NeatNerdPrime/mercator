<?php

use App\Http\Controllers\Admin\ExplorerController;
use App\Models\Application;
use App\Models\Cartographer;
use App\Models\Entity;
use App\Models\Perimeter;
use App\Models\Permission;
use App\Models\Relation;
use App\Models\Role;
use App\Models\User;
use App\Support\PerimeterSettings;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Visibilité par droit d'accès : dans les listes et dans l'explorateur, un objet n'apparaît que
 * si l'utilisateur a la permission `<type>_access` dans le périmètre de cet objet.
 *
 * Scénario — Bob a deux rôles :
 *   - « Full-P1 » : entités (access/show/create/edit/delete), relations (access), explore_access, dans P1 ;
 *   - « AppsOnly-P2 » : application_access seulement, dans P2.
 * Il est donc membre de P2 mais n'y a aucun accès aux entités ; il n'a aucun rôle dans P3.
 */

uses(RefreshDatabase::class);

function visPermIds(array $titles): array
{
    return Permission::query()->whereIn('title', $titles)->pluck('id')->all();
}

function visRole(int $perimeterId, string $title, array $permissions): Role
{
    $role = Role::factory()->create(['title' => $title, 'perimeter_id' => $perimeterId]);
    $role->permissions()->sync(visPermIds($permissions));

    return $role;
}

function visInPerimeter(string $table, $model, int $perimeterId)
{
    DB::table($table)->where('id', $model->id)->update(['perimeter_id' => $perimeterId]);

    return $model->fresh();
}

beforeEach(function () {
    $this->seed([
        PermissionsTableSeeder::class,
        RolesTableSeeder::class,
        PermissionRoleTableSeeder::class,
        UsersTableSeeder::class,
        RoleUserTableSeeder::class,
    ]);

    foreach (['roleIdsCache', 'dbPermissionsCache', 'cartographerCache'] as $cache) {
        (new ReflectionProperty(Cartographer::class, $cache))->setValue(null, []);
    }

    $this->p1 = Perimeter::find(Perimeter::DEFAULT_ID);
    $this->p2 = Perimeter::factory()->create();
    $this->p3 = Perimeter::factory()->create();

    $this->fullP1 = visRole($this->p1->id, 'Full-P1', [
        'entity_access', 'entity_show', 'entity_create', 'entity_edit', 'entity_delete',
        'relation_access', 'explore_access',
    ]);
    $this->appsP2 = visRole($this->p2->id, 'AppsOnly-P2', ['application_access']);

    $this->bob = User::factory()->create();
    $this->bob->roles()->attach([$this->fullP1->id, $this->appsP2->id]);

    $this->e1 = visInPerimeter('entities', Entity::factory()->create(['attributes' => 'tag-visible']), $this->p1->id);
    $this->e2 = visInPerimeter('entities', Entity::factory()->create(['attributes' => 'tag-hidden']), $this->p2->id);
    $this->e3 = visInPerimeter('entities', Entity::factory()->create(['attributes' => 'tag-foreign']), $this->p3->id);

    // Bob voit ce type d'objet dans P2 mais pas dans P1.
    $this->app1 = visInPerimeter('applications', Application::factory()->create(), $this->p1->id);
    $this->app2 = visInPerimeter('applications', Application::factory()->create(), $this->p2->id);

    PerimeterSettings::setEnabled(true);
});

describe('lists only show objects the user has <type>_access on, per perimeter', function () {
    test('an entity of a perimeter without entity_access is hidden, even though the user is a member of it', function () {
        $this->actingAs($this->bob);

        $response = $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.entities.index'))
            ->assertOk();

        $ids = $response->viewData('entities')->pluck('id');

        expect($ids)->toContain($this->e1->id)
            ->and($ids)->not->toContain($this->e2->id)
            ->and($ids)->not->toContain($this->e3->id);
        // Everything listed belongs to the one perimeter where Bob has entity_access.
        expect(Entity::withoutGlobalScopes()->whereIn('id', $ids)->pluck('perimeter_id')->unique()->all())->toBe([$this->p1->id]);
    });

    test('granting entity_access in that perimeter makes its entities appear', function () {
        $this->appsP2->permissions()->syncWithoutDetaching(visPermIds(['entity_access']));
        $this->actingAs($this->bob);

        $response = $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.entities.index'))
            ->assertOk();

        // P3 stays hidden: no role there.
        $ids = $response->viewData('entities')->pluck('id');

        expect($ids)->toContain($this->e1->id, $this->e2->id)
            ->and($ids)->not->toContain($this->e3->id);
    });

    test('the same user sees applications where she has application_access, and not entities there', function () {
        $this->actingAs($this->bob);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        $entities = Entity::query()->pluck('id');
        $applications = Application::query()->pluck('id');

        expect($entities)->toContain($this->e1->id)
            ->and($entities)->not->toContain($this->e2->id)
            ->and($applications)->toContain($this->app2->id)
            ->and($applications)->not->toContain($this->app1->id);
    });

    test('opening the list of a type in a perimeter where the user has no access on it is refused', function () {
        $this->actingAs($this->bob);

        $this->withSession(['active_perimeter' => $this->p2->id])
            ->get(route('admin.entities.index'))
            ->assertForbidden();
    });

    test('a hidden object cannot be opened directly either', function () {
        $this->actingAs($this->bob);

        $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.entities.show', $this->e2))
            ->assertForbidden();
    });

    test('an object delegated to the user as cartographer stays listed whatever its perimeter', function () {
        Cartographer::create([
            'cartographiable_type' => Entity::class,
            'cartographiable_id' => $this->e3->id,
            'user_id' => $this->bob->id,
        ]);
        $this->actingAs($this->bob);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        $ids = Entity::query()->pluck('id');

        expect($ids)->toContain($this->e1->id, $this->e3->id)
            ->and($ids)->not->toContain($this->e2->id);
    });

    test('administrators see every object', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        $this->actingAs($admin);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        expect(Entity::query()->count())->toBe(Entity::withoutGlobalScopes()->count());
    });

    test('feature disabled: nothing is hidden', function () {
        PerimeterSettings::setEnabled(false);
        $this->actingAs($this->bob);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        expect(Entity::query()->count())->toBe(Entity::withoutGlobalScopes()->count());
    });
});

describe('explorer', function () {
    beforeEach(function () {
        // e1 (visible) -> relation (P1, visible) -> e2 (hidden entity of P2)
        $this->relation = visInPerimeter(
            'relations',
            Relation::factory()->create(['source_id' => $this->e1->id, 'destination_id' => $this->e2->id, 'attributes' => 'rel-tag']),
            $this->p1->id,
        );
        $this->node = fn (string $prefix, $model) => $prefix.$model->id;

        $this->explore = function () {
            $this->actingAs($this->bob);
            $json = $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
                ->get(route('admin.reports.explore.data'))
                ->assertOk()
                ->streamedContent();

            return json_decode($json, true);
        };
    });

    test('nodes are limited to objects the user can access', function () {
        $data = ($this->explore)();
        $nodeIds = collect($data['nodes'])->pluck('id');

        expect($nodeIds)->toContain(Entity::$prefix.$this->e1->id)
            ->and($nodeIds)->toContain(Relation::$prefix.$this->relation->id)
            ->and($nodeIds)->not->toContain(Entity::$prefix.$this->e2->id)
            ->and($nodeIds)->not->toContain(Entity::$prefix.$this->e3->id);
    });

    test('an edge never points to an object the user cannot see', function () {
        $data = ($this->explore)();
        $edges = collect($data['edges']);

        // e1 -> relation is visible on both ends; relation -> e2 (hidden) is dropped.
        expect($edges->contains(fn ($e) => $e['from'] === Entity::$prefix.$this->e1->id && $e['to'] === Relation::$prefix.$this->relation->id))->toBeTrue()
            ->and($edges->contains(fn ($e) => $e['to'] === Entity::$prefix.$this->e2->id || $e['from'] === Entity::$prefix.$this->e2->id))->toBeFalse();

        $nodeIds = collect($data['nodes'])->pluck('id')->flip();
        foreach ($edges as $edge) {
            expect($nodeIds->has($edge['from']) && $nodeIds->has($edge['to']))->toBeTrue();
        }
    });

    test('the attribute tags only come from visible objects', function () {
        $data = ($this->explore)();

        expect($data['attributes'])->toContain('tag-visible')
            ->and($data['attributes'])->not->toContain('tag-hidden')
            ->and($data['attributes'])->not->toContain('tag-foreign');
    });

    test('the attribute list endpoint does not leak tags of hidden objects', function () {
        $this->actingAs($this->bob);

        $tags = $this->withSession(['active_perimeter' => Perimeter::ALL_ID])
            ->get(route('admin.reports.explore.attributes'))
            ->assertOk()
            ->json();

        expect($tags)->toContain('tag-visible', 'rel-tag')
            ->and($tags)->not->toContain('tag-hidden')
            ->and($tags)->not->toContain('tag-foreign');
    });

    test('getData() (also used by the graph editor) applies the same filtering', function () {
        $this->actingAs($this->bob);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        [$nodes, $edges] = (new ExplorerController)->getData();
        $nodeIds = collect($nodes)->pluck('id');

        expect($nodeIds)->toContain(Entity::$prefix.$this->e1->id)
            ->and($nodeIds)->not->toContain(Entity::$prefix.$this->e2->id)
            ->and(collect($edges)->contains(fn ($e) => $e['to'] === Entity::$prefix.$this->e2->id))->toBeFalse();
    });

    test('administrators see the whole graph', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        Role::query()->findOrFail(1)->permissions()->sync(Permission::query()->pluck('id')->all());
        $this->actingAs($admin);
        session(['active_perimeter' => Perimeter::ALL_ID]);

        [$nodes, $edges] = (new ExplorerController)->getData();
        $nodeIds = collect($nodes)->pluck('id');

        expect($nodeIds)->toContain(Entity::$prefix.$this->e2->id)
            ->and(collect($edges)->contains(fn ($e) => $e['to'] === Entity::$prefix.$this->e2->id))->toBeTrue();
    });
});
