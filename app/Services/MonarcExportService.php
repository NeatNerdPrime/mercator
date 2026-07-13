<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationService;
use App\Models\Building;
use App\Models\Information;
use App\Models\LogicalServer;
use App\Models\PhysicalServer;
use App\Models\Process;
use App\Models\Workstation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds a MONARC v2.13 "anr" export file (tests/fixtures/templates/monarc.json
 * is the reference format) from a Mercator cartography selection.
 *
 * Verified against a live MONARC 2.13.3 instance: a POST /api/client-anr/{id}/export
 * with body {"assetsLibrary":true,"knowledgeBase":true} produces a root JSON with
 * EXACTLY the keys/order built below, withEval=false and all its dependent flags
 * false — this is the baseline shape this class reproduces for the "library" mode.
 */
class MonarcExportService
{
    /** Mercator model short name -> MONARC library category label. */
    private const CATEGORY_LABELS = [
        'MacroProcessus' => 'Processus',
        'Process' => 'Processus',
        'Information' => 'Informations',
        'Application' => 'Applications',
        'ApplicationService' => 'Applications',
        'LogicalServer' => 'Serveurs',
        'PhysicalServer' => 'Serveurs',
        'Workstation' => 'Serveurs',
        'Network' => 'Réseaux',
        'Lan' => 'Réseaux',
        'Site' => 'Sites et bâtiments',
        'Building' => 'Sites et bâtiments',
        'Entity' => 'Organisation',
    ];

    /**
     * Default Mercator model short name -> Monarc knowledgeBase asset code,
     * confirmed against the standard MONARC base library (base ANR knowledgeBase).
     * If a code is absent from the source ANR's knowledgeBase, the caller must
     * fall back to an empty/manual selection — this class never invents one.
     */
    public const DEFAULT_ASSET_CODES = [
        'MacroProcessus' => 'SERV',
        'Process' => 'PROC',
        'Information' => 'INFO',
        'Application' => 'LOG_APP',
        'ApplicationService' => 'LOG_APP',
        'LogicalServer' => 'LOG_OS',
        'PhysicalServer' => 'OV_SERVEUR',
        'Workstation' => 'OV_POSTE_FIXE',
        'Network' => 'OV_RESEAU',
        'Lan' => 'OV_RESEAU',
        'Site' => 'BAT_LOC',
        'Building' => 'BAT_LOC',
        'Entity' => 'ORG_GEN',
    ];

    /**
     * @param  string  $mode  'library' | 'analysis'
     * @param  string  $name  Reserved for the caller (e.g. download filename) —
     *                        not part of the Monarc export file format itself,
     *                        confirmed against the fixture: the root has no
     *                        name/description key.
     * @param  array  $knowledgeBase  as returned by MonarcApiService::getKnowledgeBase()
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
        $tree = $this->indexSelection($selection);

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
                'assets' => $knowledgeBase['assets'] ?? [],
                'threats' => $knowledgeBase['threats'] ?? [],
                'vulnerabilities' => $knowledgeBase['vulnerabilities'] ?? [],
                'referentials' => $knowledgeBase['referentials'] ?? [],
                'informationRisks' => $knowledgeBase['informationRisks'] ?? [],
                'rolfTags' => $knowledgeBase['rolfTags'] ?? [],
                'operationalRisks' => $knowledgeBase['operationalRisks'] ?? [],
                'recommendationSets' => $knowledgeBase['recommendationSets'] ?? [],
            ],
            'library' => [
                'categories' => $this->buildLibrary($tree, $assetsByUuid),
            ],
            'instances' => $mode === 'analysis' ? $this->buildInstances($tree, $assetsByUuid, $amvsByAssetUuid) : [],
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
     * asset.uuid matches the object's chosen Monarc asset type. Global-scope
     * objects count n once; local-scope objects count n once per placement
     * in the composition tree (currently always 1 per selected Mercator
     * record — see indexSelection()). Operational (rolfTag) risks are out
     * of scope for v1 and always contribute 0.
     *
     * @param  array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>  $selection
     */
    public function countRisks(array $knowledgeBase, array $selection): int
    {
        $amvCountByAssetUuid = [];
        foreach ($knowledgeBase['informationRisks'] ?? [] as $amv) {
            $uuid = $amv['asset']['uuid'] ?? null;
            if ($uuid !== null) {
                $amvCountByAssetUuid[$uuid] = ($amvCountByAssetUuid[$uuid] ?? 0) + 1;
            }
        }

        $total = 0;
        foreach ($selection as $item) {
            $total += $amvCountByAssetUuid[$item['asset_uuid'] ?? null] ?? 0;
        }

        return $total;
    }

