<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Application;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Backup;
use App\Models\Bay;
use App\Models\Building;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\Database;
use App\Models\Domain;
use App\Models\ExternalConnectedEntity;
use App\Models\Information;
use App\Models\LogicalServer;
use App\Models\Man;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalRouter;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\PhysicalSwitch;
use App\Models\Process;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\Vlan;
use App\Models\Wan;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Models\Zone;
use Ramsey\Uuid\Uuid;

/**
 * Builds a MONARC v2.13 "anr" export file (tests/fixtures/templates/monarc.json
 * is the reference format) from a Mercator cartography selection.
 *
 * Verified against a live MONARC 2.13.3 instance: a POST /api/client-anr/{id}/export
 * with body {"assetsLibrary":true,"knowledgeBase":true} produces a root JSON with
 * EXACTLY the keys/order built below, withEval=false and all its dependent flags
 * false — this is the baseline shape this class reproduces for the "library" mode.
 *
 * Analysis mode builds a "hybrid" instance tree (option C): primary assets
 * (MacroProcessus/Process/Information) are always roots; their selected
 * supports are composed underneath them following the cartography's own
 * relations, recursively; a support with NO selected parent is promoted to
 * its own root instance, same as a primary (no synthetic wrapper object).
 * A support reachable from several selected parents gets one instance
 * PLACEMENT per parent (all referencing the SAME library object uuid) —
 * this is what makes local-scope objects countable "N times" while a
 * global-scope object is still counted once regardless of placements.
 */
class MonarcExportService
{
    /**
     * Default Mercator model short name -> Monarc knowledgeBase asset code(s),
     * confirmed against the standard MONARC base library (base ANR knowledgeBase,
     * 42 codes total). Each model maps to one or more codes: a model listed
     * under several codes becomes selectable in every one of those codes' rows
     * (e.g. a Building may be a plain premises — BAT_LOC — or, in the same
     * cartography, the one that also happens to be the datacenter —
     * OV_SALLE_IT); it is the row the object is actually placed under that
     * decides which asset_uuid it exports with, never this map directly (see
     * MonarcController::flattenRowSelection()).
     * A handful of base codes have no Mercator equivalent and are
     * intentionally left unmapped: CONT, MAT_MOB, OV_DEVELOPPEMENT,
     * OV_INFOPHY, OV_MAINTENANCE, OV_MOBIL, PER_DEV —
     * Mercator's cartography does not model paper documents, dev projects,
     * maintenance contracts, or a distinct portable-hardware form factor
     * (Workstation covers desktops and laptops alike) as discrete objects,
     * and no model's own description evokes software development activity
     * closely enough to justify PER_DEV.
     * If a code is absent from the source ANR's knowledgeBase, the caller must
     * fall back to an empty/manual selection — this class never invents one.
     *
     * @var array<string, array<int, string>>
     */
    public const DEFAULT_ASSET_CODES = [
        'Entity' => ['ORG_GEN'],
        'Relation' => ['OV_MAINTENANCE'],

        'MacroProcessus' => ['SERV', 'SERV_ESS'],
        'Process' => ['PROC', 'SERV', 'SERV_ESS'],
        'Information' => ['INFO', 'OV_INFOPHY'],

        'Application' => ['LOG_APP'],
        'ApplicationService' => ['LOG_SRV', 'SYS_MES', 'SYS_ITR', 'SYS_WEB'],
        'ApplicationBlock' => ['OV_LOGICIEL'],
        'ApplicationModule' => ['LOG_STD'],
        'Database' => ['LOG_SRV'],
        'LogicalServer' => ['LOG_OS'],
        'Container' => ['LOG_OS'],
        'Cluster' => ['OV_SERVEUR'],
        'PhysicalServer' => ['OV_SERVEUR'],
        'Workstation' => ['OV_POSTE_FIXE', 'MAT_MOB', 'OV_MOBIL'],
        'Backup' => ['OV_BACKUP'],
        'Network' => ['OV_RESEAU'],
        'Lan' => ['OV_RESEAU'],
        'Man' => ['RESEAU'],
        'Wan' => ['RESEAU'],
        'Subnetwork' => ['RESEAU'],
        'Vlan' => ['RESEAU'],
        'NetworkSwitch' => ['RESEAU'],
        'Router' => ['RESEAU'],
        'SecurityDevice' => ['RESEAU'],
        'DhcpServer' => ['LOG_SRV'],
        'Dnsserver' => ['LOG_SRV'],
        'Gateway' => ['SYS_INT'],
        'Site' => ['BAT_LOC'],
        'Building' => ['BAT_LOC', 'OV_SALLE_IT'],
        'Zone' => ['OV_BATI'],
        'PhysicalSwitch' => ['MAT_FIX'],
        'PhysicalRouter' => ['MAT_FIX'],
        'PhysicalSecurityDevice' => ['MAT_FIX'],
        'WifiTerminal' => ['MAT_FIX'],
        'StorageDevice' => ['MAT_SUPP'],
        'Peripheral' => ['MAT_PERI', 'OV_MULTI_IMPRIMANTE'],
        'Phone' => ['MAT_NELE'],
        'ExternalConnectedEntity' => ['ORG_EXT'],
        'Domain' => ['OV_ORGANISATION'],
        'ForestAd' => ['OV_ORGANISATION'],
        'Annuaire' => ['SYS_ANU'],
        'ZoneAdmin' => ['OV_ORGANISATION'],
        'AdminUser' => ['PER_EXP'],
        'Actor' => ['PER', 'OV_UTIL', 'PER_DEC', 'PER_UTI', 'PER_DEV'],
    ];

