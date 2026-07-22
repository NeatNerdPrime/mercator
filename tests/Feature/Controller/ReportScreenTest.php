<?php

use App\Models\User;
use App\Support\ReportTemplateSettings;
use Database\Seeders\PermissionRoleTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\RoleUserTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

// ReportTemplateSettings::storagePath() is a real filesystem path, not test/env-isolated the way
// the database connection is -- a bare @unlink() here would delete a real admin-uploaded template
// if one happened to exist on disk when the suite runs. This file never actually writes to that
// path (only the DB-backed metadata via ReportTemplateSettings::save()), so no cleanup of it is
// needed at all; the misplaced unlink was pure, unconditional collateral damage.

describe('cartography report screen', function () {
    test('shows the default-template message and upload field when no custom template is active', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.doc.report'));

        $response->assertOk();
        $response->assertSee(trans('report_template.using_default'));
        $response->assertSee(route('admin.report.cartography.template.default'), false);
        $response->assertSee('name="template"', false);
    });

    test('shows the active custom template\'s name and upload date', function () {
        ReportTemplateSettings::save('my-custom-template.docx', Carbon::parse('2026-01-15 10:30:00'));

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.doc.report'));

        $response->assertOk();
        $response->assertSee('my-custom-template.docx');
        $response->assertSee('15/01/2026 10:30');
        $response->assertDontSee(trans('report_template.using_default'));
    });

    test('hides the upload field from a user without the configure permission', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.doc.report'));

        $response->assertOk();
        $response->assertDontSee('name="template"', false);
    });

    test('no longer shows the report lists content, now split into its own page', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.doc.report'));

        $response->assertOk();
        $response->assertDontSee('Common Vulnerabilities and Exposures');
        $response->assertDontSee(route('admin.report.directory'), false);
    });
});

describe('cartography lists screen', function () {
    test('shows the report lists, split out of the cartography generation page', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.doc.lists'));

        $response->assertOk();
        $response->assertSee(trans('cruds.report.lists.title'));
        $response->assertSee('Common Vulnerabilities and Exposures');
        $response->assertSee(route('admin.report.directory'), false);
    });

    test('does not show the cartography generation form or the document template card', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.doc.lists'));

        $response->assertOk();
        $response->assertDontSee(route('admin.report.cartography.template.default'), false);
        $response->assertDontSee('name="vues[]"', false);
    });
});
