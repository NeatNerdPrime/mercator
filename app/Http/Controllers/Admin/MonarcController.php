<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MonarcApiException;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Application;
use App\Models\Building;
use App\Models\Database;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\Entity;
use App\Models\ExternalConnectedEntity;
use App\Models\MonarcSyncItem;
use App\Models\Relation;
use App\Models\SecurityDevice;
use App\Models\StorageDevice;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Services\MonarcApiService;
use App\Services\MonarcExportService;
use App\Services\MonarcSyncService;
use App\Services\MospService;
use App\Services\MospToMonarcConverter;
use App\Support\ModelRegistry;
use App\Support\MonarcSelectionState;
use App\Support\MonarcSettings;
use App\Support\MonarcSyncState;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MonarcController extends Controller
{
    /**
     * Whitelist of Mercator models selectable for export, keyed by the
     * short name used throughout the selection payload/UI. Never resolve
     * a model class from client input without going through this map.
     */
    private const SELECTABLE_MODELS = ModelRegistry::MONARC_SELECTABLE_MODELS;

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
    private const SIDEBAR_FAMILY_ORDER = ModelRegistry::MONARC_SIDEBAR_FAMILY_ORDER;

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
     *
     * Lives on MonarcExportService (MonarcExportService::FAMILY_VIEWS) —
     * also used there to group Monarc library objects into matching
     * top-level categories — so both stay a single source of truth.
     */
    public function __construct(
        private MonarcApiService $api,
        private MospService $mosp,
        private MospToMonarcConverter $converter,
        private MonarcExportService $exporter,
        private MonarcSyncService $syncService,
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

        // Always loaded, whether an ANR is already linked or not: the admin
        // can change the sync target (pick a different existing ANR, or
        // type a new name) at any time — not just before the first link.
        $syncState = MonarcSyncState::load();
        $monarcModels = [];
        $monarcModelsError = null;
        $monarcAnrs = [];
        $monarcAnrsError = null;

        // Authenticated once upfront: getModels() and getAnrs() below each
        // authenticate internally, and MonarcApiService only caches a
        // *successful* token — never a failure — so a bad URL/login/password
        // would otherwise make both calls fail independently and render the
        // exact same auth error twice on this screen (one per alert box).
        try {
            $this->api->authenticate();
        } catch (MonarcApiException $e) {
            $monarcModelsError = $e->getMessage();
            Log::warning('Monarc authenticate() failed: '.$e->getMessage());
        }

        if ($monarcModelsError === null) {
            try {
                $monarcModels = $this->api->getModels(app()->getLocale());

                if ($monarcModels === []) {
                    // Not necessarily a bug — an instance can legitimately have
                    // no models yet — but log it so an unexpected response shape
                    // (see MonarcApiService::getModels()) is diagnosable from
                    // the raw payload rather than silently leaving the select empty.
                    Log::warning('Monarc getModels() returned no models — check the raw response shape if models are expected.');
                }
            } catch (MonarcApiException $e) {
                $monarcModelsError = $e->getMessage();
                Log::warning('Monarc getModels() failed: '.$e->getMessage());
            }

            try {
                $monarcAnrs = $this->api->getAnrs(app()->getLocale());
            } catch (MonarcApiException $e) {
                $monarcAnrsError = $e->getMessage();
                Log::warning('Monarc getAnrs() failed: '.$e->getMessage());
            }
        }

        // Deliberately a dedicated anrExists() call rather than checking
        // whether the linked id is present in $monarcAnrs above: that list
        // can legitimately omit an ANR that still exists (e.g. Monarc-side
        // pagination/visibility rules on GET /api/client-anr), which would
        // otherwise show a false "ANR missing" warning for an ANR that a
        // real sync (MonarcSyncService, which uses this same anrExists()
        // check) would still successfully reach. Left false (no false
        // positive) when the check itself fails.
        $linkedAnrMissing = false;
        if (isset($syncState['anr_id'])) {
            try {
                $linkedAnrMissing = ! $this->api->anrExists((int) $syncState['anr_id']);
            } catch (MonarcApiException $e) {
                Log::warning('Monarc anrExists() failed while checking the linked ANR: '.$e->getMessage());
            }
        }

        // The merged "name / ANR" field's initial value comes from
        // MonarcSelectionState alone — the single source of truth for every
        // form field on this screen, standard Laravel old()-first behavior
        // after a failed validation. MonarcSyncState (linked ANR, its label)
        // is deliberately NOT consulted here: it only ever drives the
        // separate link-status card below (ANR liée / dernière synchro /
        // compteur), never the form's own pre-filled values.
        $initialAnrName = old('name', $savedState['name'] ?? '');

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
            'savedRows' => $this->normalizeSavedRows($savedState['rows'] ?? []),
            'syncState' => $syncState,
            'syncedItemsCount' => isset($syncState['anr_id'])
                ? MonarcSyncItem::query()->where('anr_id', $syncState['anr_id'])->count()
                : 0,
            'monarcModels' => $monarcModels,
            'monarcModelsError' => $monarcModelsError,
            'monarcAnrs' => $monarcAnrs,
            'monarcAnrsError' => $monarcAnrsError,
            'linkedAnrMissing' => $linkedAnrMissing,
            'initialAnrName' => $initialAnrName,
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
                'rows.*.objects' => ['nullable', 'array'],
                'rows.*.generic' => ['nullable', 'boolean'],
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
            [$knowledgeBase, $mospRows] = $this->loadMospData($referentialIds, $validated['language']);
        } catch (MonarcApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $scalesAndMethod = $this->fillScalesAndMethodDefaults([]);

        $selection = $this->flattenRowSelection($validated['rows'], collect($mospRows)->pluck('label', 'asset_uuid')->all());

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
     * Creates (on first use) or synchronizes the Monarc ANR linked to this
     * Mercator instance: only the Mercator objects (and their risks) not
     * already recorded as sent for that ANR are imported — see
     * MonarcSyncService. Called via fetch from admin/monarc.blade.php, so it
     * always answers in JSON rather than redirecting/flashing.
     */
    public function sync(Request $request)
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
                'model_id' => $request->input('model_id'),
                'anr_id' => $request->input('anr_id'),
                'anr_label' => $request->input('anr_label'),
            ],
            [
                'mosp_referentials' => ['array'],
                'mosp_referentials.*' => ['integer'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'language' => ['required', 'in:fr,en'],
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.asset_uuid' => ['required', 'string'],
                'rows.*.objects' => ['nullable', 'array'],
                'rows.*.generic' => ['nullable', 'boolean'],
                'model_id' => ['nullable', 'integer'],
                'anr_id' => ['nullable', 'integer'],
                'anr_label' => ['nullable', 'string', 'max:255'],
            ]
        )->validate();

        try {
            $referentialIds = array_map('intval', $validated['mosp_referentials']);
            [$knowledgeBase, $mospRows] = $this->loadMospData($referentialIds, $validated['language']);
        } catch (MonarcApiException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $selection = $this->flattenRowSelection($validated['rows'], collect($mospRows)->pluck('label', 'asset_uuid')->all());

        if ($selection === []) {
            return response()->json(['status' => 'error', 'message' => trans('cruds.monarc.errors.empty_selection')], 422);
        }

        $scalesAndMethod = $this->fillScalesAndMethodDefaults([]);

        try {
            $result = $this->syncService->sync(
                isset($validated['anr_id']) ? (int) $validated['anr_id'] : null,
                isset($validated['model_id']) ? (int) $validated['model_id'] : null,
                $validated['anr_label'] ?? null,
                $validated['name'],
                $validated['description'] ?? '',
                $validated['language'],
                $knowledgeBase,
                $scalesAndMethod,
                $selection
            );
        } catch (MonarcApiException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Forgets the local Monarc ANR link (and every tracked sync item) so the
     * next "Create/Synchronize" click starts a brand new ANR. The remote ANR
     * itself is never deleted.
     */
    public function resetSync(Request $request)
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $this->syncService->reset();

        return redirect(route('admin.config.parameters').'?tab=monarc')
            ->with('success', trans('cruds.monarc.sync.reset_success'));
    }

    /**
     * Called from the selection screen's JS the moment the admin picks an
     * already-existing Monarc ANR from the combobox: verifies it still
     * exists (fresh check, never cached — see getAnrs()), pulls its label
     * and language directly from Monarc, and rebuilds the row selection
     * (which objects, which "generic" checkboxes) from what is actually
     * recorded in monarc_sync_items for it — row restoration is
     * local-table-based only (see rowsFromSyncItems()), it does not
     * cross-check the ANR's live instance tree in Monarc itself.
     *
     * On success, all of this is merged into MonarcSelectionState (name,
     * language, rows) so the draft the admin keeps editing/saving reflects
     * the picked ANR — description and mosp_referentials are left as they
     * were, since Monarc has no equivalent to derive them from. Nothing is
     * persisted if the ANR is gone or the Monarc API call fails.
     */
    public function anrRows(int $anrId)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if(! MonarcSettings::enabled(), Response::HTTP_NOT_FOUND);

        $savedState = MonarcSelectionState::load();

        try {
            // getAnrs() is never cached (see MonarcApiService) — doubles as
            // both the "does it still exist" freshness check and the source
            // of its own label, in a single round trip.
            $anr = collect($this->api->getAnrs(app()->getLocale()))->firstWhere('id', $anrId);

            if ($anr === null) {
                return response()->json(['status' => 'error', 'message' => trans('cruds.monarc.sync.errors.anr_not_found')], 404);
            }

            // Triggers a fetch of the ANR's full export (cached
            // config('monarc.cache_ttl'), like every other getXxx() accessor
            // backed by fetchAnrExport() — acceptable here since it's only
            // hit when the admin actively picks this ANR, not on every load.
            $language = $this->api->getLanguageCode($anrId);
            $language = in_array($language, ['fr', 'en'], true) ? $language : ($savedState['language'] ?? 'fr');
        } catch (MonarcApiException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $selectedReferentialIds = array_map('intval', $savedState['mosp_referentials'] ?? []);

        try {
            [, $mospRows] = $this->loadMospData($selectedReferentialIds, $language);
        } catch (MonarcApiException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $syncItems = MonarcSyncItem::query()->where('anr_id', $anrId)->get(['model', 'mercator_id', 'sent_at']);
        $rows = $this->rowsFromSyncItems($syncItems, $mospRows);

        // Merges into the draft: the ANR's own label/language and the
        // reconstructed selection become the new name/language/rows;
        // description and mosp_referentials are left exactly as they were —
        // Monarc has no equivalent to derive them from.
        MonarcSelectionState::save([
            'name' => $anr['label'],
            'description' => $savedState['description'] ?? '',
            'language' => $language,
            'mosp_referentials' => $savedState['mosp_referentials'] ?? [],
            'rows' => $rows,
        ]);

        return response()->json([
            'status' => 'ok',
            'anr_id' => $anrId,
            'name' => $anr['label'],
            'language' => $language,
            'rows' => $rows,
            'synced_count' => $syncItems->count(),
            'last_synced_at' => optional($syncItems->max('sent_at'))->toIso8601String(),
        ]);
    }

    /**
     * Persists the selection screen's current form state (name, description,
     * language, chosen referentials, per-row object selections)
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
                'rows.*.objects' => ['nullable', 'array'],
                'rows.*.generic' => ['nullable', 'boolean'],
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
     * empty select, so it's dropped rather than shown inert.
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
                    'view' => MonarcExportService::viewForFamily($families[0]),
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
     * Groups rows by Mercator "view" (see MonarcExportService::FAMILY_VIEWS),
     * in that constant's order, dropping any view with no rows — the
     * empty-section rule.
     *
     * @param  array<int, array{view: string}>  $rows
     * @return array<int, array{key: string, label: string, rows: array}>
     */
    private function rowsByView(array $rows): array
    {
        $grouped = collect($rows)->groupBy('view');

        $sections = [];
        foreach (array_keys(MonarcExportService::FAMILY_VIEWS) as $viewKey) {
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
     * filter, not a hint: a row's select only ever offers
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
        $families = [];
        foreach (self::SIDEBAR_FAMILY_ORDER as $model) {
            // Shared with MonarcExportService's library-category grouping,
            // so both name a family (e.g. "Workstation") identically.
            $label = MonarcExportService::familyLabel($model);

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
     * row's multi-select.
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
     * Flattens the row -> objects[] Mercator-key payload into the flat
     * {model,id,name,asset_uuid,scope} selection buildExport() expects.
     * Re-resolves each item's name from the database — the payload only
     * ever carries "Model:id" strings, never a trusted display name. Only
     * its first row assignment is kept if a key is repeated across rows.
     *
     * A row flagged "generic" contributes ONE synthetic placeholder object
     * instead of its individually-picked Mercator objects (any "objects"
     * still present in the payload for that row — e.g. left over from
     * before the checkbox was ticked — are ignored): the admin wants "one
     * generic object for this whole Monarc asset type" rather than
     * enumerating cartography objects. Its {model,id} pair is a sentinel,
     * never a real Mercator record — "MonarcGenericType:{asset_uuid}" / 0 —
     * unique per asset type (so two different generic rows never collide
     * in monarc_sync_items, and buildLibrary() never lumps unrelated
     * generic placeholders together into one fake "family" sub-category)
     * and stable across syncs (so re-syncing doesn't duplicate it — see
     * MonarcExportService::objectUuid()). Downstream, MonarcExportService
     * treats it exactly like any other object with no cartography
     * relations: an "orphan" promoted to its own root instance, carrying
     * every risk of its own asset_uuid (see buildInstanceRisks()) — there
     * is nothing generic-specific in MonarcExportService at all.
     *
     * Every object is exported with scope=1 ("local"): the UI used to also
     * offer a "global" scope (an object's risk assessed once and shared
     * across every occurrence, vs. once per occurrence) via a second select
     * per row, but that was removed for simplicity — "local" is what
     * actually matches how the composition tree is built (an object placed
     * under N selected parents gets N real instance placements regardless),
     * so nothing about the resulting tree or risk count changes by dropping
     * the "global" option, only the choice itself.
     *
     * @param  array<string, array{asset_uuid: string, objects?: array<int, string>, generic?: bool}>  $rows
     * @param  array<string, string>  $assetLabelByUuid  KB asset_uuid -> its own MOSP label, for naming a generic row's placeholder — re-derived from the freshly-loaded MOSP data, never trusted from the request
     * @return array<int, array{model: string, id: int, name: string, asset_uuid: string, scope: int}>
     */
    private function flattenRowSelection(array $rows, array $assetLabelByUuid = []): array
    {
        $seenKeys = [];
        $selection = [];

        foreach ($rows as $row) {
            $assetUuid = $row['asset_uuid'];

            if (! empty($row['generic'])) {
                $key = 'generic:'.$assetUuid;
                if (isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;

                $selection[] = [
                    'model' => 'MonarcGenericType:'.$assetUuid,
                    'id' => 0,
                    'name' => $assetLabelByUuid[$assetUuid] ?? $assetUuid,
                    'asset_uuid' => $assetUuid,
                    'scope' => 1,
                ];

                continue;
            }

            foreach ($row['objects'] ?? [] as $key) {
                if (isset($seenKeys[$key])) {
                    continue; // already assigned to an earlier row
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
                    'scope' => 1,
                ];
            }
        }

        return $selection;
    }

    /**
     * Migrates a saved selection's rows from the retired {global,local}
     * shape to the current {objects} shape, so a draft saved before the
     * single-list simplification still restores correctly instead of
     * silently losing its selections. New saves always already use
     * "objects" and pass through untouched.
     *
     * @param  array<string, array>  $rows
     * @return array<string, array>
     */
    private function normalizeSavedRows(array $rows): array
    {
        return array_map(function (array $row) {
            if (isset($row['objects'])) {
                return $row;
            }

            $row['objects'] = array_values(array_unique(array_merge($row['global'] ?? [], $row['local'] ?? [])));
            unset($row['global'], $row['local']);

            return $row;
        }, $rows);
    }

    /**
     * Rebuilds the {row_uuid: {asset_uuid, objects?, generic?}} shape
     * initRowSelects()/initGenericToggles() restore from, out of what is
     * actually tracked in monarc_sync_items for the given ANR — see
     * anrRows().
     *
     * A model with several possible asset codes (e.g. Building: BAT_LOC or
     * OV_SALLE_IT) is assigned to whichever of those rows happens to be
     * rendered first: monarc_sync_items never recorded which row the object
     * was originally placed under (only its Mercator model+id), so this is
     * a best-effort reconstruction, not an exact replay of the original
     * selection screen. A tracked object whose Mercator record was deleted
     * since, or whose model maps to no row currently on screen (e.g. the
     * admin narrowed the MOSP referentials), is silently skipped.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, MonarcSyncItem>  $syncItems  already scoped to the target anr_id
     * @param  array<int, array{row_uuid: string, asset_code: string, asset_uuid: string}>  $mospRows
     * @return array<string, array{asset_uuid: string, objects?: array<int, string>, generic?: bool}>
     */
    private function rowsFromSyncItems(\Illuminate\Database\Eloquent\Collection $syncItems, array $mospRows): array
    {
        $rowByCode = collect($mospRows)->keyBy('asset_code');
        $rowByAssetUuid = collect($mospRows)->keyBy('asset_uuid');

        $rowByModel = [];
        foreach (MonarcExportService::DEFAULT_ASSET_CODES as $model => $codes) {
            foreach ($codes as $code) {
                if ($rowByCode->has($code)) {
                    $rowByModel[$model] = $rowByCode[$code];

                    break;
                }
            }
        }

        $rows = [];
        foreach ($syncItems as $item) {
            if (str_starts_with($item->model, 'MonarcGenericType:')) {
                $assetUuid = substr($item->model, strlen('MonarcGenericType:'));
                $row = $rowByAssetUuid->get($assetUuid);
                if ($row !== null) {
                    $rows[$row['row_uuid']]['asset_uuid'] = $assetUuid;
                    $rows[$row['row_uuid']]['generic'] = true;
                }

                continue;
            }

            $row = $rowByModel[$item->model] ?? null;
            $modelClass = self::SELECTABLE_MODELS[$item->model] ?? null;
            if ($row === null || $modelClass === null || ! $modelClass::query()->whereKey($item->mercator_id)->exists()) {
                continue;
            }

            $rowUuid = $row['row_uuid'];
            $rows[$rowUuid]['asset_uuid'] = $row['asset_uuid'];
            $rows[$rowUuid]['objects'][] = $item->model.':'.$item->mercator_id;
        }

        return $rows;
    }
}