    /** Mercator families whose objects are "primary assets" — always instance roots. */
    public const PRIMARY_FAMILIES = ['MacroProcessus', 'Process', 'Information'];

    /**
     * Mercator "views" (resources/views/partials/sidebar.blade.php submenus),
     * used to group Monarc library objects into matching top-level categories
     * (see buildLibrary()) and to group the cartography-selection screen's
     * rows (see MonarcController::rowsByView()) — the single source of truth
     * for both, so the two never drift apart.
     *
     * @var array<string, array<int, string>>
     */
    public const FAMILY_VIEWS = [
        'ecosystem' => ['Entity', 'Relation'],
        'information_system' => ['MacroProcessus', 'Process', 'Actor', 'Information'],
        'applications' => ['ApplicationBlock', 'Application', 'ApplicationService', 'ApplicationModule', 'Database'],
        'administration' => ['Domain', 'ForestAd', 'Annuaire', 'ZoneAdmin', 'AdminUser'],
        'logical_infrastructure' => ['Network', 'SubNetwork', 'LogicalServer', 'Cluster', 'Container', 'Backup', 'Network', 'Subnetwork', 'Gateway', 'Router', 'NetworkSwitch', 'SecurityDevice', 'DhcpServer', 'Dnsserver', 'Vlan', 'ExternalConnectedEntity'],
        'physical_infrastructure' => ['Site', 'Building', 'Bay', 'Zone', 'PhysicalServer', 'PhysicalSwitch', 'PhysicalRouter', 'Workstation', 'StorageDevice', 'Peripheral', 'Phone', 'WifiTerminal', 'PhysicalSecurityDevice', 'Wan', 'Man', 'Lan'],
    ];

    /**
     * Mercator model short name -> translation key for its family label (see
     * MonarcController::loadMercatorFamilies(), which shares this mapping),
     * used to name a view's per-family sub-category in buildLibrary() when
     * more than one object of that family is selected. Kept as trans() KEYS
     * (not resolved values) so this class constant never needs a request
     * context to be defined.
     *
     * @var array<string, string>
     */
    private const FAMILY_LABEL_KEYS = [
        'MacroProcessus' => 'cruds.macroProcessus.title',
        'Process' => 'cruds.process.title',
        'Information' => 'cruds.information.title',
        'Actor' => 'cruds.actor.title',
        'Application' => 'cruds.application.title',
        'ApplicationService' => 'cruds.applicationService.title',
        'ApplicationBlock' => 'cruds.applicationBlock.title',
        'ApplicationModule' => 'cruds.applicationModule.title',
        'Database' => 'cruds.database.title',
        'LogicalServer' => 'cruds.logicalServer.title',
        'Cluster' => 'cruds.cluster.title',
        'Container' => 'cruds.container.title',
        'Backup' => 'cruds.backup.title',
        'Network' => 'cruds.network.title',
        'Subnetwork' => 'cruds.subnetwork.title',
        'Gateway' => 'cruds.gateway.title',
        'Router' => 'cruds.router.title',
        'NetworkSwitch' => 'cruds.networkSwitch.title',
        'SecurityDevice' => 'cruds.securityDevice.title',
        'DhcpServer' => 'cruds.dhcpServer.title',
        'Dnsserver' => 'cruds.dnsserver.title',
        'Vlan' => 'cruds.vlan.title',
        'ExternalConnectedEntity' => 'cruds.externalConnectedEntity.title',
        'Lan' => 'cruds.lan.title',
        'Man' => 'cruds.man.title',
        'Wan' => 'cruds.wan.title',
        'PhysicalServer' => 'cruds.physicalServer.title',
        'Workstation' => 'cruds.workstation.title',
        'Site' => 'cruds.site.title',
        'Building' => 'cruds.building.title',
        'Bay' => 'cruds.bay.title',
        'PhysicalSwitch' => 'cruds.physicalSwitch.title',
        'PhysicalRouter' => 'cruds.physicalRouter.title',
        'PhysicalSecurityDevice' => 'cruds.physicalSecurityDevice.title',
        'StorageDevice' => 'cruds.storageDevice.title',
        'Peripheral' => 'cruds.peripheral.title',
        'Phone' => 'cruds.phone.title',
        'WifiTerminal' => 'cruds.wifiTerminal.title',
        'Zone' => 'cruds.zone.title',
        'Entity' => 'cruds.entity.title',
        'Relation' => 'cruds.relation.title',
        'Domain' => 'cruds.domain.title',
        'ForestAd' => 'cruds.forestAd.title',
        'Annuaire' => 'cruds.annuaire.title',
        'ZoneAdmin' => 'cruds.zoneAdmin.title',
        'AdminUser' => 'cruds.adminUser.title',
    ];