    /**
     * Indexes the selection by "Model:id" and resolves, for each item, its
     * single parent within the selection (if any) using the cartography's
     * own relations — never inventing a link that doesn't exist in Mercator.
     *
     * @return array<string, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int, uuid: string, parentKey: ?string, children: array<int, string>}>
     */
    private function indexSelection(array $selection): array
    {
        $index = [];
        foreach ($selection as $item) {
            $key = $item['model'].':'.$item['id'];
            $index[$key] = $item + [
                'uuid' => (string) Str::uuid(),
                'parentKey' => null,
                'children' => [],
            ];
        }

        foreach ($index as $key => $entry) {
            $index[$key]['parentKey'] = $this->resolveParentKey($entry['model'], $entry['id'], $index);
        }

        foreach ($index as $key => $entry) {
            if ($entry['parentKey'] !== null) {
                $index[$entry['parentKey']]['children'][] = $key;
            }
        }

        return $index;
    }

    private function resolveParentKey(string $model, int $id, array $index): ?string
    {
        return match ($model) {
            'Process' => $this->matchParent($index, 'MacroProcessus', Process::query()->whereKey($id)->value('macroprocess_id')),
            'Application' => $this->matchParentFromMany($index, 'Process', Application::query()->select('id')->find($id)?->processes()->pluck('processes.id')),
            'ApplicationService' => $this->matchParentFromMany($index, 'Application', ApplicationService::query()->select('id')->find($id)?->applications()->pluck('applications.id')),
            'LogicalServer' => $this->matchParentFromMany($index, 'Application', LogicalServer::query()->select('id')->find($id)?->applications()->pluck('applications.id')),
            'PhysicalServer' => $this->matchParentFromMany($index, 'LogicalServer', PhysicalServer::query()->select('id')->find($id)?->logicalServers()->pluck('logical_servers.id'))
                ?? $this->matchParent($index, 'Building', PhysicalServer::query()->whereKey($id)->value('building_id'))
                ?? $this->matchParent($index, 'Site', PhysicalServer::query()->whereKey($id)->value('site_id')),
            'Workstation' => $this->matchParentFromMany($index, 'Application', Workstation::query()->select('id')->find($id)?->applications()->pluck('applications.id'))
                ?? $this->matchParent($index, 'Building', Workstation::query()->whereKey($id)->value('building_id'))
                ?? $this->matchParent($index, 'Site', Workstation::query()->whereKey($id)->value('site_id')),
            'Information' => $this->matchParentFromMany($index, 'Process', Information::query()->select('id')->find($id)?->processes()->pluck('processes.id')),
            'Building' => $this->matchParent($index, 'Site', Building::query()->whereKey($id)->value('site_id')),
            default => null,
        };
    }

    private function matchParent(array $index, string $parentModel, mixed $parentId): ?string
    {
        if ($parentId === null) {
            return null;
        }

        $key = $parentModel.':'.$parentId;

        return isset($index[$key]) ? $key : null;
    }

    private function matchParentFromMany(array $index, string $parentModel, ?Collection $parentIds): ?string
    {
        foreach ($parentIds ?? [] as $parentId) {
            $key = $parentModel.':'.$parentId;
            if (isset($index[$key])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Library objects are always flat (verified against a live MONARC 2.13.3
     * instance: a non-empty "children" on a library object crashes the real
     * importer — ObjectCategoryImportProcessor::processObjectCategoryData()
     * is invoked with a null category for nested objects. The reference
     * fixture itself never populates object-level "children" either. The
     * cartography's parent/child composition (resolved in indexSelection())
     * is only meaningful for the "instances" tree in analysis mode.
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
     * Analysis mode instance tree, mirroring the cartography's composition
     * (unlike the library, which is always flat — see buildLibrary()).
     * Impacts are left unevaluated (Monarc "not assessed" convention: -1,
     * inheritance flags at their default), instanceRisks hold only the AMV
     * uuid (informationRisk) with an empty recommendations list — the
     * minimal form accepted by InstanceImportProcessor when withEval=false.
     *
     * Validated against a live Monarc 2.13.3 instance: importing a generated
     * "analysis" export reproduced the exact composition tree (levels,
     * scopes, asset codes) and the target ANR's own risks-dashboard reported
     * the same risk count as countRisks() computed beforehand.
     */
    private function buildInstances(array $index, array $assetsByUuid, array $amvsByAssetUuid): array
    {
        $roots = array_filter($index, fn (array $entry) => $entry['parentKey'] === null);

        return array_values(array_map(
            fn (array $entry) => $this->buildInstance($entry['model'].':'.$entry['id'], $index, $assetsByUuid, $amvsByAssetUuid, 1),
            $roots
        ));
    }

    private function buildInstance(string $key, array $index, array $assetsByUuid, array $amvsByAssetUuid, int $level): array
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
                fn (string $childKey) => $this->buildInstance($childKey, $index, $assetsByUuid, $amvsByAssetUuid, $level + 1),
                $entry['children']
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
}
