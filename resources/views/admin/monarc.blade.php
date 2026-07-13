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

@if ($apiError)
    <div class="alert alert-danger" role="alert">
        {{ $apiError }}
    </div>
@endif

<form method="GET" action="{{ route('admin.monarc') }}" class="mb-3">
    <div class="row">
        <div class="col-md-4">
            <label class="label-required" for="anr">{{ trans('cruds.monarc.source_anr') }}</label>
            <select class="form-select" name="anr" id="anr">
                @foreach ($anrs as $anr)
                    <option value="{{ $anr['id'] }}" {{ $selectedAnrId == $anr['id'] ? 'selected' : '' }}>
                        {{ $anr['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-secondary" type="submit">{{ trans('global.apply') }}</button>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('admin.monarc.export') }}">
    @csrf
    <input type="hidden" name="anr_id" value="{{ $selectedAnrId }}">

    <div class="card mb-3">
        <div class="card-header">{{ trans('cruds.monarc.analysis_model') }}</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="label-required" for="monarc_name">{{ trans('cruds.monarc.name') }}</label>
                        <input class="form-control" type="text" name="name" id="monarc_name"
                               value="{{ old('name') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="monarc_description">{{ trans('cruds.monarc.description') }}</label>
                        <input class="form-control" type="text" name="description" id="monarc_description"
                               value="{{ old('description') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="monarc_language">{{ trans('cruds.monarc.language') }}</label>
                        <select class="form-select" name="language" id="monarc_language">
                            <option value="fr" {{ old('language', 'fr') === 'fr' ? 'selected' : '' }}>Français</option>
                            <option value="en" {{ old('language') === 'en' ? 'selected' : '' }}>English</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="d-block">{{ trans('cruds.monarc.export_mode') }}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode_library"
                                   value="library" {{ old('mode', 'library') === 'library' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mode_library">
                                {{ trans('cruds.monarc.mode_library') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode_analysis"
                                   value="analysis" {{ old('mode') === 'analysis' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mode_analysis">
                                {{ trans('cruds.monarc.mode_analysis') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ trans('cruds.monarc.selection') }}</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 2%;"></th>
                            <th>{{ trans('cruds.monarc.object') }}</th>
                            <th>{{ trans('cruds.monarc.family') }}</th>
                            <th style="width: 25%;">{{ trans('cruds.monarc.asset_type') }}</th>
                            <th style="width: 12%;">{{ trans('cruds.monarc.scope') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($families as $family)
                            @php($defaultAssetUuid = optional($assets->firstWhere('code', $defaultAssetCodes[$family['model']] ?? null))['uuid'])
                            @foreach ($family['items'] as $item)
                                @php($key = $family['model'].':'.$item->id)
                                <tr>
                                    <td>
                                        <input class="form-check-input monarc-select" type="checkbox"
                                               name="selection[{{ $key }}][checked]" value="1"
                                               id="chk_{{ $key }}" data-key="{{ $key }}">
                                    </td>
                                    <td>
                                        <label for="chk_{{ $key }}">{{ $item->name }}</label>
                                        <input type="hidden" name="selection[{{ $key }}][model]" value="{{ $family['model'] }}">
                                        <input type="hidden" name="selection[{{ $key }}][id]" value="{{ $item->id }}">
                                    </td>
                                    <td>{{ $family['label'] }}</td>
                                    <td>
                                        <select class="form-select form-select-sm monarc-asset"
                                                name="selection[{{ $key }}][asset_uuid]" data-key="{{ $key }}">
                                            <option value=""></option>
                                            @foreach ($assets as $asset)
                                                <option value="{{ $asset['uuid'] }}"
                                                    {{ $defaultAssetUuid === $asset['uuid'] ? 'selected' : '' }}>
                                                    {{ $asset['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm monarc-scope"
                                                name="selection[{{ $key }}][scope]">
                                            <option value="2">{{ trans('cruds.monarc.global') }}</option>
                                            <option value="1" selected>{{ trans('cruds.monarc.local') }}</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
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
    </div>
</form>

@endsection

@section('scripts')
@parent
<script>
(function () {
    'use strict';

    var amvCountByAssetUuid = @json($amvCountByAssetUuid);

    function computeRiskCount() {
        var total = 0;

        document.querySelectorAll('.monarc-select').forEach(function (checkbox) {
            if (!checkbox.checked) {
                return;
            }
            var key = checkbox.getAttribute('data-key');
            var select = document.querySelector('.monarc-asset[data-key="' + key + '"]');
            var uuid = select ? select.value : '';
            total += amvCountByAssetUuid[uuid] || 0;
        });

        var el = document.getElementById('monarc-risk-count');
        if (el) {
            el.textContent = total;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.monarc-select, .monarc-asset').forEach(function (el) {
            el.addEventListener('change', computeRiskCount);
        });
        computeRiskCount();
    });
})();
</script>
@endsection