    /**
     * The Mercator "view" a family belongs to (see FAMILY_VIEWS), or 'other'
     * for a family mapped to none — shared by the selection screen
     * (MonarcController::rowsFromAssets()) and the library grouping below.
     */
    public static function viewForFamily(string $model): string
    {
        foreach (self::FAMILY_VIEWS as $viewKey => $models) {
            if (in_array($model, $models, true)) {
                return $viewKey;
            }
        }

        return 'other';
    }

    /** A family's translated display label (e.g. "Postes de travail" for Workstation). */
    public static function familyLabel(string $model): string
    {
        $key = self::FAMILY_LABEL_KEYS[$model] ?? null;

        return $key !== null ? trans($key) : $model;
    }

    /**
     * Fixed namespace for the uuid v5 identities generated by objectUuid()
     * below — NEVER change this value: it is the root of every deterministic
     * uuid this class emits, and changing it would make every
     * previously-synced Monarc library object look "new" again (see
     * MonarcSyncService, which relies on these uuids staying stable across
     * two exports of the same Mercator object to compute its diff).
     */
    private const NAMESPACE_UUID = 'c0e94327-c048-4529-9f9c-f9450ef3c96e';

    /** @var array<string, array<int, array<int, string>>>|null lazily computed, see relationsMap() */
    private ?array $relationsMapCache = null;

    /**
     * Deterministic (uuid v5) library-object uuid for a given Mercator
     * object — stable across two exports of the same object, which is what
     * lets MonarcSyncService diff a cartography against what was already
     * imported into a Monarc ANR without ever producing a duplicate.
     */
    public static function objectUuid(string $model, int $id): string
    {
        return Uuid::uuid5(self::NAMESPACE_UUID, "{$model}:{$id}")->toString();
    }

    /**
     * @param  string  $mode  'library' | 'analysis'
     * @param  string  $name  Reserved for the caller (e.g. download filename) —
     *                        not part of the Monarc export file format itself,
     *                        confirmed against the fixture: the root has no
     *                        name/description key.
     * @param  array  $knowledgeBase  as returned by MonarcApiService::getKnowledgeBase()
     *                                or MospToMonarcConverter::convert()
     * @param  array  $scalesAndMethod  keys: monarc_version, scales, operationalRiskScales,
     *                                  method, thresholds, soas, soaScaleComments
     * @param  array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>  $selection
     */
    public function buildExport(
        string $mode,
        string $name,
        string $description,
        string $languageCode,
        array $knowledgeBase,
        array $scalesAndMethod,
        array $selection
    ): array {
        $assetsByUuid = collect($knowledgeBase['assets'] ?? [])->keyBy('uuid')->all();
        $amvsByAssetUuid = collect($knowledgeBase['informationRisks'] ?? [])
            ->filter(fn (array $amv) => isset($amv['asset']['uuid']))
            ->groupBy(fn (array $amv) => $amv['asset']['uuid'])
            ->all();
        $index = $this->indexSelection($selection);

        $categories = $this->buildLibrary($index, $assetsByUuid);
        $instances = [];

        if ($mode === 'analysis') {
            $instances = $this->buildAnalysisTree($index, $assetsByUuid, $amvsByAssetUuid);
        }

        return [
            'type' => 'anr',
            'monarc_version' => $scalesAndMethod['monarc_version'] ?? 'unknown',
            'exportDatetime' => now()->format('Y-m-d H:i:s'),
            'withEval' => false,
            'withControls' => false,
            'withRecommendations' => false,
            'withMethodSteps' => ! empty($scalesAndMethod['method']),
            'withInterviews' => false,
            'withSoas' => ! empty($scalesAndMethod['soas']),
            'withRecords' => false,
            'withLibrary' => true,
            'withKnowledgeBase' => true,
            'languageCode' => $languageCode,
            'languageIndex' => $languageCode === 'en' ? 2 : 1,
            'knowledgeBase' => [
                'assets' => array_values($assetsByUuid),
                'threats' => $knowledgeBase['threats'] ?? [],
                'vulnerabilities' => $knowledgeBase['vulnerabilities'] ?? [],
                'referentials' => $knowledgeBase['referentials'] ?? [],
                'informationRisks' => $knowledgeBase['informationRisks'] ?? [],
                'rolfTags' => $knowledgeBase['rolfTags'] ?? [],
                'operationalRisks' => $knowledgeBase['operationalRisks'] ?? [],
                'recommendationSets' => $knowledgeBase['recommendationSets'] ?? [],
            ],
            'library' => [
                'categories' => $categories,
            ],
            'instances' => $instances,
            'anrInstanceMetadataFields' => [],
            'scales' => $scalesAndMethod['scales'] ?? [],
            'operationalRiskScales' => $scalesAndMethod['operationalRiskScales'] ?? [],
            'soaScaleComments' => $scalesAndMethod['soaScaleComments'] ?? [],
            'soas' => $scalesAndMethod['soas'] ?? [],
            'method' => $scalesAndMethod['method'] ?? [],
            'thresholds' => $scalesAndMethod['thresholds'] ?? [],
            'interviews' => [],
            'gdprRecords' => [],
        ];
    }

