<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MonarcApiException;
use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Backup;
use App\Models\Bay;
use App\Models\Building;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\Database;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\Domain;
use App\Models\Entity;
use App\Models\ExternalConnectedEntity;
use App\Models\ForestAd;
use App\Models\Gateway;
use App\Models\Information;
use App\Models\Lan;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\Man;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalRouter;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\PhysicalSwitch;
use App\Models\Process;
use App\Models\Relation;
use App\Models\Router;
use App\Models\SecurityDevice;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\Vlan;
use App\Models\Wan;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Models\Zone;
use App\Models\ZoneAdmin;
use App\Services\MonarcApiService;
use App\Services\MonarcExportService;
use App\Services\MospService;
use App\Services\MospToMonarcConverter;
use App\Support\MonarcSelectionState;
use App\Support\MonarcSettings;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonarcController extends Controller
{
    /**
     * Whitelist of Mercator models selectable for export, keyed by the
     * short name used throughout the selection payload/UI. Never resolve
     * a model class from client input without going through this map.
     */
    private const SELECTABLE_MODELS = [
        'MacroProcessus' => MacroProcessus::class,
        'Process' => Process::class,
        'Information' => Information::class,
        'Actor' => Actor::class,
        'Application' => Application::class,
        'ApplicationService' => ApplicationService::class,
        'ApplicationBlock' => ApplicationBlock::class,
        'ApplicationModule' => ApplicationModule::class,
        'Database' => Database::class,
        'LogicalServer' => LogicalServer::class,
        'Cluster' => Cluster::class,
        'Container' => Container::class,
        'Backup' => Backup::class,
        'Network' => Network::class,
        'Subnetwork' => Subnetwork::class,
        'Gateway' => Gateway::class,
        'Router' => Router::class,
        'NetworkSwitch' => NetworkSwitch::class,
        'SecurityDevice' => SecurityDevice::class,
        'DhcpServer' => DhcpServer::class,
        'Dnsserver' => Dnsserver::class,
        'Vlan' => Vlan::class,
        'ExternalConnectedEntity' => ExternalConnectedEntity::class,
        'Lan' => Lan::class,
        'Man' => Man::class,
        'Wan' => Wan::class,
        'PhysicalServer' => PhysicalServer::class,
        'Workstation' => Workstation::class,
        'Site' => Site::class,
        'Building' => Building::class,
        'Bay' => Bay::class,
        'PhysicalSwitch' => PhysicalSwitch::class,
        'PhysicalRouter' => PhysicalRouter::class,
        'PhysicalSecurityDevice' => PhysicalSecurityDevice::class,
        'StorageDevice' => StorageDevice::class,
        'Peripheral' => Peripheral::class,
        'Phone' => Phone::class,
        'WifiTerminal' => WifiTerminal::class,
        'Zone' => Zone::class,
        'Entity' => Entity::class,
        'Relation' => Relation::class,
        'Domain' => Domain::class,
        'ForestAd' => ForestAd::class,
        'Annuaire' => Annuaire::class,
        'ZoneAdmin' => ZoneAdmin::class,
        'AdminUser' => AdminUser::class,
    ];

    private const SCALES_AND_METHOD_KEYS = ['scales', 'operationalRiskScales', 'method', 'thresholds', 'soaScaleComments'];

    /**
     * Every selectable family, in the exact order it appears in
     * resources/views/partials/sidebar.blade.php (submenu order, then
     * within-submenu order) — the single source of truth for family order
     * on the selection screen: it drives both loadMercatorFamilies() (so
     * a row's Select2 optgroups list e.g. Entity before Relation) and the
     * "matching Mercator objects" column's display order. It never affects
     * which view a row is grouped under — rowsFromAssets() keeps its own,
     * unsorted families order for that.
     */
    private const SIDEBAR_FAMILY_ORDER = [
        'Entity', 'Relation',
        'MacroProcessus', 'Process', 'Actor', 'Information',
        'ApplicationBlock', 'Application', 'ApplicationService', 'ApplicationModule', 'Database',
        'ZoneAdmin', 'Annuaire', 'ForestAd', 'Domain', 'AdminUser',
        'Network', 'Subnetwork', 'Gateway', 'ExternalConnectedEntity', 'Router', 'NetworkSwitch',
        'SecurityDevice', 'DhcpServer', 'Dnsserver', 'Cluster', 'LogicalServer', 'Backup', 'Container', 'Vlan',
        'Site', 'Building', 'Bay', 'Zone', 'PhysicalServer', 'Workstation', 'StorageDevice', 'Peripheral',
        'Phone', 'PhysicalSwitch', 'PhysicalRouter', 'WifiTerminal', 'PhysicalSecurityDevice', 'Wan', 'Man', 'Lan',
    ];

    /**
     * Groups the selectable Mercator families into Mercator's own "views"
     * (resources/views/partials/navbar.blade.php's Views menu — panel.menu.*
     * translations already exist for every label used here), so the
     * cartography selection screen reads like the rest of the app instead
     * of one flat 13-family table.
     *
     * Mercator models with no sensible MONARC asset type — e.g. PhysicalLink
     * (network cabling) and LogicalFlow/ApplicationFlow (application-to-
     * application data flows) — represent relationships between objects, not
     * discrete assets with their own risk profile, so they were never added
     * to SELECTABLE_MODELS and never appear on this screen at all. Likewise,
     * views with no mapped family here (GDPR, preferences...) are simply
     * absent from this list, which is equivalent to "always empty, never
     * rendered" for this screen.
     *
     * Groupings mirror each view's actual report controller (e.g.
     * Report\LogicalInfrastructureView, Report\PhysicalInfrastructureView),
     * not just the navbar's access-gate check, since a few models
     * (ExternalConnectedEntity, DhcpServer, Dnsserver, SecurityDevice...)
     * are rendered on a view's page without gating its navbar visibility.
     * A model that legitimately appears on more than one Mercator report
     * page (e.g. Workstation, StorageDevice, WifiTerminal on both logical
     * and physical infrastructure) is placed under its single most natural
     * view here, to avoid duplicating the same row across two sections of
     * this screen.
     */
    private const FAMILY_VIEWS = [
        'ecosystem' => ['Entity', 'Relation'],
        'information_system' => ['MacroProcessus', 'Process', 'Actor', 'Information'],
        'applications' => ['ApplicationBlock', 'Application', 'ApplicationService',  'ApplicationModule', 'Database'],
        'administration' => ['Domain', 'ForestAd', 'Annuaire', 'ZoneAdmin', 'AdminUser'],
        'logical_infrastructure' => ['Network', 'SubNetwork', 'LogicalServer', 'Cluster', 'Container', 'Backup', 'Network', 'Subnetwork', 'Gateway', 'Router', 'NetworkSwitch', 'SecurityDevice', 'DhcpServer', 'Dnsserver', 'Vlan', 'ExternalConnectedEntity'],
        'physical_infrastructure' => ['Site', 'Building', 'Bay', 'Zone', 'PhysicalServer', 'PhysicalSwitch', 'PhysicalRouter', 'Workstation', 'StorageDevice', 'Peripheral', 'Phone', 'WifiTerminal', 'PhysicalSecurityDevice', 'Wan', 'Man', 'Lan'],
    ];

    public function __construct(
        private MonarcApiService $api,
        private MospService $mosp,
        private MospToMonarcConverter $converter,
        private MonarcExportService $exporter,
    ) {}

    public function index()
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $mospReferentials = collect();
        $rows = [];
        $knowledgeBase = [];
        $apiError = null;
        $savedState = MonarcSelectionState::load();

        try {
            $mospReferentials = collect($this->mosp->getReferentials())
                ->sortBy(fn (array $r) => mb_strtolower($r['organization'].' '.$r['name']))
                ->values();
        } catch (MonarcApiException $e) {
            $apiError = $e->getMessage();
        }

        // Referentials never affect which rows appear or their risk counts —
        // only which compliance measures get tagged onto each risk in the
        // exported file — so there's no separate "apply" reload for them:
        // the multi-select lives in the same form as everything else, and a
        // GET visit always reflects the last saved/exported choice.
        $selectedReferentialIds = array_map('intval', $savedState['mosp_referentials'] ?? []);

        // The base catalog (assets/threats/vulnerabilities/risks) is always
        // available from MOSP — referentials are an optional add-on for the
        // KB's "referentials"/measures section, not a prerequisite for rows.
        if ($apiError === null) {
            try {
                [$knowledgeBase, $rows] = $this->loadMospData($selectedReferentialIds, app()->getLocale());
            } catch (MonarcApiException $e) {
                $apiError = $e->getMessage();
            }
        }

        $scalesAndMethod = $this->fillScalesAndMethodDefaults([]);
        $families = $this->loadMercatorFamilies();

        return view('admin.monarc', [
            'apiError' => $apiError,
            'mospReferentials' => $mospReferentials,
            'selectedReferentialIds' => $selectedReferentialIds,
            'viewSections' => $this->rowsByView($rows),
            'families' => $families,
            'familyLabelByModel' => collect($families)->pluck('label', 'model')->all(),
            'familyOrder' => array_flip(self::SIDEBAR_FAMILY_ORDER),
            'familiesForJs' => $this->familiesForJs($families),
            'familiesByAssetCode' => $this->familiesByAssetCode(),
            'amvCountByAssetUuid' => $this->amvCountByAssetUuid($knowledgeBase),
            'risksByAssetUuid' => $this->risksByAssetUuid($knowledgeBase),
            'relations' => $this->exporter->buildRelationsMap(),
            'primaryFamilies' => MonarcExportService::PRIMARY_FAMILIES,
            'savedState' => $savedState,
            'savedRows' => $savedState['rows'] ?? [],
        ]);
    }

    public function export(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $rowsInput = collect($request->input('rows', []));

        $validated = validator(
            [
                'mosp_referentials' => $request->input('mosp_referentials', []),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'language' => $request->input('language'),
                'rows' => $rowsInput->all(),
            ],
            [
                'mosp_referentials' => ['array'],
                'mosp_referentials.*' => ['integer'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'language' => ['required', 'in:fr,en'],
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.asset_uuid' => ['required', 'string'],
                'rows.*.global' => ['nullable', 'array'],
                'rows.*.local' => ['nullable', 'array'],
            ]
        )->validate();

        MonarcSelectionState::save([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'language' => $validated['language'],
            'mosp_referentials' => array_map('intval', $validated['mosp_referentials']),
            'rows' => $validated['rows'],
        ]);

        try {
            $referentialIds = array_map('intval', $validated['mosp_referentials']);
            [$knowledgeBase] = $this->loadMospData($referentialIds, $validated['language']);
        } catch (MonarcApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $scalesAndMethod = $this->fillScalesAndMethodDefaults([]);

        $selection = $this->flattenRowSelection($validated['rows']);

        if ($selection === []) {
            return back()->withInput()->with('error', trans('cruds.monarc.errors.empty_selection'));
        }

        $export = $this->exporter->buildExport(
            'analysis',
            $validated['name'],
            $validated['description'] ?? '',
            $validated['language'],
            $knowledgeBase,
            $scalesAndMethod,
            $selection
        );

        $json = json_encode($export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'monarc-'.Str::slug($validated['name']).'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Persists the selection screen's current form state (name, description,
     * language, chosen referentials, per-row global/local selections)
     * without building or downloading an export — lets the user save an
     * in-progress selection and come back to it later. Deliberately lenient:
     * unlike export(), an empty or partial selection is a valid thing to save.
     */
    public function saveSelection(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $validated = validator(
            [
                'mosp_referentials' => $request->input('mosp_referentials', []),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'language' => $request->input('language'),
                'rows' => $request->input('rows', []),
            ],
            [
                'mosp_referentials' => ['array'],
                'mosp_referentials.*' => ['integer'],
                'name' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'language' => ['nullable', 'in:fr,en'],
                'rows' => ['array'],
                'rows.*.asset_uuid' => ['required', 'string'],
                'rows.*.global' => ['nullable', 'array'],
                'rows.*.local' => ['nullable', 'array'],
            ]
        )->validate();

        MonarcSelectionState::save([
            'name' => $validated['name'] ?? '',
            'description' => $validated['description'] ?? '',
            'language' => $validated['language'] ?? 'fr',
            'mosp_referentials' => array_map('intval', $validated['mosp_referentials']),
            'rows' => $validated['rows'],
        ]);

        return redirect(route('admin.monarc'))->with('success', trans('cruds.monarc.save_success'));
    }

    public function testConnection(Request $request)
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        [$message, $ok] = $this->api->testConnection();

        return redirect(route('admin.config.parameters').'?tab=monarc')
            ->with($ok ? 'success' : 'error', $message);
    }

    /**
     * @param  array<int, int>  $referentialIds  user-selected "Security referentials" MOSP ids (optional)
     * @param  string  $languageCode  row labels are fetched in this language, falling back to English
     *                                (see MospService::fetchPreferredLanguage()) — callers always pass
     *                                either the current user's locale (selection page) or the export
     *                                form's chosen language (export), never a hardcoded default.
     * @return array{0: array, 1: array<int, array{row_uuid: string, label: string, asset_code: string, asset_uuid: string, families: array<int, string>, view: string}>}
     *
     * @throws MonarcApiException
     */
    private function loadMospData(array $referentialIds, string $languageCode): array
    {
        $knowledgeBase = $this->converter->convert(
            $this->mosp->getBaseAssets($languageCode),
            $this->mosp->getBaseThreats($languageCode),
            $this->mosp->getBaseVulnerabilities($languageCode),
            $this->mosp->getBaseRisks(),
            array_map(fn (int $id) => $this->mosp->getReferentialData($id), $referentialIds)
        );

        return [$knowledgeBase, $this->rowsFromAssets($knowledgeBase['assets'])];
    }

    /**
     * Builds one selection row per KB asset — but only for asset codes that
     * map to at least one Mercator family (MonarcExportService::DEFAULT_ASSET_CODES,
     * reversed by familiesByAssetCode()): a MONARC asset type with no
     * corresponding Mercator family (most of the ~40 base codes — printers,
     * backups, maintenance contracts, etc., that Mercator simply doesn't
     * model as first-class cartography objects) would only ever produce
     * empty global/local selects, so it's dropped rather than shown inert.
     *
     * @return array<int, array{row_uuid: string, label: string, asset_code: string, asset_uuid: string, families: array<int, string>, view: string}>
     */
    private function rowsFromAssets(array $assets): array
    {
        $familiesByCode = $this->familiesByAssetCode();
        $familyOrder = array_flip(self::SIDEBAR_FAMILY_ORDER);

        return collect($assets)
            ->map(function (array $asset) use ($familiesByCode) {
                $families = $familiesByCode[$asset['code']] ?? [];

                return $families === [] ? null : [
                    'row_uuid' => $asset['uuid'],
                    'label' => $asset['label'],
                    'asset_code' => $asset['code'],
                    'asset_uuid' => $asset['uuid'],
                    'families' => $families,
                    'view' => $this->viewForFamily($families[0]),
                ];
            })
            ->filter()
            // Rows are ordered like the sidebar first (by the earliest-appearing
            // eligible family — e.g. Entity before Relation in "ecosystem"),
            // falling back to the MONARC label alphabetically only to break
            // ties between rows that share the same leading family.
            ->sortBy(function (array $row) use ($familyOrder) {
                $minIndex = collect($row['families'])->map(fn (string $model) => $familyOrder[$model] ?? PHP_INT_MAX)->min();

                return sprintf('%04d-%s', $minIndex, mb_strtolower($row['label']));
            })
            ->values()
            ->all();
    }

    /**
     * Groups rows by Mercator "view" (see FAMILY_VIEWS), in FAMILY_VIEWS
     * order, dropping any view with no rows — the empty-section rule.
     *
     * @param  array<int, array{view: string}>  $rows
     * @return array<int, array{key: string, label: string, rows: array}>
     */
    private function rowsByView(array $rows): array
    {
        $grouped = collect($rows)->groupBy('view');

        $sections = [];
        foreach (array_keys(self::FAMILY_VIEWS) as $viewKey) {
            $viewRows = $grouped->get($viewKey, collect());
            if ($viewRows->isEmpty()) {
                continue;
            }

            $sections[] = [
                'key' => $viewKey,
                'label' => trans("panel.menu.{$viewKey}"),
                'rows' => $viewRows->values()->all(),
            ];
        }

        return $sections;
    }

    private function viewForFamily(string $model): string
    {
        foreach (self::FAMILY_VIEWS as $viewKey => $models) {
            if (in_array($model, $models, true)) {
                return $viewKey;
            }
        }

        return 'other';
    }

    /**
     * Fills scales/operationalRiskScales/method/thresholds/soaScaleComments
     * from config/monarc-defaults.php whenever the source didn't provide them
     * (always the case for MOSP, which carries none of these).
     */
    private function fillScalesAndMethodDefaults(array $scalesAndMethod): array
    {
        $defaults = config('monarc-defaults', []);

        foreach (self::SCALES_AND_METHOD_KEYS as $key) {
            if (empty($scalesAndMethod[$key])) {
                $scalesAndMethod[$key] = $defaults[$key] ?? [];
            }
        }

        if (empty($scalesAndMethod['monarc_version'])) {
            $scalesAndMethod['monarc_version'] = config('monarc.target_monarc_version', '2.13.3');
        }

        return $scalesAndMethod;
    }

    private function amvCountByAssetUuid(array $knowledgeBase): array
    {
        $counts = [];
        foreach ($knowledgeBase['informationRisks'] ?? [] as $amv) {
            $uuid = $amv['asset']['uuid'] ?? null;
            if ($uuid !== null) {
                $counts[$uuid] = ($counts[$uuid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Groups the knowledgeBase's AMVs (threat+vulnerability pairs) by asset
     * uuid, with human-readable threat/vulnerability labels resolved — feeds
     * the "view these risks" modal triggered by clicking a row's risk count.
     *
     * @return array<string, array<int, array{threat: string, vulnerability: string}>>
     */
    private function risksByAssetUuid(array $knowledgeBase): array
    {
        $threatsByUuid = collect($knowledgeBase['threats'] ?? [])->keyBy('uuid');
        $vulnerabilitiesByUuid = collect($knowledgeBase['vulnerabilities'] ?? [])->keyBy('uuid');

        $byAsset = [];
        foreach ($knowledgeBase['informationRisks'] ?? [] as $amv) {
            $assetUuid = $amv['asset']['uuid'] ?? null;
            if ($assetUuid === null) {
                continue;
            }

            $threat = $threatsByUuid->get($amv['threat']['uuid'] ?? null);
            $vulnerability = $vulnerabilitiesByUuid->get($amv['vulnerability']['uuid'] ?? null);

            $byAsset[$assetUuid][] = [
                'threat' => $threat['label'] ?? '',
                'vulnerability' => $vulnerability['label'] ?? '',
            ];
        }

        foreach ($byAsset as $assetUuid => $risks) {
            $byAsset[$assetUuid] = collect($risks)
                ->sortBy(fn (array $risk) => mb_strtolower($risk['threat']))
                ->values()
                ->all();
        }

        return $byAsset;
    }

    /**
     * Reverses MonarcExportService::DEFAULT_ASSET_CODES so a row's asset
     * code maps back to the Mercator families ALLOWED for it — a strict
     * filter, not a hint: a row's global/local selects only ever offer
     * objects from these families, and rowsFromAssets() drops any asset
     * code that maps to none at all.
     *
     * @return array<string, array<int, string>>
     */
    private function familiesByAssetCode(): array
    {
        $byCode = [];
        foreach (MonarcExportService::DEFAULT_ASSET_CODES as $model => $codes) {
            foreach ($codes as $code) {
                $byCode[$code][] = $model;
            }
        }

        return $byCode;
    }

    /**
     * @return array<int, array{label: string, model: string, items: Collection}>
     */
    private function loadMercatorFamilies(): array
    {
        $labelsByModel = [
            'MacroProcessus' => trans('cruds.macroProcessus.title'),
            'Process' => trans('cruds.process.title'),
            'Information' => trans('cruds.information.title'),
            'Actor' => trans('cruds.actor.title'),
            'Application' => trans('cruds.application.title'),
            'ApplicationService' => trans('cruds.applicationService.title'),
            'ApplicationBlock' => trans('cruds.applicationBlock.title'),
            'ApplicationModule' => trans('cruds.applicationModule.title'),
            'Database' => trans('cruds.database.title'),
            'LogicalServer' => trans('cruds.logicalServer.title'),
            'Cluster' => trans('cruds.cluster.title'),
            'Container' => trans('cruds.container.title'),
            'Backup' => trans('cruds.backup.title'),
            'Network' => trans('cruds.network.title'),
            'Subnetwork' => trans('cruds.subnetwork.title'),
            'Gateway' => trans('cruds.gateway.title'),
            'Router' => trans('cruds.router.title'),
            'NetworkSwitch' => trans('cruds.networkSwitch.title'),
            'SecurityDevice' => trans('cruds.securityDevice.title'),
            'DhcpServer' => trans('cruds.dhcpServer.title'),
            'Dnsserver' => trans('cruds.dnsserver.title'),
            'Vlan' => trans('cruds.vlan.title'),
            'ExternalConnectedEntity' => trans('cruds.externalConnectedEntity.title'),
            'Lan' => trans('cruds.lan.title'),
            'Man' => trans('cruds.man.title'),
            'Wan' => trans('cruds.wan.title'),
            'PhysicalServer' => trans('cruds.physicalServer.title'),
            'Workstation' => trans('cruds.workstation.title'),
            'Site' => trans('cruds.site.title'),
            'Building' => trans('cruds.building.title'),
            'Bay' => trans('cruds.bay.title'),
            'PhysicalSwitch' => trans('cruds.physicalSwitch.title'),
            'PhysicalRouter' => trans('cruds.physicalRouter.title'),
            'PhysicalSecurityDevice' => trans('cruds.physicalSecurityDevice.title'),
            'StorageDevice' => trans('cruds.storageDevice.title'),
            'Peripheral' => trans('cruds.peripheral.title'),
            'Phone' => trans('cruds.phone.title'),
            'WifiTerminal' => trans('cruds.wifiTerminal.title'),
            'Zone' => trans('cruds.zone.title'),
            'Entity' => trans('cruds.entity.title'),
            'Relation' => trans('cruds.relation.title'),
            'Domain' => trans('cruds.domaine.title'),
            'ForestAd' => trans('cruds.forestAd.title'),
            'Annuaire' => trans('cruds.annuaire.title'),
            'ZoneAdmin' => trans('cruds.zoneAdmin.title'),
            'AdminUser' => trans('cruds.adminUser.title'),
        ];

        $families = [];
        foreach (self::SIDEBAR_FAMILY_ORDER as $model) {
            $label = $labelsByModel[$model];

            // AdminUser has no "name" column — it is identified by its login (user_id).
            $nameColumn = $model === 'AdminUser' ? 'user_id' : 'name';

            $items = self::SELECTABLE_MODELS[$model]::query()
                ->select(['id', "{$nameColumn} as name"])
                ->get()
                ->sortBy(fn ($item) => mb_strtolower($item->name))
                ->values();

            $families[] = ['label' => $label, 'model' => $model, 'items' => $items];
        }

        return $families;
    }

    /**
     * Same data as loadMercatorFamilies(), reshaped for the page's embedded
     * JSON: each item's "id" is the "Model:id" composite key used
     * everywhere else (selection payload, relations map), so the client can
     * feed it straight into Select2's `data` option (with optgroups via
     * `children`) without server-rendering hundreds of <option> tags per
     * row's two multi-selects.
     *
     * @return array<int, array{model: string, label: string, items: array<int, array{id: string, text: string}>}>
     */
    private function familiesForJs(array $families): array
    {
        return array_map(fn (array $family) => [
            'model' => $family['model'],
            'label' => $family['label'],
            'items' => $family['items']->map(fn ($item) => [
                'id' => $family['model'].':'.$item->id,
                'text' => $item->name,
            ])->all(),
        ], $families);
    }

    /**
     * Flattens the inverted (row -> global[]/local[] Mercator keys) payload
     * into the flat {model,id,name,asset_uuid,scope} selection buildExport()
     * expects. Re-resolves each item's name from the database — the payload
     * only ever carries "Model:id" strings, never a trusted display name.
     * A Mercator key is skipped if it's listed as both global and local on
     * the same row (server-side enforcement of the UI's exclusivity rule),
     * and only its first row assignment is kept if repeated across rows.
     *
     * @param  array<string, array{asset_uuid: string, global?: array<int, string>, local?: array<int, string>}>  $rows
     * @return array<int, array{model: string, id: int, name: string, asset_uuid: string, scope: int}>
     */
    private function flattenRowSelection(array $rows): array
    {
        $seenKeys = [];
        $selection = [];

        foreach ($rows as $row) {
            $assetUuid = $row['asset_uuid'];

            $globalKeys = array_flip($row['global'] ?? []);
            $localKeys = array_flip($row['local'] ?? []);
            $exclusive = array_diff_key($globalKeys + $localKeys, array_intersect_key($globalKeys, $localKeys));

            foreach (['global' => 2, 'local' => 1] as $field => $scope) {
                foreach ($row[$field] ?? [] as $key) {
                    if (! isset($exclusive[$key]) || isset($seenKeys[$key])) {
                        continue; // either flagged both global+local, or already assigned to an earlier row
                    }

                    [$model, $id] = array_pad(explode(':', $key, 2), 2, null);
                    $modelClass = self::SELECTABLE_MODELS[$model] ?? null;
                    if ($modelClass === null) {
                        continue;
                    }

                    $nameColumn = $model === 'AdminUser' ? 'user_id' : 'name';
                    $name = $modelClass::query()->select(['id', "{$nameColumn} as name"])->find((int) $id)?->name;
                    if ($name === null) {
                        continue;
                    }

                    $seenKeys[$key] = true;
                    $selection[] = [
                        'model' => $model,
                        'id' => (int) $id,
                        'name' => $name,
                        'asset_uuid' => $assetUuid,
                        'scope' => $scope,
                    ];
                }
            }
        }

        return $selection;
    }
}
