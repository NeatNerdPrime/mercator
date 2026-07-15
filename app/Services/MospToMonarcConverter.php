<?php

namespace App\Services;

/**
 * Converts MOSP's flat base catalog (see MospService's docblock: "Assets",
 * "Threats", "Vulnerabilities", "Risks" schemas) plus one or more selected
 * "Security referentials" (measure catalogs) into the knowledgeBase shape
 * MonarcExportService expects — the same shape as the ANR export format in
 * tests/fixtures/templates/monarc.json. All MOSP uuids are preserved as-is;
 * only presentation fields are translated (asset type name -> int, threat
 * booleans -> 0/1, theme label -> {id, label} with a locally generated
 * stable id), mirroring MONARC's own import-side conversion
 * (adaptOldObjectDataStructureToNewFormat in zm-client's ObjectImportService
 * does the equivalent translation in reverse).
 */
class MospToMonarcConverter
{
    /**
     * @param  array<int, array>  $assets  raw MospService::getBaseAssets()
     * @param  array<int, array>  $threats  raw MospService::getBaseThreats()
     * @param  array<int, array>  $vulnerabilities  raw MospService::getBaseVulnerabilities()
     * @param  array<int, array>  $risks  raw MospService::getBaseRisks() (the AMV catalog)
     * @param  array<int, array>  $referentialObjects  raw MospService::getReferentialData() results, one per user-selected referential
     */
    public function convert(array $assets, array $threats, array $vulnerabilities, array $risks, array $referentialObjects): array
    {
        [$referentialsOut, $measureUuids] = $this->convertReferentials($referentialObjects);

        return [
            'assets' => collect($assets)->unique('uuid')->map(fn (array $a) => $this->convertAsset($a))->values()->all(),
            'threats' => $this->convertThreats($threats),
            'vulnerabilities' => collect($vulnerabilities)->unique('uuid')->map(fn (array $v) => $this->convertVulnerability($v))->values()->all(),
            'informationRisks' => collect($risks)->unique('uuid')->map(fn (array $r) => $this->convertRisk($r, $measureUuids))->values()->all(),
            'referentials' => $referentialsOut,
            'rolfTags' => [],
            'operationalRisks' => [],
            'recommendationSets' => [],
        ];
    }

    private function convertAsset(array $asset): array
    {
        return [
            'uuid' => $asset['uuid'],
            'code' => $asset['code'] ?? '',
            'label' => $asset['label'] ?? '',
            'description' => $asset['description'] ?? '',
            'type' => ($asset['type'] ?? '') === 'Primary' ? 1 : 2,
            'status' => 1,
        ];
    }

    /** @return array<int, array> */
    private function convertThreats(array $threats): array
    {
        $themeIdsByLabel = [];
        $nextThemeId = 1;

        return collect($threats)->unique('uuid')->map(function (array $threat) use (&$themeIdsByLabel, &$nextThemeId) {
            $themeLabel = $threat['theme'] ?? '';
            if (! isset($themeIdsByLabel[$themeLabel])) {
                $themeIdsByLabel[$themeLabel] = $nextThemeId++;
            }

            return [
                'uuid' => $threat['uuid'],
                'label' => $threat['label'] ?? '',
                'description' => $threat['description'] ?? '',
                'theme' => ['id' => $themeIdsByLabel[$themeLabel], 'label' => $themeLabel],
                'status' => 1,
                'mode' => 0,
                'code' => $threat['code'] ?? '',
                'confidentiality' => ! empty($threat['c']) ? 1 : 0,
                'integrity' => ! empty($threat['i']) ? 1 : 0,
                'availability' => ! empty($threat['a']) ? 1 : 0,
                'trend' => -1,
                'comment' => '',
                'qualification' => -1,
            ];
        })->values()->all();
    }

    private function convertVulnerability(array $vulnerability): array
    {
        return [
            'uuid' => $vulnerability['uuid'],
            'label' => $vulnerability['label'] ?? '',
            'description' => $vulnerability['description'] ?? '',
            'code' => $vulnerability['code'] ?? '',
            'status' => 1,
        ];
    }

    /**
     * @param  array<int, array>  $referentialObjects
     * @return array{0: array<int, array>, 1: array<string, bool>} [referentials, measure uuid set]
     */
    private function convertReferentials(array $referentialObjects): array
    {
        $referentialsByUuid = [];
        $seenMeasureUuids = [];

        foreach ($referentialObjects as $referential) {
            $referentialUuid = $referential['uuid'] ?? null;
            if ($referentialUuid === null || isset($referentialsByUuid[$referentialUuid])) {
                continue;
            }

            $measures = [];
            foreach ($referential['values'] ?? [] as $value) {
                $measureUuid = $value['uuid'] ?? null;
                if ($measureUuid === null || isset($seenMeasureUuids[$measureUuid])) {
                    continue;
                }

                $measures[] = [
                    'uuid' => $measureUuid,
                    'code' => $value['code'] ?? '',
                    'label' => $value['label'] ?? '',
                    'referential' => [
                        'uuid' => $value['referential'] ?? $referentialUuid,
                        'label' => $value['referential_label'] ?? ($referential['label'] ?? ''),
                    ],
                    'category' => ['label' => $value['category'] ?? ''],
                ];
                $seenMeasureUuids[$measureUuid] = true;
            }

            $referentialsByUuid[$referentialUuid] = [
                'uuid' => $referentialUuid,
                'label' => $referential['label'] ?? '',
                'measures' => $measures,
            ];
        }

        return [array_values($referentialsByUuid), $seenMeasureUuids];
    }

    /**
     * @param  array<string, bool>  $measureUuids  measures actually present in the selected referentials
     */
    private function convertRisk(array $risk, array $measureUuids): array
    {
        // Drop measure references that don't resolve within the selected
        // referential(s) — a dangling uuid would break MONARC's own
        // referential integrity checks on import.
        $measures = array_values(array_filter(
            $risk['measures'] ?? [],
            fn (string $measureUuid) => isset($measureUuids[$measureUuid])
        ));

        return [
            'uuid' => $risk['uuid'],
            'asset' => ['uuid' => $risk['asset'] ?? null],
            'threat' => ['uuid' => $risk['threat'] ?? null],
            'vulnerability' => ['uuid' => $risk['vulnerability'] ?? null],
            'measures' => array_map(fn (string $measureUuid) => ['uuid' => $measureUuid], $measures),
            'status' => 1,
        ];
    }
}
