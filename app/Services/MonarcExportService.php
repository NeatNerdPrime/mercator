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
use Illuminate\Support\Str;

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
 * relations, recursively; a support with NO selected parent is grouped under
 * a synthetic per-family container object (generic "CONT" asset, 0 AMV).
 * A support reachable from several selected parents gets one instance
 * PLACEMENT per parent (all referencing the SAME library object uuid) —
 * this is what makes local-scope objects countable "N times" while a
 * global-scope object is still counted once regardless of placements.
 */
class MonarcExportService
{
    /** Mercator model short name -> MONARC library category label. */
    private const CATEGORY_LABELS = [
        'Entity' => 'Organisation',
        'Relation' => 'Organisation',
        'MacroProcessus' => 'Processus',
        'Process' => 'Processus',
        'Information' => 'Informations',
        'Application' => 'Applications',
        'ApplicationService' => 'Applications',
        'ApplicationBlock' => 'Applications',
        'ApplicationModule' => 'Applications',
        'Database' => 'Applications',
        'LogicalServer' => 'Serveurs',
        'Container' => 'Serveurs',
        'Cluster' => 'Serveurs',
        'PhysicalServer' => 'Serveurs',
        'Workstation' => 'Serveurs',
        'Backup' => 'Serveurs',
        'Network' => 'Réseaux',
        'Lan' => 'Réseaux',
        'Man' => 'Réseaux',
        'Wan' => 'Réseaux',
        'Subnetwork' => 'Réseaux',
        'Vlan' => 'Réseaux',
        'NetworkSwitch' => 'Réseaux',
        'Router' => 'Réseaux',
        'SecurityDevice' => 'Réseaux',
        'DhcpServer' => 'Réseaux',
        'Dnsserver' => 'Réseaux',
        'Gateway' => 'Réseaux',
        'Site' => 'Sites et bâtiments',
        'Building' => 'Sites et bâtiments',
        'Bay' => 'Sites et bâtiments',
        'Zone' => 'Sites et bâtiments',
        'PhysicalSwitch' => 'Matériel',
        'PhysicalRouter' => 'Matériel',
        'PhysicalSecurityDevice' => 'Matériel',
        'WifiTerminal' => 'Matériel',
        'StorageDevice' => 'Matériel',
        'Peripheral' => 'Matériel',
        'Phone' => 'Matériel',
        'ExternalConnectedEntity' => 'Organisation',
        'Domain' => 'Organisation',
        'ForestAd' => 'Organisation',
        'Annuaire' => 'Organisation',
        'ZoneAdmin' => 'Organisation',
        'AdminUser' => 'Personnel',
        'Actor' => 'Personnel',
    ];

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
     * intentionally left unmapped: CONT (reserved for the synthetic orphan
     * containers built by this class, see CONTAINER_ASSET_UUID), MAT_MOB,
     * OV_DEVELOPPEMENT, OV_INFOPHY, OV_MAINTENANCE, OV_MOBIL, PER_DEV —
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
     * Canonical MONARC base-library "Conteneur" asset (type=1 primaire, no AMV),
     * confirmed present under this exact uuid on every stock MONARC instance —
     * used for the synthetic per-family containers in the hybrid instance tree.
     */
    private const CONTAINER_ASSET_UUID = 'd2023c8f-44d1-11e9-a78c-0800277f0571';

    /** @var array<string, array<int, array<int, string>>>|null lazily computed, see relationsMap() */
    private ?array $relationsMapCache = null;

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
            [$instances, $containerCategories] = $this->buildAnalysisTree($index, $assetsByUuid, $amvsByAssetUuid, $languageCode);
            $categories = array_merge($categories, $containerCategories);

