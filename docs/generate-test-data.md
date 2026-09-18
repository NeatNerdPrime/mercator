# Test Data Generation

Mercator ships an Artisan command that generates a realistic, internally consistent set of test data — perimeters, applications, databases, logical servers, application flows, and physical infrastructure (sites, buildings, floors, rooms, bays, physical servers/switches/routers, peripherals, workstations, phones, WiFi access points, security zones) — for demos, UI testing at scale, and load testing.

```bash
php artisan mercator:generate-test-data [options]
```

It has no web UI: it is run from the server's command line by an administrator.

---

## Quick start

```bash
# Use a predefined profile
php artisan mercator:generate-test-data --scenario=pme

# Preview what would be created, without writing anything
php artisan mercator:generate-test-data --scenario=large_enterprise --dry-run

# Fully custom counts
php artisan mercator:generate-test-data --perimeters=3 --applications=200 --servers=100 --databases=50 --flows=500 --sites=6 --buildings=15 --bays=40 --physical-servers=80 --peripherals=20 --workstations=150
```

Running the command with no options at all uses the `default_scenario` configured in `config/data-scenarios.php` (`pme` out of the box).

---

## Predefined scenarios

Pass `--scenario=<name>` to use one of the profiles defined in `config/data-scenarios.php`:

| Scenario | Perimeters | Applications | Databases | Servers | Flows | Sites | Buildings | Bays | Physical servers | Peripherals | Workstations | Security zones |
|----------|-----------:|-------------:|----------:|--------:|------:|------:|----------:|-----:|------------------:|------------:|-------------:|----------------:|
| `pme` | 1 | 15 | 5 | 8 | 7 | 1 | 2 | 4 | 6 | 4 | 10 | 2 |
| `mid_market` | 2 | 80 | 25 | 40 | 50 | 3 | 6 | 12 | 20 | 15 | 60 | 4 |
| `large_enterprise` | 4 | 400 | 100 | 200 | 400 | 6 | 15 | 40 | 80 | 40 | 300 | 8 |
| `load_test` | 2 | 1000 | 200 | 2000 | 1667 | 4 | 10 | 30 | 100 | 30 | 150 | 6 |

Flows are a third of what a plain applications-only count would suggest, since each one can now connect any of four endpoint types (application, service, module, database) rather than just two applications — see **Application flows** below.

Floors, rooms, networks, subnetworks, VLANs, phones, WiFi access points, physical switches, physical routers, application blocks, application services/modules, admin zones and AD domains aren't in this table: floors and rooms are a direct per-site/per-floor draw (see `floors_per_site` / `locals_per_floor` in the config file, 1–8 and 5–10 by default), networks/subnetworks/VLANs are one network per site with a direct per-network draw of subnetworks (`subnetworks_per_network`, 4–16 by default), phones always match the number of workstations actually created, WiFi access points are a per-floor/per-room probability, switches/routers are a direct consequence of the number of bays/floors/sites (one switch per bay, one switch per floor, at most one router per site), application blocks are a direct per-perimeter draw (`application_blocks_per_perimeter`, 5–7 by default), application services/modules are a direct per-application/per-service draw (`application_services_per_application` 0–5, `application_modules_per_service` 0–3), and admin zones/AD domains are a direct per-site draw (`zone_admins_per_site` 1–3, `domains_per_site` 3–5) — see below for all of them.

Any manual option (`--applications`, `--servers`, ...) overrides just that one value on top of the chosen scenario — you don't have to specify all of them.

---

## Options

| Option | Description |
|--------|-------------|
| `--scenario=<name>` | Predefined profile (see table above). Ignored for a counter that is also set manually. |
| `--perimeters=<n>` | Total number of perimeters to end up with (the default perimeter counts as 1 — only missing ones are created). |
| `--applications=<n>` | Number of applications to create. |
| `--databases=<n>` | Number of databases to create. |
| `--servers=<n>` | Number of logical servers to create. |
| `--flows=<n>` | Number of application flows to create (requires at least 2 applications). |
| `--sites=<n>` | Number of sites to create. |
| `--buildings=<n>` | Number of buildings to create. |
| `--bays=<n>` | Number of bays (racks) to create. |
| `--physical-servers=<n>` | Number of physical servers to create. |
| `--peripherals=<n>` | Number of peripherals (printers, badge readers, UPS, ...) to create. |
| `--workstations=<n>` | Number of workstations to create. |
| `--security-zones=<n>` | Number of security zones to create. |
| `--chunk=<n>` | Batch size for bulk inserts (default from `config/data-scenarios.php`, `500`). |
| `--seed=<n>` | Random seed, for reproducible output across runs. |
| `--dry-run` | Compute and display the plan, write nothing to the database. |
| `--force` | Skip the interactive confirmation prompt. Does **not** delete any existing data. |

