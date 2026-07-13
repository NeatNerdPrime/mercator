<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MonarcApiException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationService;
use App\Models\Building;
use App\Models\Entity;
use App\Models\Information;
use App\Models\Lan;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\Network;
use App\Models\PhysicalServer;
use App\Models\Process;
use App\Models\Site;
use App\Models\Workstation;
use App\Services\MonarcApiService;
use App\Services\MonarcExportService;
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
        'Application' => Application::class,
        'ApplicationService' => ApplicationService::class,
        'LogicalServer' => LogicalServer::class,
        'PhysicalServer' => PhysicalServer::class,
        'Workstation' => Workstation::class,
        'Network' => Network::class,
        'Lan' => Lan::class,
        'Site' => Site::class,
        'Building' => Building::class,
        'Entity' => Entity::class,
    ];

    public function __construct(
        private MonarcApiService $api,
        private MonarcExportService $exporter,
    ) {}

    public function index(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $apiError = null;
        $anrs = [];

        try {
            $anrs = $this->api->getAnrs();
        } catch (MonarcApiException $e) {
            $apiError = $e->getMessage();
        }

        $anrs = collect($anrs)->sortBy(fn (array $anr) => mb_strtolower($anr['label'] ?? ''))->values();
        $selectedAnrId = (int) $request->query('anr', $anrs->first()['id'] ?? 0);

        $assets = collect();
        $amvCountByAssetUuid = [];

        if ($selectedAnrId && $apiError === null) {
            try {
                $assets = collect($this->api->getAssets($selectedAnrId))
                    ->sortBy(fn (array $asset) => mb_strtolower($asset['label'] ?? ''))
                    ->values();

                foreach ($this->api->getAmvs($selectedAnrId) as $amv) {
                    $uuid = $amv['asset']['uuid'] ?? null;
                    if ($uuid !== null) {
                        $amvCountByAssetUuid[$uuid] = ($amvCountByAssetUuid[$uuid] ?? 0) + 1;
                    }
                }
            } catch (MonarcApiException $e) {
                $apiError = $e->getMessage();
            }
        }

        return view('admin.monarc', [
            'anrs' => $anrs,
            'selectedAnrId' => $selectedAnrId,
            'apiError' => $apiError,
            'families' => $this->loadMercatorFamilies(),
            'assets' => $assets,
            'defaultAssetCodes' => MonarcExportService::DEFAULT_ASSET_CODES,
            'amvCountByAssetUuid' => $amvCountByAssetUuid,
        ]);
    }

    public function export(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $checked = collect($request->input('selection', []))
            ->filter(fn (array $row) => ! empty($row['checked']))
            ->values()
            ->all();

        $validated = validator(
            [
                'anr_id' => $request->input('anr_id'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'language' => $request->input('language'),
                'mode' => $request->input('mode'),
                'selection' => $checked,
            ],
            [
                'anr_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'language' => ['required', 'in:fr,en'],
                'mode' => ['required', 'in:library,analysis'],
                'selection' => ['required', 'array', 'min:1'],
                'selection.*.model' => ['required', 'string', 'in:'.implode(',', array_keys(self::SELECTABLE_MODELS))],
                'selection.*.id' => ['required', 'integer'],
                'selection.*.asset_uuid' => ['required', 'string'],
                'selection.*.scope' => ['required', 'integer', 'in:1,2'],
            ]
        )->validate();

        $anrId = (int) $validated['anr_id'];

        try {
            $knowledgeBase = [
                'assets' => $this->api->getAssets($anrId),
                'threats' => $this->api->getThreats($anrId),
                'vulnerabilities' => $this->api->getVulnerabilities($anrId),
                'referentials' => $this->api->getReferentials($anrId),
                'informationRisks' => $this->api->getAmvs($anrId),
                'rolfTags' => $this->api->getRolfTags($anrId),
                'operationalRisks' => $this->api->getOperationalRisks($anrId),
                'recommendationSets' => [],
            ];

            $scalesAndMethod = [
                'monarc_version' => $this->api->getMonarcVersion($anrId),
                'scales' => $this->api->getScales($anrId),
                'operationalRiskScales' => $this->api->getOperationalRiskScales($anrId),
                'method' => $this->api->getMethod($anrId),
                'thresholds' => $this->api->getThresholds($anrId),
                'soas' => $this->api->getSoas($anrId),
                'soaScaleComments' => $this->api->getSoaScaleComments($anrId),
            ];
        } catch (MonarcApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $selection = $this->resolveSelection($validated['selection']);

        if ($selection === []) {
            return back()->withInput()->with('error', trans('cruds.monarc.errors.empty_selection'));
        }

        $export = $this->exporter->buildExport(
            $validated['mode'],
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

    public function testConnection(Request $request)
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        [$message, $ok] = $this->api->testConnection();

        return redirect(route('admin.config.parameters').'?tab=monarc')
            ->with($ok ? 'success' : 'error', $message);
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
            'Application' => trans('cruds.application.title'),
            'ApplicationService' => trans('cruds.applicationService.title'),
            'LogicalServer' => trans('cruds.logicalServer.title'),
            'PhysicalServer' => trans('cruds.physicalServer.title'),
            'Workstation' => trans('cruds.workstation.title'),
            'Network' => trans('cruds.network.title'),
            'Lan' => trans('cruds.lan.title'),
            'Site' => trans('cruds.site.title'),
            'Building' => trans('cruds.building.title'),
            'Entity' => trans('cruds.entity.title'),
        ];

        $families = [];
        foreach ($labelsByModel as $model => $label) {
            $items = self::SELECTABLE_MODELS[$model]::query()
                ->select(['id', 'name'])
                ->get()
                ->sortBy(fn ($item) => mb_strtolower($item->name))
                ->values();

            $families[] = ['label' => $label, 'model' => $model, 'items' => $items];
        }

        return $families;
    }

    /**
     * Re-resolves each selected item's name from the database — the
     * selection payload only ever carries model+id+asset_uuid+scope from
     * the client, never a trusted display name.
     *
     * @return array<int, array{model: string, id: int, name: string, asset_uuid: string, scope: int}>
     */
    private function resolveSelection(array $selection): array
    {
        $resolved = [];

        foreach ($selection as $item) {
            $modelClass = self::SELECTABLE_MODELS[$item['model']] ?? null;
            if ($modelClass === null) {
                continue;
            }

            $name = $modelClass::query()->select(['id', 'name'])->find((int) $item['id'])?->name;
            if ($name === null) {
                continue;
            }

            $resolved[] = [
                'model' => $item['model'],
                'id' => (int) $item['id'],
                'name' => $name,
                'asset_uuid' => $item['asset_uuid'],
                'scope' => (int) $item['scope'],
            ];
        }

        return $resolved;
    }
}