    /**
     * Number of risks the analysis will generate for the given selection:
     * for each object, n = count of knowledgeBase.informationRisks whose
     * asset.uuid matches the object's chosen Monarc asset type.
     *
     * - Global-scope objects count n once, however many times they occur
     *   in the composition tree.
     * - Local-scope objects count n × (number of instance placements) — a
     *   support reachable from two selected parents places two instances.
     * - In 'library' mode there is no instance tree (every object is a flat,
     *   single library entry), so every object counts n once regardless of scope.
     *
     * Operational (rolfTag) risks are out of scope and always contribute 0.
     *
     * @param  array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>  $selection
     */
    public function countRisks(array $knowledgeBase, array $selection, string $mode = 'analysis'): int
    {
        $amvCountByAssetUuid = [];
        foreach ($knowledgeBase['informationRisks'] ?? [] as $amv) {
            $uuid = $amv['asset']['uuid'] ?? null;
            if ($uuid !== null) {
                $amvCountByAssetUuid[$uuid] = ($amvCountByAssetUuid[$uuid] ?? 0) + 1;
            }
        }

        $index = $this->indexSelection($selection);
        $occurrences = $mode === 'analysis' ? $this->computeOccurrences($index) : [];

        $total = 0;
        foreach ($selection as $item) {
            $key = $item['model'].':'.$item['id'];
            $n = $amvCountByAssetUuid[$item['asset_uuid'] ?? null] ?? 0;
            $isGlobal = (int) $item['scope'] === 2;
            $total += $isGlobal ? $n : $n * ($occurrences[$key] ?? 1);
        }

        return $total;
    }

    /**
     * Indexes the selection by "Model:id" and assigns each item a
     * deterministic library-object uuid (shared by every instance placement
     * of that item, and stable across two exports of the same object — see
     * objectUuid()).
     *
     * @return array<string, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int, uuid: string}>
     */
    private function indexSelection(array $selection): array
    {
        $index = [];
        foreach ($selection as $item) {
            $key = $item['model'].':'.$item['id'];
            $index[$key] = $item + ['uuid' => self::objectUuid($item['model'], (int) $item['id'])];
        }

        return $index;
    }

    /**
     * Number of instance placements each selected item will get in the
     * hybrid analysis tree: primaries are always exactly 1 (they're always
     * roots); a support with no selected parent is also 1 (promoted to its
     * own root, same as a primary); a support reachable from N distinct selected
     * parents is the SUM of each of those parents' own occurrence counts
     * (so a support two levels below a twice-placed ancestor is itself
     * placed twice per that branch).
     *
     * @return array<string, int>
     */
    private function computeOccurrences(array $index): array
    {
        $memo = [];
        $visiting = [];
        foreach (array_keys($index) as $key) {
            $this->occurrenceCount($key, $index, $memo, $visiting);
        }

        return $memo;
    }

    private function occurrenceCount(string $key, array $index, array &$memo, array &$visiting): int
    {
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        if (in_array($index[$key]['model'], self::PRIMARY_FAMILIES, true)) {
            return $memo[$key] = 1;
        }

        if (isset($visiting[$key])) {
            return 1; // cycle guard — cartography relations should never loop, but never trust that blindly
        }
        $visiting[$key] = true;

        $parents = $this->resolveParentKeys($index[$key]['model'], (int) $index[$key]['id'], $index);

        if ($parents === []) {
            unset($visiting[$key]);

            return $memo[$key] = 1;
        }

        $sum = 0;
        foreach ($parents as $parentKey) {
            $sum += $this->occurrenceCount($parentKey, $index, $memo, $visiting);
        }
        unset($visiting[$key]);

        return $memo[$key] = $sum;
    }