There is no `--floors`, `--locals` (rooms), `--networks`, `--subnetworks`, `--vlans`, `--phones`, `--wifi-terminals`, `--physical-switches`, `--physical-routers`, `--application-blocks`, `--application-services`, `--application-modules`, `--zone-admins`, `--domains`, `--macro-processes`, `--processes`, `--activities`, `--operations`, `--tasks`, `--actors`, `--informations`, `--entities`, `--relations` or `--data-processing` option — see below for why.

---

## What gets generated

* **Perimeters** — picked from a pool of realistic organisation-style names (`Production`, `Filiale Nord`, `Donnees Sante`, ...), falling back to a generated name once the pool is exhausted. Existing perimeters are kept; only the missing count is created.
* **Applications / Databases / Logical servers** — realistic names (`acme-billing-prod`, `db-crm-staging`, `web03-prod`, ...), plausible types and security-need levels, spread evenly across the available perimeters. Each logical server is also paired with a physical server from its own perimeter (`logical_server_physical_server`), and its IP address is drawn from a subnetwork on that physical server's site rather than being a generic address — unless no eligible physical server/subnetwork exists, in which case a generic IP is used instead.
* **Application blocks** — every perimeter gets 5–7 application blocks (`application_blocks_per_perimeter`), and every application is assigned to one block **from its own perimeter**.
* **Application services and modules** — not a target count either: every *application* gets 0–5 application services (`application_services_per_application`), each one dedicated to that single application (never shared), and every *application service* gets 0–3 application modules (`application_modules_per_service`), each dedicated to that single service. Both get realistic names/types/vendor/version.
* **Application ↔ Server** links — each application is linked to 1, 2 or 3 logical servers **from its own perimeter**, never 0: 90% of applications get exactly one, the rest split between two and three (`APPLICATION_SERVER_COUNTS`, not a configurable min/max).
* **Application ↔ Database** links — each application is linked to a random number of databases **from its own perimeter** (see `databases_per_application` in the config file).
* **Application flows** — connect two *endpoints*, each independently an application, an application service, an application module, or a database (any combination — service-to-database, module-to-module, application-to-service, ...), never the exact same object on both ends. A configurable share of flows (`cross_perimeter_flow_probability`, default `10%`) deliberately link two endpoints from different perimeters, to exercise cross-perimeter flow visibility; the rest stay within a single perimeter. Predefined scenarios generate a third as many flows as before this endpoint diversification, to keep the total volume of generated links comparable.
* **Physical infrastructure (technical side)** — sites, buildings, bays, physical servers and peripherals, chained coherently: a building references a site from its own perimeter, a bay references the same site as its building, and a physical server/peripheral references the same bay/building/site — never a mix of perimeters. A child object without an eligible parent in its own perimeter (e.g. `--buildings` requested but `--sites=0`) is still created, just without that particular link.
* **Networks, subnetworks and VLANs (logical side)** — one `Network` per site, named exactly like that site (`Network` has no `site_id` column, so the association is by name rather than a foreign key). Each network gets 4–16 subnetworks (`subnetworks_per_network`), and each subnetwork is paired 1:1 with its own VLAN via `subnetworks.vlan_id`.
* **Floors and rooms (office side)** — a second, parallel hierarchy, independent of `--buildings`/`--bays`: every site gets 1–8 floors named `ET1`, `ET2`, ... (`floors_per_site`), and every floor gets 5–10 rooms named `LOCAL042`-style (`locals_per_floor`). Both are stored the same way as buildings, just tagged with `type` = `Etage` / `Local`.
* **Physical switches and routers** — not a target count either: every *bay* gets its own physical switch (rack/technical wiring), every *floor* also gets its own physical switch (office wiring — a second, independent switch, not shared with the bay's), and every *site* with at least one bay gets exactly one physical router, placed in one of that site's bays. A site with 0 bays gets no router (and none of its floor switches get linked to one either).
* **Physical links** — a physical link connects each physical server to the switch of its own bay, and each switch — whether from a bay or a floor — to the router of its site, so the whole rack/site wiring is navigable, not just the equipment list.
* **Workstations and phones** — always located in a *room*, never directly in a floor or a building. Phones are always exactly as many as the workstations actually created.
* **WiFi access points** — unlike every other object above, not a target count: each *floor or room* independently has an 80% chance (`wifi_terminal_probability` in the config file) of getting its own access point. With 0 sites (hence 0 floors/rooms), there are 0 access points, whatever else is requested.
* **Workstation and WiFi links** — each workstation and each WiFi access point is connected by a physical link to its *nearest* switch — the switch of its own floor, or, when it lives in a room, the switch of the floor that contains that room. Never a bay switch (a workstation isn't in a rack).
* **Security zones** — each one is linked to 1–3 buildings from its own perimeter (see `buildings_per_zone` in the config file). Zones link to actual buildings, not to floors or rooms.
* **Directory / Active Directory infrastructure (per site)** — not a target count either: every *site* gets 1–3 admin zones (`zone_admins_per_site`), exactly one directory service (`Annuaire`, linked to one of these admin zones and, when the site's perimeter has one, an application), and exactly one AD forest (`ForestAd`, linked to one of the same admin zones). Each site also gets 3–5 domains (`domains_per_site`), all attached to that site's AD forest (`domain_forest_ad`). None of `zone_admins`/`annuaires`/`forest_ads`/`domains` has a `site_id` column, so "per site" only structures the generation — not something queryable back from the database beyond the shared perimeter.
* **Admin users** — every site gets exactly as many `admin_users` as workstations actually created on that site, split as evenly as possible across that site's domains: each domain's `user_count` is set to exactly the number of `admin_users` actually attached to it (`domain_id`), not a post-hoc estimate.
* **Business-process cartography (per perimeter)** — not a target count either, and generated unconditionally for every perimeter (independent of any `--applications`/`--sites`/... option): every *perimeter* gets 3–5 macro-processes (`macro_processes_per_perimeter`), each with 3–10 processes (`processes_per_macro_process`, `macroprocess_id`); each *process* gets 5–10 dedicated activities (`activities_per_process`, via `activity_process`); each *activity* gets 1–3 dedicated operations (`operations_per_activity`, via `activity_operation`) — each operation also inherits the *same* `process_id` as its parent activity, since `Operation` has both a direct FK to `Process` and a many-to-many with `Activity`; and each *operation* gets 1–3 dedicated tasks (`tasks_per_operation`, via `operation_task`). Nothing at any level is shared between two parents — each activity/operation/task belongs to exactly one process/activity/operation.
* **Actors** — every perimeter gets 5–20 actors (`actors_per_perimeter`), each one assigned to 1–5 operations (`actor_operations_per_actor`) **from that same perimeter** (`actor_operation`) — a genuine many-to-many, unlike the dedicated parent/child links above.
* **Informations** — every perimeter gets 5–20 `Information` records (`informations_per_perimeter`). No relation is created with processes — see below for its links to databases and flows.
* **Information ↔ Database and Information ↔ Flow links** — every database gets 1–5 informations (`informations_per_database`, `database_information`), and every application flow gets 0–2 informations (`informations_per_flow`, `application_flow_information`) — both drawn **from that same perimeter** as the database/flow.
* **Entities and relations** — every perimeter gets a fixed 100 entities (`entities_per_perimeter`, a plain count rather than a range — "about a hundred" per the request, not a min/max). Each entity is then linked to 0–3 other entities **from that same perimeter** (`relations_per_entity`), never to itself — unlike `ApplicationFlow`, `Relation` has no cross-perimeter scope, so both ends of a relation always stay within one perimeter. An entity alone in its perimeter (shouldn't happen at 100 per perimeter, but handled defensively) simply gets no relation rather than a self-loop.
* **Data processing (GDPR register)** — every perimeter gets 20–50 `DataProcessing` records (`data_processing_per_perimeter`), each one linked to 1–3 applications, 1–3 processes, and 1–3 informations (`data_processing_links_per_type`, three independent pivots) — all drawn **from that same perimeter**. Exactly one GDPR Art. 6 legal basis is picked per record, reflected consistently in both `legal_basis` and the matching `lawfulness_*` boolean column (the other five stay `false`).
* **Attribute tags** — every generated cartography object (applications, servers, sites, networks, buildings, workstations, ...) gets 3–5 free-form tags in its `attributes` field, drawn from a fixed set: `OK`, `Checked`, `Dev`, `Test`, `T1`, `T2`, `T3`, `O1`, `Saved`, `A1`–`A5`, `Sec`, `Lab` (space-separated, matching the convention already used elsewhere in Mercator for this field).

Every model already existing in Mercator (`app/Factories/*Factory.php`) provides the underlying fake attributes — this command adds the perimeter distribution and the relationships on top.

---

## Multi-perimeter access

As soon as the run ends up with **more than one perimeter** (existing + newly created), the command:

1. Enables the perimeters feature itself (same flag as **Admin → Configuration → Perimeters**) — so the perimeter selector is immediately usable, with no separate manual step.
2. Creates one administrator role per perimeter, titled `admin.perimeter.<slug>` (e.g. `admin.perimeter.filiale-nord`), holding **every** permission.
3. If the canonical administrator account (`admin@admin.com`) exists, attaches it to every one of these new roles — without ever removing its existing roles — so it can immediately switch into any generated perimeter. If that account doesn't exist, this step is silently skipped (nothing else is affected).

This whole step is skipped entirely when the run only ever has a single perimeter (e.g. the `pme` scenario) — there is nothing to switch between. It is idempotent: running the command again against the same perimeters reuses the existing roles (and refreshes their permissions) instead of duplicating them, and re-attaching the admin account is a no-op if it's already there.

---

## Safety

* Everything is written inside a single database transaction: if anything fails partway through, nothing is persisted.
* `--dry-run` performs no write of any kind — it only reports what *would* be created (including whether the perimeters feature would be enabled).
* `--force` only bypasses the confirmation prompt; it never deletes or overwrites existing records.
* Enabling the perimeters feature and attaching `admin@admin.com` to the new roles (see above) is the one case where this command changes application-wide behaviour, not just adds rows — it only happens when the resulting perimeter count is greater than one, and is announced before the confirmation prompt.

---

## Example output

```
+---------------------------------------------------------+-------------------+
| Objet                                                   | Quantité demandée |
+---------------------------------------------------------+-------------------+
| Périmètres (total)                                      | 4                 |
| Applications                                            | 400               |
| Groupes applicatifs par périmètre (~24 au total)        | 5-7               |
| Services applicatifs par application (~1000 au total)   | 0-5               |
| Modules applicatifs par service (~1500 au total)        | 0-3               |
| Bases de données                                        | 100               |
| Serveurs logiques                                       | 200               |
| Flux applicatifs (application/service/module/BDD)       | 400               |
| Sites                                                   | 6                 |
| Réseaux (= sites)                                       | 6                 |
| Sous-réseaux par réseau (~60 au total)                  | 4-16              |
| VLANs (= sous-réseaux)                                  | 60                |
| Bâtiments                                               | 15                |
| Baies                                                   | 40                |
| Étages par site (~27 au total)                          | 1-8               |
| Locaux par étage (~203 au total)                        | 5-10              |
| Switchs physiques (= baies)                             | 40                |
| Switchs physiques (= étages)                            | 27                |
| Routeurs physiques (<= sites)                           | 6                 |
| Serveurs physiques                                      | 80                |
| Périphériques                                           | 40                |
| Postes de travail (dans un local)                       | 300               |
| Téléphones (= postes de travail, dans un local)         | 300               |
| Bornes WiFi (~80% des étages/locaux)                    | 184               |
| Zones de sécurité                                       | 8                 |
| Zones d'administration par site (~12 au total)          | 1-3               |
| Annuaires (= sites)                                     | 6                 |
| Forêts Active Directory / LDAP (= sites)                | 6                 |
| Domaines par site (~24 au total)                        | 3-5               |
| Utilisateurs admin_users (= postes de travail)          | 300               |
| Macro-processus par périmètre (~16 au total)            | 3-5               |
| Processus par macro-processus (~104 au total)           | 3-10              |
| Activités par processus (~780 au total)                 | 5-10              |
| Opérations par activité (~1560 au total)                | 1-3               |
| Tâches par opération (~3120 au total)                   | 1-3               |
| Acteurs par périmètre (~50 au total)                    | 5-20              |
| Informations par périmètre (~50 au total)               | 5-20              |
| Entités par périmètre (400 au total)                    | 100               |
| Relations par entité (~600 au total)                    | 0-3               |
| Traitements de données par périmètre (~140 au total)    | 20-50             |
+---------------------------------------------------------+-------------------+
Plus d'un périmètre : la fonctionnalité périmètres sera activée, un rôle admin.perimeter.<nom> sera créé pour chacun, et le compte admin@admin.com (s'il existe) y sera rattaché.

+-----------------------------------------------------+---------+
| Résultat                                             | Valeur  |
+-----------------------------------------------------+---------+
| Périmètres (total / créés)                           | 4 / 3   |
| Fonctionnalité périmètres activée                    | Oui     |
| Rôles admin.perimeter.* créés                        | 4       |
| Compte admin@admin.com rattaché aux rôles            | Non     |
| Applications créées                                  | 400     |
| Groupes applicatifs créés                            | 22      |
| Services applicatifs créés                           | 1010    |
| Liens applications <-> services applicatifs          | 1010    |
| Modules applicatifs créés                            | 1475    |
| Liens services applicatifs <-> modules applicatifs   | 1475    |
| Bases de données créées                              | 100     |
| Serveurs logiques créés                              | 200     |
| Liens serveurs logiques <-> serveurs physiques       | 200     |
| Liens applications <-> serveurs                      | 470     |
| Liens applications <-> bases de données              | 400     |
| Flux applicatifs créés                               | 400     |
| Sites créés                                          | 6       |
| Réseaux créés                                        | 6       |
| Sous-réseaux créés                                   | 67      |
| VLANs créés                                          | 67      |
| Bâtiments créés                                      | 15      |
| Baies créées                                         | 40      |
| Étages créés                                         | 33      |
| Locaux créés                                         | 239     |
| Switchs physiques créés (baies)                      | 40      |
| Switchs physiques créés (étages)                     | 33      |
| Routeurs physiques créés                             | 6       |
| Serveurs physiques créés                             | 80      |
| Liens serveurs <-> switchs                           | 80      |
| Liens switchs de baie <-> routeurs                   | 40      |
| Liens switchs d'étage <-> routeurs                   | 33      |
| Périphériques créés                                  | 40      |
| Postes de travail créés                              | 300     |
| Liens postes de travail <-> switchs                  | 300     |
| Téléphones créés                                     | 300     |
| Bornes WiFi créées                                   | 215     |
| Liens bornes WiFi <-> switchs                        | 215     |
| Zones de sécurité créées                             | 8       |
| Liens zones <-> bâtiments                            | 11      |
| Zones d'administration créées                        | 12      |
| Annuaires créés                                      | 6       |
| Forêts Active Directory / LDAP créées                | 6       |
| Domaines créés                                       | 26      |
| Liens forêts AD <-> domaines                         | 26      |
| Utilisateurs admin_users créés                       | 300     |
| Macro-processus créés                                | 17      |
| Processus créés                                      | 124     |
| Liens processus <-> activités                        | 924     |
| Activités créées                                     | 924     |
| Liens activités <-> opérations                       | 1830    |
| Opérations créées                                    | 1830    |
| Liens opérations <-> tâches                          | 3643    |
| Tâches créées                                        | 3643    |
| Acteurs créés                                        | 65      |
| Liens acteurs <-> opérations                         | 206     |
| Informations créées                                  | 52      |
| Liens bases de données <-> informations              | 299     |
| Liens flux applicatifs <-> informations              | 390     |
| Entités créées                                       | 400     |
| Relations créées                                     | 611     |
| Traitements de données créés                         | 166     |
| Liens traitements de données <-> applications        | 342     |
| Liens traitements de données <-> processus           | 320     |
| Liens traitements de données <-> informations        | 338     |
+-----------------------------------------------------+---------+
```

All 200 logical servers got a physical server + a site-appropriate IP (200/200): with 80 physical servers spread across 4 perimeters, every perimeter had at least one to pick from.

Note every workstation and every WiFi access point got a link (300/300 and 215/215): as long as every site has at least one bay (hence a router isn't even required here — floor switches always exist for every floor regardless of routers), workstations/WiFi always find their floor's switch. Also note every subnetwork has exactly one VLAN (67/67), every application got 1–3 logical servers with ~90% getting exactly one, every application block belongs to the same perimeter as the applications assigned to it (22 blocks across 4 perimeters, 5–7 each), every application service/module is dedicated to exactly one application/service (1010 services for 1010 links, 1475 modules for 1475 links), every flow's two endpoints resolve to an existing application/service/module/database with no self-loop, every admin zone/directory/AD forest/domain/admin_user chains coherently within its site's perimeter (12 admin zones and 26 domains across 6 sites, 300 admin_users exactly matching the 300 workstations created), and the whole business-process chain (924 activities for 924 process links, 1830 operations for 1830 activity links, 3643 tasks for 3643 operation links) stays within its perimeter end to end, with every operation inheriting the same process as its parent activity. Every perimeter also got exactly 100 entities (400/400 across 4 perimeters), and every one of the 611 relations created connects two entities of the same perimeter with no self-loop. Every database got 1–5 informations (299 links across 100 databases) and every flow got 0–2 (390 links across 400 flows), both always from the same perimeter. Every one of the 166 data processing records (a GDPR register) got 1–3 links each to applications, processes and informations (342/320/338 links respectively), all within the same perimeter, and each one's `legal_basis` matched exactly one true `lawfulness_*` flag.
