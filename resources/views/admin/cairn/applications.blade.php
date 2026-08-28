{{--
    Canevas Cairn (vue applicative). Partial destiné à être inclus par les vues create/edit du
    contrôleur resourceful CairnController :

        @include('admin.cairn.applications', ['initialSelection' => $cairn->selection ?? []])

    La page qui l'inclut doit charger le module Vite associé, par exemple :

        @section('scripts')
            @parent
            @vite(['resources/js/cairn.js'])
        @endsection
--}}
<div id="cairn-app"
     data-search-url="{{ route('admin.cairn.search') }}"
     data-generate-url="{{ route('admin.cairn.generate') }}"
     data-initial-selection="{{ json_encode($initialSelection ?? []) }}"
     data-i18n-no-selection="{{ trans('cruds.cairn.fields.no_selection') }}"
     data-i18n-error-generate="{{ trans('cruds.cairn.fields.error_generate') }}"
     data-i18n-remove="{{ trans('global.delete') }}">

    <div class="card mb-3">
        <div class="card-header">
            {{ trans('cruds.cairn.fields.selection') }}
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="cairn-type" class="form-label">{{ trans('cruds.cairn.fields.type') }}</label>
                    <select id="cairn-type" class="form-control">
                        <option value="entity">{{ trans('cruds.entity.title') }}</option>
                        <option value="application" selected>{{ trans('cruds.application.title') }}</option>
                        <option value="service">{{ trans('cruds.applicationService.title') }}</option>
                        <option value="module">{{ trans('cruds.applicationModule.title') }}</option>
                        <option value="database">{{ trans('cruds.database.title') }}</option>
                        <option value="flux">{{ trans('cruds.applicationFlow.title') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="cairn-object" class="form-label">{{ trans('cruds.cairn.fields.object') }}</label>
                    <select id="cairn-object" class="form-control"></select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" id="cairn-add" class="btn btn-primary flex-fill">{{ trans('cruds.cairn.fields.add') }}</button>
                    <button type="button" id="cairn-clear" class="btn btn-danger flex-fill">{{ trans('cruds.cairn.fields.clear') }}</button>
                </div>
            </div>
        </div>
        <ul id="cairn-selection-list" class="list-group list-group-flush"></ul>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            {{ trans('cruds.cairn.fields.diagram') }}
            <a href="https://github.com/R0kshan/cairn/" target="_blank" rel="noopener noreferrer" class="small">
                <i class="bi bi-box-arrow-up-right"></i> {{ trans('cruds.cairn.fields.documentation') }}
            </a>
        </div>
        <div class="card-body">
            <div id="cairn-diagnostics" class="d-none mb-2"></div>
            <div id="cairn-loading" class="d-none text-center text-muted py-3">
                <span class="spinner-border spinner-border-sm me-1"></span> {{ trans('cruds.cairn.fields.loading') }}
            </div>
            <div id="cairn-prompt" class="text-center text-muted py-5">
                {{ trans('cruds.cairn.fields.prompt_empty') }}
            </div>
            <div id="cairn-diagram" style="overflow: auto;"></div>
        </div>
    </div>
</div>