    /**
     * All selected items acting as a valid cartography parent for the given
     * item — every match at the winning priority level (not just the
     * first), so a shared support gets one placement per selected parent.
     * Backed by buildRelationsMap() (cached per instance) so the server-side
     * tree/count computation and the JS mirror share a single source of
     * truth for the cartography's relations and their priority order.
     *
     * @return array<int, string>
     */
    private function resolveParentKeys(string $model, int $id, array $index): array
    {
        $groups = $this->relationsMap()["{$model}:{$id}"] ?? [];

        foreach ($groups as $group) {
            $matched = array_values(array_filter($group, fn (string $parentKey) => isset($index[$parentKey])));
            if ($matched !== []) {
                return $matched;
            }
        }

        return [];
    }

    private function relationsMap(): array
    {
        return $this->relationsMapCache ??= $this->buildRelationsMap();
    }

    /**
     * Library OBJECTS (the leaf entries under a category's "objects" array)
     * are always flat — verified against a live MONARC 2.13.3 instance: a
     * non-empty "children" on a library OBJECT crashes the real importer
     * (ObjectCategoryImportProcessor::processObjectCategoryData() is invoked
     * with a null category for nested objects). The reference fixture itself
     * never populates object-level "children" either. The cartography's
     * parent/child composition is only meaningful for the "instances" tree
     * in analysis mode (see buildAnalysisTree()) — objects built here (see
     * libraryObject()) always have an empty "children".
     *
     * CATEGORIES (the folders objects sit in) are a different concept and
     * DO nest here: each selected object's Mercator "view" (see
     * FAMILY_VIEWS) becomes a top-level category, and — unlike the object
     * nesting above — a family with more than one selected object gets its
     * own named sub-category underneath (e.g. "Infrastructure physique" ->
     * "Postes de travail" -> Workstation1, Workstation2), while a family
     * with just one object stays directly under its view. Category nesting
     * itself was not independently re-verified live during this change
     * (only the object-level constraint above was) — it mirrors Monarc's own
     * long-standing object-library UI, which is inherently a category tree.
     *
     * @return array<int, array{label: string, children: array, objects: array, position: int, isRoot: int}>
     */
    private function buildLibrary(array $index, array $assetsByUuid): array
    {
        $objectsByViewThenFamily = [];
        foreach ($index as $entry) {
            $view = self::viewForFamily($entry['model']);
            $objectsByViewThenFamily[$view][$entry['model']][] = $this->libraryObject($entry, $assetsByUuid);
        }

        $categories = [];
        $position = 1;

        // View order mirrors the sidebar/selection-screen order (FAMILY_VIEWS
        // declaration order), not alphabetical — consistent with the rest of
        // this feature. A family mapped to no known view ('other') falls
        // back to a single flat "Autres" category, same as before this change.
        foreach ([...array_keys(self::FAMILY_VIEWS), 'other'] as $viewKey) {
            if (! isset($objectsByViewThenFamily[$viewKey])) {
                continue;
            }

            [$directObjects, $subCategories] = $this->splitByFamilyCount($objectsByViewThenFamily[$viewKey]);

            $categories[] = [
                'label' => $viewKey === 'other' ? 'Autres' : trans("panel.menu.{$viewKey}"),
                'children' => $subCategories,
                'objects' => $directObjects,
                'position' => $position++,
                'isRoot' => 1,
            ];
        }

        return $categories;
    }

    /**
     * A view's selected objects, grouped by Mercator family (model): a
     * family with a single object stays a direct object of the view; a
     * family with more than one gets its own named sub-category — see
     * buildLibrary().
     *
     * @param  array<string, array<int, array>>  $objectsByFamily
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function splitByFamilyCount(array $objectsByFamily): array
    {
        uksort($objectsByFamily, fn (string $a, string $b) => mb_strtolower(self::familyLabel($a)) <=> mb_strtolower(self::familyLabel($b)));

        $directObjects = [];
        $subCategories = [];
        $subPosition = 1;

        foreach ($objectsByFamily as $model => $objects) {
            usort($objects, fn (array $a, array $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

            if (count($objects) > 1) {
                $subCategories[] = [
                    'label' => self::familyLabel($model),
                    'children' => [],
                    'objects' => $objects,
                    'position' => $subPosition++,
                    'isRoot' => 0,
                ];
            } else {
                array_push($directObjects, ...$objects);
            }
        }

        usort($directObjects, fn (array $a, array $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        return [$directObjects, $subCategories];
    }

    /** @return array{uuid: string, name: string, label: string, mode: int, scope: int, asset: ?array, rolfTag: null, children: array} */
    private function libraryObject(array $entry, array $assetsByUuid): array
    {
        return [
            'uuid' => $entry['uuid'],
            'name' => $entry['name'],
            'label' => $entry['name'],
            'mode' => 0,
            'scope' => (int) $entry['scope'],
            'asset' => $assetsByUuid[$entry['asset_uuid']] ?? null,
            'rolfTag' => null,
            'children' => [],
        ];
    }