            if ($containerCategories !== []) {
                $assetsByUuid[self::CONTAINER_ASSET_UUID] ??= $this->containerAsset($languageCode);
            }
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
     * Indexes the selection by "Model:id" and assigns each item a fresh
     * library-object uuid (shared by every instance placement of that item).
     *
     * @return array<string, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int, uuid: string}>
     */
    private function indexSelection(array $selection): array
    {
        $index = [];
        foreach ($selection as $item) {
            $key = $item['model'].':'.$item['id'];
            $index[$key] = $item + ['uuid' => (string) Str::uuid()];
        }

        return $index;
    }

    /**
     * Number of instance placements each selected item will get in the
     * hybrid analysis tree: primaries are always exactly 1 (they're always
     * roots); a support with no selected parent is 1 (grouped under its
     * family container); a support reachable from N distinct selected
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
     * Library objects are always flat (verified against a live MONARC 2.13.3
     * instance: a non-empty "children" on a library object crashes the real
     * importer — ObjectCategoryImportProcessor::processObjectCategoryData()
     * is invoked with a null category for nested objects). The reference
     * fixture itself never populates object-level "children" either. The
     * cartography's parent/child composition is only meaningful for the
     * "instances" tree in analysis mode (see buildAnalysisTree()).
     *
     * @return array<int, array{label: string, children: array, objects: array, position: int, isRoot: int}>
     */
    private function buildLibrary(array $index, array $assetsByUuid): array
    {
        $byCategory = [];
        foreach ($index as $entry) {
            $label = self::CATEGORY_LABELS[$entry['model']] ?? 'Autres';
            $byCategory[$label][] = [
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

        ksort($byCategory, SORT_STRING | SORT_FLAG_CASE);

        $categories = [];
        $position = 1;
        foreach ($byCategory as $label => $objects) {
            $categories[] = [
                'label' => $label,
                'children' => [],
                'objects' => $objects,
                'position' => $position++,
                'isRoot' => 1,
            ];
        }

        return $categories;
    }

    /**
     * Builds the hybrid (option C) instance tree: selected primaries as
     * roots, their selected supports composed underneath following the
     * cartography's relations (a support reachable from several selected
     * parents gets one instance placement per parent, all pointing at the
     * SAME library object uuid), and parentless supports grouped under a
     * synthetic per-family container instance (also added to the library,
     * hence the second return value).
     *
     * Validated against a live Monarc 2.13.3 instance: importing a generated
     * "analysis" export reproduced the exact composition tree (levels,
     * scopes, asset codes) and the target ANR's own risks-dashboard reported
     * the same risk count as countRisks() computed beforehand.
     *
     * @return array{0: array, 1: array}
     */
    private function buildAnalysisTree(array $index, array $assetsByUuid, array $amvsByAssetUuid, string $languageCode): array
    {
        $childrenByParent = [];
        $orphanKeys = [];

        foreach ($index as $key => $entry) {
            if (in_array($entry['model'], self::PRIMARY_FAMILIES, true)) {
                continue; // primaries are never placed as someone else's child
            }

            $parents = $this->resolveParentKeys($entry['model'], (int) $entry['id'], $index);

            if ($parents === []) {
                $orphanKeys[] = $key;

                continue;
            }

            foreach ($parents as $parentKey) {
                $childrenByParent[$parentKey][] = $key;
            }
        }

        $instances = [];
        foreach ($index as $key => $entry) {
            if (in_array($entry['model'], self::PRIMARY_FAMILIES, true)) {
                $instances[] = $this->buildInstance($key, $index, $childrenByParent, $assetsByUuid, $amvsByAssetUuid, 1);
            }
        }

        $orphansByFamily = [];
        foreach ($orphanKeys as $key) {
            $label = self::CATEGORY_LABELS[$index[$key]['model']] ?? 'Autres';
            $orphansByFamily[$label][] = $key;
        }

        $containerCategories = [];
        $containerAsset = $this->containerAsset($languageCode);
        $position = 100; // after the real families built by buildLibrary()

        foreach ($orphansByFamily as $label => $keys) {
            $containerUuid = (string) Str::uuid();

            $containerCategories[] = [
                'label' => $label.' (conteneur)',
                'children' => [],
                'objects' => [[
                    'uuid' => $containerUuid,
                    'name' => $label,
                    'label' => $label,
                    'mode' => 0,
                    'scope' => 2,
                    'asset' => $containerAsset,
                    'rolfTag' => null,
                    'children' => [],
                ]],
                'position' => $position++,
                'isRoot' => 1,
            ];

            $instances[] = [
                'name' => $label,
                'label' => $label,
                'level' => 1,
                'position' => 1,
                'confidentiality' => -1,
                'integrity' => -1,
                'availability' => -1,
                'isConfidentialityInherited' => 0,
                'isIntegrityInherited' => 0,
                'isAvailabilityInherited' => 0,
                'asset' => $containerAsset,
                'object' => ['uuid' => $containerUuid],
                'instanceMetadata' => [],
                'instanceRisks' => [],
                'operationalInstanceRisks' => [],
                'instancesConsequences' => [],
                'children' => array_map(
                    fn (string $childKey) => $this->buildInstance($childKey, $index, $childrenByParent, $assetsByUuid, $amvsByAssetUuid, 2),
                    $keys
                ),
            ];
        }

        return [$instances, $containerCategories];
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

    private function containerAsset(string $languageCode): array
    {
        return [
            'uuid' => self::CONTAINER_ASSET_UUID,
            'code' => 'CONT',
            'label' => $languageCode === 'en' ? 'Container' : 'Conteneur',
            'description' => $languageCode === 'en' ? 'Asset container' : "Conteneur d'actifs",
            'type' => 1,
            'status' => 1,
        ];
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
