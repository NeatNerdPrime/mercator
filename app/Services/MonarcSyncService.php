<?php

namespace App\Services;

use App\Exceptions\MonarcApiException;
use App\Models\MonarcSyncItem;
use App\Support\MonarcSyncState;
use Illuminate\Support\Carbon;

/**
 * Orchestrates the "Create / Synchronize ANR" workflow from admin/monarc.blade.php:
 * links to an existing Monarc ANR or creates a new one on first use, then on
 * every call only ever imports the Mercator objects (and their risks) not
 * already recorded in monarc_sync_items for that ANR — Monarc's own import
 * always ADDS to the instance tree (see MonarcApiService), so re-sending an
 * already-imported object would duplicate it.
 *
 * Linking to a pre-existing ANR (one not created through this workflow) is
 * inherently riskier for duplicates than creating a fresh one: the local
 * monarc_sync_items table starts empty for that anr_id, so the very first
 * sync into it sends the FULL current selection regardless of whatever that
 * ANR may already contain from another source. This is a deliberate,
 * documented limitation (see the UI's help text), not an oversight — Mercator
 * has no way to inspect an ANR's existing instance tree (see below).
 *
 * Deletions/renames on the Mercator side are out of scope for this workflow:
 * the diff only ever detects and sends ADDITIONS.
 *
 * Not implemented: cross-checking the local monarc_sync_items table against
 * Monarc's own instance tree (GET /api/client-anr/{id}/instances or
 * equivalent) to detect an ANR emptied out-of-band. That endpoint's shape was
 * never verified against a live instance during this feature's development
 * (unlike every other endpoint documented on MonarcApiService), so it is
 * deliberately not called rather than guessed. The local table remains the
 * sole source of truth for the diff; anrExists() only guards against the
 * ANR itself having been deleted.
 */
class MonarcSyncService
{
    public function __construct(
        private MonarcApiService $api,
        private MonarcExportService $exporter,
    ) {}

