<?php

use App\Models\Entity;
use App\Models\Relation;
use App\Models\User;
use App\Services\Graph\EcosystemGraphBuilder;
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

test('buildDot draws entity nodes, a parent edge and a relation edge', function () {
    $parent = Entity::factory()->create(['name' => 'Parent Co']);
    $child = Entity::factory()->create(['name' => 'Child Co', 'parent_entity_id' => $parent->id]);
    $relation = Relation::factory()->create(['name' => 'Owns', 'source_id' => $parent->id, 'destination_id' => $child->id]);

    $entities = Entity::query()->whereIn('id', [$parent->id, $child->id])->get();
    $relations = Relation::query()->whereIn('id', [$relation->id])->get();

    $dot = (new EcosystemGraphBuilder)->buildDot($entities, $relations);

    expect($dot)
        ->toContain('E'.$parent->id.' [label="Parent Co"')
        ->toContain('E'.$child->id.' [label="Child Co"')
        ->toContain('E'.$parent->id.' -> E'.$child->id)
        ->toContain('label="Owns"')
        ->toContain('#'.$relation->getUID());
});

test('buildDot ignores relations whose endpoints are outside the entity collection', function () {
    $entityInScope = Entity::factory()->create();
    $entityOutOfScope = Entity::factory()->create();
    Relation::factory()->create(['source_id' => $entityInScope->id, 'destination_id' => $entityOutOfScope->id]);

    $entities = Entity::query()->whereIn('id', [$entityInScope->id])->get();
    $relations = Relation::all();

    $dot = (new EcosystemGraphBuilder)->buildDot($entities, $relations);

    expect($dot)->not->toContain('->');
});

test('imageManifest includes the default entity icon', function () {
    $entities = Entity::query()->whereIn('id', [Entity::factory()->create()->id])->get();

    $manifest = (new EcosystemGraphBuilder)->imageManifest($entities);

    expect($manifest)->toContain(['path' => '/images/entity.png', 'width' => '64px', 'height' => '64px']);
});
