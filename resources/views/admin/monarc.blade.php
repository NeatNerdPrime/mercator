@extends('layouts.admin')

@section('title')
    {{ trans('cruds.monarc.title_short') }}
@endsection

@section('content')

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($apiError)
    <div class="alert alert-danger" role="alert">
        {{ $apiError }}
    </div>
@endif

@if ($viewSections === [])
    <div class="alert alert-info">
        {{ trans('cruds.monarc.no_rows_mosp') }}
    </div>
@else
    <form method="POST" action="{{ route('admin.monarc.export') }}" id="monarc-export-form">
        @csrf

        <div class="card mb-3">
            <div class="card-header">{{ trans('cruds.monarc.analysis_model') }}</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="label-required" for="monarc_name">{{ trans('cruds.monarc.name') }}</label>
                            <input class="form-control" type="text" name="name" id="monarc_name"
                                   value="{{ old('name', $savedState['name'] ?? '') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="monarc_description">{{ trans('cruds.monarc.description') }}</label>
                            <input class="form-control" type="text" name="description" id="monarc_description"
                                   value="{{ old('description', $savedState['description'] ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="monarc_language">{{ trans('cruds.monarc.language') }}</label>
                            <select class="form-control select2" name="language" id="monarc_language">
                                <option value="fr" {{ old('language', $savedState['language'] ?? 'fr') === 'fr' ? 'selected' : '' }}>Français</option>
                                <option value="en" {{ old('language', $savedState['language'] ?? 'fr') === 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ trans('cruds.monarc.source') }}</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="mosp_referentials">{{ trans('cruds.monarc.mosp_referentials') }}</label>
                    <select class="form-control" name="mosp_referentials[]" id="mosp_referentials" multiple style="min-height: 100px;">
                        @foreach ($mospReferentials as $referential)
                            <option value="{{ $referential['id'] }}" {{ in_array($referential['id'], $selectedReferentialIds, true) ? 'selected' : '' }}
                                data-organization="{{ $referential['organization'] }}">
                                {{ $referential['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <span class="help-block">{{ trans('cruds.monarc.mosp_referentials_helper') }}</span>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ trans('cruds.monarc.selection') }}</div>
            <div class="card-body">
                @foreach ($viewSections as $section)
                    <h6 class="fw-bold {{ $loop->first ? '' : 'mt-4' }} mb-2">{{ $section['label'] }}</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 18%;">{{ trans('cruds.monarc.row_families') }}</th>
                                    <th style="width: 8%;">{{ trans('cruds.monarc.row_asset') }}</th>
                                    <th style="width: 19%;">{{ trans('cruds.monarc.row_object') }}</th>
                                    <th style="width: 5%;">{{ trans('cruds.monarc.row_nb_amv') }}</th>
                                    <th style="width: 25%;">{{ trans('cruds.monarc.global_objects') }}</th>
                                    <th style="width: 25%;">{{ trans('cruds.monarc.local_objects') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    <tr>
                                        <td>{{ collect($row['families'])->sortBy(fn ($model) => $familyOrder[$model] ?? PHP_INT_MAX)->map(fn ($model) => $familyLabelByModel[$model] ?? $model)->implode(', ') }}</td>
                                        <td>{{ $row['asset_code'] }}</td>
                                        <td>{{ $row['label'] }}</td>
                                        <td>
                                            @php $riskCount = $amvCountByAssetUuid[$row['asset_uuid']] ?? 0; @endphp
                                            @if ($riskCount > 0)
                                                <a href="#" class="row-risk-count-link"
                                                   data-asset-uuid="{{ $row['asset_uuid'] }}" data-asset-label="{{ $row['label'] }}">{{ $riskCount }}</a>
                                            @else
                                                {{ $riskCount }}
                                            @endif
                                        </td>
                                        <td>
                                            <input type="hidden" class="row-asset-uuid"
                                                   name="rows[{{ $row['row_uuid'] }}][asset_uuid]"
                                                   data-row-uuid="{{ $row['row_uuid'] }}" value="{{ $row['asset_uuid'] }}">
                                            <select class="form-control row-global" multiple
                                                    data-row-uuid="{{ $row['row_uuid'] }}" data-asset-code="{{ $row['asset_code'] }}"
                                                    name="rows[{{ $row['row_uuid'] }}][global][]"></select>
                                        </td>
                                        <td>
                                            <select class="form-control row-local" multiple
                                                    data-row-uuid="{{ $row['row_uuid'] }}" data-asset-code="{{ $row['asset_code'] }}"
                                                    name="rows[{{ $row['row_uuid'] }}][local][]"></select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <strong>{{ trans('cruds.monarc.risk_count') }} : <span id="monarc-risk-count">0</span></strong>
            </div>
        </div>

        <div class="form-group">
            <button class="btn btn-success" type="submit">
                <i class="fas fa-file-export me-1"></i>{{ trans('cruds.monarc.export_button') }}
            </button>
            <button class="btn btn-secondary" type="submit" formaction="{{ route('admin.monarc.save') }}" formnovalidate>
                {{ trans('global.save') }}
            </button>
            <button class="btn btn-outline-secondary" type="button" id="monarc-clear-btn">
                {{ trans('cruds.monarc.clear_button') }}
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.home') }}">
                {{ trans('global.cancel') }}
            </a>
        </div>
    </form>

    <div class="modal fade" id="monarcRisksModal" tabindex="-1" aria-labelledby="monarcRisksModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="monarcRisksModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('cruds.monarc.risks_modal_close') }}"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ trans('cruds.monarc.risks_modal_threat') }}</th>
                                <th>{{ trans('cruds.monarc.risks_modal_vulnerability') }}</th>
                            </tr>
                        </thead>
                        <tbody id="monarcRisksModalBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('cruds.monarc.risks_modal_close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection

@section('scripts')
@parent
<script>
(function () {
    'use strict';

    var families = @json($familiesForJs);
    var familiesByAssetCode = @json($familiesByAssetCode);
    var relations = @json($relations);
    var amvCountByAssetUuid = @json($amvCountByAssetUuid);
    var risksByAssetUuid = @json($risksByAssetUuid);
    var primaryFamilies = @json($primaryFamilies);
    var savedRows = @json($savedRows);
    var exclusiveErrorMessage = @json(trans('cruds.monarc.exclusive_error'));
    var risksModalEmptyMessage = @json(trans('cruds.monarc.risks_modal_empty'));

    document.addEventListener('DOMContentLoaded', function () {
        initRowSelects();
        initRiskCounter();
        initRisksModal();
        initClearButton();
    });

    // ── Zone 2 : Select2 multiple par ligne (global / local) ───────────────
    // Strict filter (not just a sort hint): a row only ever offers objects
    // from the Mercator families mapped to its asset code (server-side
    // mirror: MonarcController::familiesByAssetCode() / rowsFromAssets()).
    function select2DataForRow(assetCode) {
        var allowedModels = familiesByAssetCode[assetCode] || [];

        return families
            .filter(function (family) { return allowedModels.indexOf(family.model) !== -1; })
            .map(function (family) { return { text: family.label, children: family.items }; });
    }

    function initRowSelects() {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            return;
        }

        $('.row-global, .row-local').each(function () {
            var $sel = $(this);
            $sel.select2({
                width: '100%',
                data: select2DataForRow($sel.data('asset-code')),
                placeholder: '',
            });

            // Restore a previously saved/exported selection, if any.
            var rowUuid = $sel.data('row-uuid');
            var isGlobal = $sel.hasClass('row-global');
            var saved = (savedRows[rowUuid] || {})[isGlobal ? 'global' : 'local'] || [];
            if (saved.length > 0) {
                $sel.val(saved).trigger('change.select2');
            }
        });

        // Exclusivité : un objet choisi dans un select ne peut pas rester
        // sélectionné dans l'autre select de la MÊME ligne.
        $('.row-global, .row-local').on('change', function () {
            var $sel = $(this);
            var rowUuid = $sel.data('row-uuid');
            var isGlobal = $sel.hasClass('row-global');
            var $other = $('.' + (isGlobal ? 'row-local' : 'row-global') + '[data-row-uuid="' + rowUuid + '"]');
            var mine = $sel.val() || [];
            var otherVal = $other.val() || [];
            var conflict = otherVal.filter(function (v) { return mine.indexOf(v) !== -1; });

            if (conflict.length > 0) {
                var cleaned = otherVal.filter(function (v) { return conflict.indexOf(v) === -1; });
                $other.val(cleaned).trigger('change.select2');
                window.alert(exclusiveErrorMessage);
            }

            computeRiskCount();
        });
    }

    // ── Zone 3 : compteur de risques (miroir exact de countRisks()) ────────
    function selectedSet() {
        var set = {};
        document.querySelectorAll('.row-global, .row-local').forEach(function (sel) {
            ($(sel).val() || []).forEach(function (key) { set[key] = true; });
        });
        return set;
    }

    function occurrenceCount(key, selected, memo, visiting) {
        if (Object.prototype.hasOwnProperty.call(memo, key)) {
            return memo[key];
        }
        var model = key.split(':')[0];
        if (primaryFamilies.indexOf(model) !== -1) {
            return memo[key] = 1;
        }
        if (visiting[key]) {
            return 1;
        }
        visiting[key] = true;

        var groups = relations[key] || [];
        var parents = [];
        for (var i = 0; i < groups.length; i++) {
            var matched = groups[i].filter(function (p) { return selected[p]; });
            if (matched.length > 0) {
                parents = matched;
                break;
            }
        }

        var result;
        if (parents.length === 0) {
            result = 1;
        } else {
            result = 0;
            parents.forEach(function (p) {
                result += occurrenceCount(p, selected, memo, visiting);
            });
        }
        delete visiting[key];

        return memo[key] = result;
    }

    function computeRiskCount() {
        var selected = selectedSet();
        var memo = {};
        var visiting = {};
        var total = 0;

        document.querySelectorAll('.row-global, .row-local').forEach(function (sel) {
            var rowUuid = sel.getAttribute('data-row-uuid');
            var assetUuidInput = document.querySelector('.row-asset-uuid[data-row-uuid="' + rowUuid + '"]');
            var assetUuid = assetUuidInput ? assetUuidInput.value : '';
            var n = amvCountByAssetUuid[assetUuid] || 0;
            var isGlobal = sel.classList.contains('row-global');
            var keys = $(sel).val() || [];

            keys.forEach(function (key) {
                if (isGlobal) {
                    total += n;
                } else {
                    total += n * occurrenceCount(key, selected, memo, visiting);
                }
            });
        });

        var el = document.getElementById('monarc-risk-count');
        if (el) {
            el.textContent = total;
        }
    }

    function initRiskCounter() {
        computeRiskCount();
    }

    // ── Zone 4 : détail des risques d'un actif (modale) ─────────────────────
    function initRisksModal() {
        document.querySelectorAll('.row-risk-count-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                showRisksModal(this.getAttribute('data-asset-uuid'), this.getAttribute('data-asset-label'));
            });
        });
    }

    function showRisksModal(assetUuid, assetLabel) {
        var modalEl = document.getElementById('monarcRisksModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }

        document.getElementById('monarcRisksModalLabel').textContent = assetLabel;

        var tbody = document.getElementById('monarcRisksModalBody');
        tbody.innerHTML = '';
        var risks = risksByAssetUuid[assetUuid] || [];

        if (risks.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 2;
            emptyCell.className = 'text-muted';
            emptyCell.textContent = risksModalEmptyMessage;
            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
        } else {
            risks.forEach(function (risk) {
                var row = document.createElement('tr');
                var threatCell = document.createElement('td');
                threatCell.textContent = risk.threat;
                var vulnCell = document.createElement('td');
                vulnCell.textContent = risk.vulnerability;
                row.appendChild(threatCell);
                row.appendChild(vulnCell);
                tbody.appendChild(row);
            });
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // ── Bouton "Effacer" : réinitialise le formulaire affiché sans rien
    // sauvegarder — un rechargement de page restaure le dernier état enregistré.
    function initClearButton() {
        var btn = document.getElementById('monarc-clear-btn');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            document.getElementById('monarc_name').value = '';
            document.getElementById('monarc_description').value = '';

            var referentials = document.getElementById('mosp_referentials');
            if (referentials) {
                Array.from(referentials.options).forEach(function (opt) { opt.selected = false; });
            }

            $('.row-global, .row-local').val(null).trigger('change.select2');
            computeRiskCount();
        });
    }
})();
</script>
@endsection
