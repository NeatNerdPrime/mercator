# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is Mercator

Mercator is an Information System (IS) cartography web application built on Laravel 11 / PHP 8.4. It maps an organization's IS according to the ANSSI methodology, covering the ecosystem, business processes, applications, logical infrastructure, and physical infrastructure. It tracks a "maturity score" (levels 1–3) based on how completely each object is documented.

## Commands

```bash
# Tests
./vendor/bin/pest                          # all tests
./vendor/bin/pest --group=api              # API tests only
./vendor/bin/pest --group=controller       # controller tests only
./vendor/bin/pest --group=console          # console command tests only
./vendor/bin/pest tests/Feature/Api/ActivityApiTest.php  # single file

# Code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Frontend
npm run dev    # dev server with HMR
npm run build  # production build
```

## Architecture

### Models (`app/Models/`)

All domain models live in `app/Models/`. Most use `SoftDeletes`. Models that should be audited use the `Auditable` trait (auto-creates `AuditLog` entries on create/update/delete). Models declare a `public static array $searchable` property listing fields that support LIKE-based text filtering in the API.

### Controllers

Three groups, all under `app/Http/Controllers/`:

- **`Admin/`** — Web UI controllers (Blade views). Standard CRUD: `index`, `create`, `store`, `edit`, `update`, `destroy`, `massDestroy`. Authorization checked inline with `abort_if(Gate::denies(...))`.
- **`API/`** — REST JSON controllers, all extending `APIController`. Authenticated via Laravel Passport OAuth2.
- **`Report/`** — Report generation controllers (Word/Excel exports).

### `APIController` base class (`app/Http/Controllers/API/APIController.php`)

All API controllers set `protected string $modelClass = ModelClass::class` and delegate to base methods:
- `indexResource(Request $request)` — auto-builds filters (exact, partial/LIKE, `_not`, `_lt/lte/gt/gte`, `_in`, `_between`, `_null`, `_after/_before` for dates, global `search`), sorts, and eager-load includes via `spatie/laravel-query-builder`. Relations are auto-detected via PHP reflection on the model.
- `storeResource()`, `updateResource()`, `destroyResource()`, `massDestroyByIds()`, `massStoreItems()`, `massUpdateItems()` — shared CRUD helpers.

Individual API controllers override these to handle relationship syncing (e.g., `$application->entities()->sync(...)`).

### Authorization

No Laravel Policies. Permissions are stored in the user's session at login as an array (`auth_permissions`). `AuthServiceProvider` registers a `Gate::before()` hook that checks this array before any other gate rule. Permission slugs follow the pattern `model_action` (e.g., `m_application_access`, `m_application_create`).

### Frontend (`resources/BPMN/`)

TypeScript source files, built with Vite. The `bpmn-*.ts` files implement the interactive MaxGraph-based cartography editor (graph show/edit views). D3/Graphviz is used for network topology views. Non-graph views use jQuery, Select2, DataTables, and Chart.js.

### Testing conventions

Tests use Pest with `RefreshDatabase`. Before each API test, seed the permissions/roles/users tables and authenticate with `Passport::actingAs($user)`. The admin user is `admin@admin.com`. Test groups map to directories: `Feature/Api` → `api`, `Feature/Controller` → `controller`, `Feature/Console` → `console`.

### Artisan commands

- `mercator:cpe-sync` / `mercator:cpe-import` — sync CPE (Common Platform Enumeration) data
- `mercator:cve-search` — search CVEs for known assets
- `mercator:certificate-expiration` — check certificate expiry
- `mercator:cleanup` — housekeeping tasks

### Key config files

- `config/mercator-config.php` — CVE provider settings, mail
- `config/api.php` — rate limiting (`api.rate_limit`, `api.rate_limit_decay`)
- `config/ldap.php` — LDAP/Active Directory auth
- `version.txt` — read at boot and exposed as `app('mercator.version')`