    /**
     * @param  ?int  $existingAnrId  set when the admin picked an already-existing Monarc ANR from the list
     * @param  ?int  $modelId  the Monarc model to create from — only required when creating a brand new ANR
     * @param  ?string  $anrLabel  the chosen/typed ANR name — required unless already linked
     * @param  string  $analysisName  the export's own "analysis name" (cosmetic only, see MonarcExportService::buildExport()), independent of the ANR's own label
     * @param  array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>  $selection
     * @return array{status: string, anr_id: ?int, anr_label?: string, created: bool, sent_count: int, message: string}
     *
     * @throws MonarcApiException
     */
    public function sync(
        ?int $existingAnrId,
        ?int $modelId,
        ?string $anrLabel,
        string $analysisName,
        string $description,
        string $languageCode,
        array $knowledgeBase,
        array $scalesAndMethod,
        array $selection
    ): array {
        $state = MonarcSyncState::load();
        $currentAnrId = isset($state['anr_id']) ? (int) $state['anr_id'] : null;
        $created = false;
        $labelToPersist = $state['anr_label'] ?? null;
        $anrId = null;

        // The admin can change the sync target at any time — pick a
        // different existing ANR, type a brand new name, or (by leaving the
        // combobox as pre-filled) simply keep resyncing the currently linked
        // one. Only the first case below ever creates something in Monarc.
        if ($existingAnrId !== null) {
            $anrId = $existingAnrId;
            $labelToPersist = $anrLabel ?? $labelToPersist ?? '';
        } elseif (! empty($anrLabel)) {
            if ($modelId === null) {
                throw new MonarcApiException(trans('cruds.monarc.sync.errors.model_required'));
            }

            $anrId = $this->api->createAnr($modelId, $anrLabel, $languageCode === 'en' ? 2 : 1);
            $created = true;
            $labelToPersist = $anrLabel;
        } elseif ($currentAnrId !== null) {
            $anrId = $currentAnrId;
        } else {
            throw new MonarcApiException(trans('cruds.monarc.sync.errors.anr_choice_required'));
        }

        if (! $created) {
            if ($anrId === $currentAnrId) {
                // Resyncing the same ANR as before: a "not found" here means
                // it was deleted out-of-band — report it, don't recreate.
                if (! $this->api->anrExists($anrId)) {
                    return [
                        'status' => 'anr_missing',
                        'anr_id' => $anrId,
                        'created' => false,
                        'sent_count' => 0,
                        'message' => trans('cruds.monarc.sync.errors.anr_missing'),
                    ];
                }
            } elseif (! $this->api->anrExists($anrId)) {
                // A newly picked (or first-ever linked) existing ANR that
                // turns out not to exist is a hard error, not a soft status.
                throw new MonarcApiException(trans('cruds.monarc.sync.errors.anr_not_found'));
            }
        }

        $newSelection = $this->diffSelection($anrId, $selection);

        if ($newSelection === []) {
            $this->persistState($anrId, $labelToPersist, $modelId ?? ($state['model_id'] ?? null), $state['last_synced_at'] ?? null);

            return [
                'status' => 'up_to_date',
                'anr_id' => $anrId,
                'anr_label' => $labelToPersist,
                'created' => $created,
                'sent_count' => 0,
                'message' => trans('cruds.monarc.sync.up_to_date'),
            ];
        }

        $export = $this->exporter->buildExport(
            'analysis',
            $analysisName,
            $description,
            $languageCode,
            $knowledgeBase,
            $scalesAndMethod,
            $newSelection
        );

        $json = json_encode($export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Not persisted unless this succeeds — a failed import must leave
        // monarc_sync_items exactly as it was (see class docblock).
        $this->api->importInstances($anrId, $json);

        $now = Carbon::now();
        foreach ($newSelection as $item) {
            MonarcSyncItem::query()->updateOrCreate(
                ['anr_id' => $anrId, 'model' => $item['model'], 'mercator_id' => $item['id']],
                ['object_uuid' => MonarcExportService::objectUuid($item['model'], (int) $item['id']), 'sent_at' => $now]
            );
        }

        $this->persistState($anrId, $labelToPersist, $modelId ?? ($state['model_id'] ?? null), $now->toIso8601String());

        return [
            'status' => 'synced',
            'anr_id' => $anrId,
            'anr_label' => $labelToPersist,
            'created' => $created,
            'sent_count' => count($newSelection),
            'message' => $created
                ? trans('cruds.monarc.sync.created_and_synced', ['id' => $anrId, 'count' => count($newSelection)])
                : trans('cruds.monarc.sync.synced', ['count' => count($newSelection)]),
        ];
    }

    /**
     * Forgets the local ANR link and every tracked sync item, so the next
     * sync starts from scratch (new or existing ANR, per the admin's next
     * choice). The remote ANR itself is left untouched.
     */
    public function reset(): void
    {
        MonarcSyncItem::query()->delete();
        MonarcSyncState::reset();
    }

    /**
     * @param  array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>  $selection
     * @return array<int, array{model: string, id: int, name: string, asset_uuid: ?string, scope: int}>
     */
    private function diffSelection(int $anrId, array $selection): array
    {
        $alreadySentKeys = MonarcSyncItem::query()
            ->where('anr_id', $anrId)
            ->get(['model', 'mercator_id'])
            ->map(fn ($row) => $row->model.':'.$row->mercator_id)
            ->flip();

        return array_values(array_filter(
            $selection,
            fn (array $item) => ! $alreadySentKeys->has($item['model'].':'.$item['id'])
        ));
    }

    private function persistState(int $anrId, ?string $label, ?int $modelId, ?string $lastSyncedAt): void
    {
        MonarcSyncState::save([
            'anr_id' => $anrId,
            'anr_label' => $label ?? '',
            'model_id' => $modelId,
            'last_synced_at' => $lastSyncedAt,
        ]);
    }
}