    /**
     * Builds the hybrid (option C) instance tree: selected primaries as
     * roots, their selected supports composed underneath following the
     * cartography's relations (a support reachable from several selected
     * parents gets one instance placement per parent, all pointing at the
     * SAME library object uuid). A support with no selected parent (an
     * "orphan") is promoted to its own root instance, exactly like a
     * primary — Mercator used to wrap orphans in a synthetic per-family
     * "X (conteneur)" library category/asset/instance, but that added a
     * fake object to every export; orphans are now real instances in their
     * own right, with no synthetic wrapper at all.
     *
     * Validated against a live Monarc 2.13.3 instance: importing a generated
     * "analysis" export reproduced the exact composition tree (levels,
     * scopes, asset codes) and the target ANR's own risks-dashboard reported
     * the same risk count as countRisks() computed beforehand.
     */
    private function buildAnalysisTree(array $index, array $assetsByUuid, array $amvsByAssetUuid): array
    {
        $childrenByParent = [];
        $rootKeys = [];

        foreach ($index as $key => $entry) {
            if (in_array($entry['model'], self::PRIMARY_FAMILIES, true)) {
                $rootKeys[] = $key;

                continue; // primaries are never placed as someone else's child
            }

            $parents = $this->resolveParentKeys($entry['model'], (int) $entry['id'], $index);

            if ($parents === []) {
                $rootKeys[] = $key; // orphan: promoted to a root instance, same as a primary

                continue;
            }

            foreach ($parents as $parentKey) {
                $childrenByParent[$parentKey][] = $key;
            }
        }

        return array_map(
            fn (string $key) => $this->buildInstance($key, $index, $childrenByParent, $assetsByUuid, $amvsByAssetUuid, 1),
            $rootKeys
        );
    }

    private function buildInstance(string $key, array $index, array $childrenByParent, array $assetsByUuid, array $amvsByAssetUuid, int $level): array
    {
        $entry = $index[$key];
        $asset = $assetsByUuid[$entry['asset_uuid']] ?? null;

        return [
            'name' => $entry['name'],
            'label' => $entry['name'],
            'level' => $level,
            'position' => 1,
            'confidentiality' => -1,
            'integrity' => -1,
            'availability' => -1,
            'isConfidentialityInherited' => 0,
            'isIntegrityInherited' => 0,
            'isAvailabilityInherited' => 0,
            'asset' => $asset,
            'object' => ['uuid' => $entry['uuid']],
            'instanceMetadata' => [],
            'instanceRisks' => $this->buildInstanceRisks($asset, $amvsByAssetUuid),
            'operationalInstanceRisks' => [],
            'instancesConsequences' => [],
            'children' => array_map(
                fn (string $childKey) => $this->buildInstance($childKey, $index, $childrenByParent, $assetsByUuid, $amvsByAssetUuid, $level + 1),
                $childrenByParent[$key] ?? []
            ),
        ];
    }

    /**
     * Minimal instanceRisks form accepted by InstanceImportProcessor when
     * withEval=false: one entry per AMV of the instance's asset type, holding
     * only the AMV (informationRisk) uuid and an empty recommendations list —
     * evaluation fields are only read by Monarc's importer when withEval=true.
     */
    private function buildInstanceRisks(?array $asset, array $amvsByAssetUuid): array
    {
        if ($asset === null) {
            return [];
        }

        $amvs = $amvsByAssetUuid[$asset['uuid']] ?? [];

        return array_map(
            fn (array $amv) => [
                'informationRisk' => ['uuid' => $amv['uuid']],
                'recommendations' => [],
            ],
            is_array($amvs) ? $amvs : $amvs->all()
        );
    }

    /**
     * Full cartography parent/child adjacency for EVERY Mercator object
     * (not just a selection), for the JS risk-counter mirror embedded in the
     * selection page: "Model:id" -> list of priority GROUPS of candidate
     * parent keys (e.g. a PhysicalServer's groups are [selected LogicalServers],
     * [its Building], [its Site], tried in that order) — the exact same
     * priority-fallback shape resolveParentKeys() uses, so the client can
     * replicate occurrence counting exactly: for each child, take the first
     * group with at least one member present in the current selection.
     *
     * Bulk-loaded (one query per relation, not per object) since this runs
     * over the whole cartography, not a bounded selection.
     *
     * @return array<string, array<int, array<int, string>>>
     */
    public function buildRelationsMap(): array
    {
        $map = [];

        foreach (Process::query()->select('id', 'macroprocess_id')->get() as $process) {
            if ($process->macroprocess_id !== null) {
                $map["Process:{$process->id}"] = [["MacroProcessus:{$process->macroprocess_id}"]];
            }
        }

        foreach (Application::query()->select('id')->with('processes:id')->get() as $application) {
            $parents = $application->processes->map(fn ($p) => "Process:{$p->id}")->all();
            if ($parents !== []) {
                $map["Application:{$application->id}"] = [$parents];
            }
        }

        foreach (ApplicationService::query()->select('id')->with('applications:id')->get() as $service) {
            $parents = $service->applications->map(fn ($a) => "Application:{$a->id}")->all();
            if ($parents !== []) {
                $map["ApplicationService:{$service->id}"] = [$parents];
            }
        }

        foreach (LogicalServer::query()->select('id')->with('applications:id')->get() as $logicalServer) {
            $parents = $logicalServer->applications->map(fn ($a) => "Application:{$a->id}")->all();
            if ($parents !== []) {
                $map["LogicalServer:{$logicalServer->id}"] = [$parents];
            }
        }

        foreach (PhysicalServer::query()->select('id', 'building_id', 'site_id')->with('logicalServers:id')->get() as $physicalServer) {
            $groups = [];
            $logicalServerParents = $physicalServer->logicalServers->map(fn ($ls) => "LogicalServer:{$ls->id}")->all();
            if ($logicalServerParents !== []) {
                $groups[] = $logicalServerParents;
            }
            if ($physicalServer->building_id !== null) {
                $groups[] = ["Building:{$physicalServer->building_id}"];
            }
            if ($physicalServer->site_id !== null) {
                $groups[] = ["Site:{$physicalServer->site_id}"];
            }
            if ($groups !== []) {
                $map["PhysicalServer:{$physicalServer->id}"] = $groups;
            }
        }

        foreach (Workstation::query()->select('id', 'building_id', 'site_id')->with('applications:id')->get() as $workstation) {
            $groups = [];
            $applicationParents = $workstation->applications->map(fn ($a) => "Application:{$a->id}")->all();
            if ($applicationParents !== []) {
                $groups[] = $applicationParents;
            }
            if ($workstation->building_id !== null) {
                $groups[] = ["Building:{$workstation->building_id}"];
            }
            if ($workstation->site_id !== null) {
                $groups[] = ["Site:{$workstation->site_id}"];
            }
            if ($groups !== []) {
                $map["Workstation:{$workstation->id}"] = $groups;
            }
        }

        foreach (Information::query()->select('id')->with('processes:id')->get() as $information) {
            $parents = $information->processes->map(fn ($p) => "Process:{$p->id}")->all();
            if ($parents !== []) {
                $map["Information:{$information->id}"] = [$parents];
            }
        }

        foreach (Building::query()->select('id', 'site_id')->get() as $building) {
            if ($building->site_id !== null) {
                $map["Building:{$building->id}"] = [["Site:{$building->site_id}"]];
            }
        }

        foreach (ApplicationModule::query()->select('id')->with('applicationServices:id')->get() as $module) {
            $parents = $module->applicationServices->map(fn ($s) => "ApplicationService:{$s->id}")->all();
            if ($parents !== []) {
                $map["ApplicationModule:{$module->id}"] = [$parents];
            }
        }

        foreach (Database::query()->select('id')->with('applications:id')->get() as $database) {
            $parents = $database->applications->map(fn ($a) => "Application:{$a->id}")->all();
            if ($parents !== []) {
                $map["Database:{$database->id}"] = [$parents];
            }
        }

        foreach (Cluster::query()->select('id')->with('logicalServers:id')->get() as $cluster) {
            $parents = $cluster->logicalServers->map(fn ($ls) => "LogicalServer:{$ls->id}")->all();
            if ($parents !== []) {
                $map["Cluster:{$cluster->id}"] = [$parents];
            }
        }

        foreach (Container::query()->select('id')->with('logicalServers:id')->get() as $container) {
            $parents = $container->logicalServers->map(fn ($ls) => "LogicalServer:{$ls->id}")->all();
            if ($parents !== []) {
                $map["Container:{$container->id}"] = [$parents];
            }
        }

        foreach (Domain::query()->select('id')->with('forestAds:id')->get() as $domain) {
            $parents = $domain->forestAds->map(fn ($f) => "ForestAd:{$f->id}")->all();
            if ($parents !== []) {
                $map["Domain:{$domain->id}"] = [$parents];
            }
        }

        foreach (AdminUser::query()->select('id', 'domain_id')->get() as $adminUser) {
            if ($adminUser->domain_id !== null) {
                $map["AdminUser:{$adminUser->id}"] = [["Domain:{$adminUser->domain_id}"]];
            }
        }

        foreach (Bay::query()->select('id', 'building_id', 'site_id')->get() as $bay) {
            $groups = [];
            if ($bay->building_id !== null) {
                $groups[] = ["Building:{$bay->building_id}"];
            }
            if ($bay->site_id !== null) {
                $groups[] = ["Site:{$bay->site_id}"];
            }
            if ($groups !== []) {
                $map["Bay:{$bay->id}"] = $groups;
            }
        }

        foreach ([StorageDevice::class, Peripheral::class, PhysicalSwitch::class, PhysicalRouter::class, PhysicalSecurityDevice::class] as $modelClass) {
            foreach ($modelClass::query()->select(['id', 'bay_id', 'building_id', 'site_id'])->get() as $device) {
                $model = class_basename($modelClass);
                $groups = [];
                if ($device->bay_id !== null) {
                    $groups[] = ["Bay:{$device->bay_id}"];
                }
                if ($device->building_id !== null) {
                    $groups[] = ["Building:{$device->building_id}"];
                }
                if ($device->site_id !== null) {
                    $groups[] = ["Site:{$device->site_id}"];
                }
                if ($groups !== []) {
                    $map["{$model}:{$device->id}"] = $groups;
                }
            }
        }

        foreach ([Phone::class, WifiTerminal::class] as $modelClass) {
            foreach ($modelClass::query()->select(['id', 'building_id', 'site_id'])->get() as $device) {
                $model = class_basename($modelClass);
                $groups = [];
                if ($device->building_id !== null) {
                    $groups[] = ["Building:{$device->building_id}"];
                }
                if ($device->site_id !== null) {
                    $groups[] = ["Site:{$device->site_id}"];
                }
                if ($groups !== []) {
                    $map["{$model}:{$device->id}"] = $groups;
                }
            }
        }

        foreach (Man::query()->select('id')->with('lans:id')->get() as $man) {
            $parents = $man->lans->map(fn ($l) => "Lan:{$l->id}")->all();
            if ($parents !== []) {
                $map["Man:{$man->id}"] = [$parents];
            }
        }

        foreach (Wan::query()->select('id')->with(['mans:id', 'lans:id'])->get() as $wan) {
            $groups = [];
            $manParents = $wan->mans->map(fn ($m) => "Man:{$m->id}")->all();
            if ($manParents !== []) {
                $groups[] = $manParents;
            }
            $lanParents = $wan->lans->map(fn ($l) => "Lan:{$l->id}")->all();
            if ($lanParents !== []) {
                $groups[] = $lanParents;
            }
            if ($groups !== []) {
                $map["Wan:{$wan->id}"] = $groups;
            }
        }

        foreach (Subnetwork::query()->select('id', 'network_id')->get() as $subnetwork) {
            if ($subnetwork->network_id !== null) {
                $map["Subnetwork:{$subnetwork->id}"] = [["Network:{$subnetwork->network_id}"]];
            }
        }

        foreach (Vlan::query()->select('id')->with(['networkSwitches:id', 'physicalRouters:id'])->get() as $vlan) {
            $groups = [];
            $switchParents = $vlan->networkSwitches->map(fn ($s) => "NetworkSwitch:{$s->id}")->all();
            if ($switchParents !== []) {
                $groups[] = $switchParents;
            }
            $routerParents = $vlan->physicalRouters->map(fn ($r) => "PhysicalRouter:{$r->id}")->all();
            if ($routerParents !== []) {
                $groups[] = $routerParents;
            }
            if ($groups !== []) {
                $map["Vlan:{$vlan->id}"] = $groups;
            }
        }

        foreach (Backup::query()->select('id')->with('logicalServers:id')->get() as $backup) {
            $parents = $backup->logicalServers->map(fn ($ls) => "LogicalServer:{$ls->id}")->all();
            if ($parents !== []) {
                $map["Backup:{$backup->id}"] = [$parents];
            }
        }

        foreach (ExternalConnectedEntity::query()->select('id', 'entity_id')->get() as $externalEntity) {
            if ($externalEntity->entity_id !== null) {
                $map["ExternalConnectedEntity:{$externalEntity->id}"] = [["Entity:{$externalEntity->entity_id}"]];
            }
        }

        foreach (Zone::query()->select('id')->with('buildings:id')->get() as $zone) {
            $parents = $zone->buildings->map(fn ($b) => "Building:{$b->id}")->all();
            if ($parents !== []) {
                $map["Zone:{$zone->id}"] = [$parents];
            }
        }

        return $map;
    }
}
