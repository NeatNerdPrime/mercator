@extends('layouts.admin')

@section('title')
    {{ trans('cruds.report.cartography.title') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header">
                    {{ trans("cruds.report.cartography.title") }}
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.report.cartography') }}" enctype="multipart/form-data"
                          target="_new">
                        @method('PUT')
                        @csrf
                        <div class="row">
                            <div class="col-5">
                                <div class="form-group">
                                    <label for="vues">{{ trans("cruds.report.cartography.views") }}</label>
                                    <div style="padding-bottom: 4px">
                                        <span class="btn btn-info btn-xs select-all"
                                              style="border-radius: 0">{{ trans('global.select_all') }}</span>
                                        <span class="btn btn-info btn-xs deselect-all"
                                              style="border-radius: 0">{{ trans('global.deselect_all') }}</span>
                                    </div>
                                    <select class="form-control select2 {{ $errors->has('vues') ? 'is-invalid' : '' }}"
                                            name="vues[]" id="vues" multiple>
                                        <option value="1">{{ trans("cruds.report.cartography.ecosystem") }}</option>
                                        <option value="2">{{ trans("cruds.report.cartography.information_system") }}</option>
                                        <option value="3">{{ trans("cruds.report.cartography.applications") }}</option>
                                        <option value="4">{{ trans("cruds.report.cartography.administration") }}</option>
                                        <option value="5">{{ trans("cruds.report.cartography.logical_infrastructure") }}</option>
                                        <option value="6">{{ trans("cruds.report.cartography.physical_infrastructure") }}</option>
                                        <option value="7">{{ trans("cruds.report.cartography.gdpr") }}</option>
                                    </select>
                                    @if($errors->has('processes'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('processes') }}
                                        </div>
                                    @endif
                                    <span class="help-block">{{ trans("cruds.report.cartography.views_helper") }}</span>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="form-group">
                                    <div class="form-check form-switch" style="padding-top: 50px;">
                                        <input class="form-check-input" type="checkbox" id="graph" name="graph" checked>
                                        <label class="form-check-label"
                                               for="graph">{{ trans("cruds.report.cartography.graph_helper") }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <button class="btn btn-success" type="submit">
                                        {{ trans ("global.create") }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <br>

    <div class="row">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header">
                    {{ trans('report_template.title') }}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-9">

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <p>
                        <a href="{{ route('admin.report.cartography.template.default') }}"
                           target="_new">{{ trans('report_template.download_default') }}</a>
                    </p>

                    @if ($reportTemplate)
                        <p>
                            <strong>{{ trans('report_template.current_template') }}:</strong>
                            <a href="{{ route('admin.report.cartography.template.current') }}"
                               target="_new">{{ $reportTemplate['original_name'] }}</a>
                            ({{ \Illuminate\Support\Carbon::parse($reportTemplate['uploaded_at'])->format('d/m/Y H:i') }})
                        </p>
                    @else
                        <p>{{ trans('report_template.using_default') }}</p>
                    @endif

                    @can('configure')
                        <form method="POST" action="{{ route('admin.report.cartography.template.upload') }}"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label for="template">{{ trans('report_template.upload') }}</label>
                                <input type="file" name="template" id="template" accept=".docx"
                                       class="form-control {{ $errors->has('template') ? 'is-invalid' : '' }}">
                                @if ($errors->has('template'))
                                    <div class="invalid-feedback">{{ $errors->first('template') }}</div>
                                @endif
                                <span class="help-block">{{ trans('report_template.tags_helper') }}</span>
                            </div>
                            <button class="btn btn-success" type="submit">
                                {{ trans('report_template.upload') }}
                            </button>
                        </form>
                    @endcan
                </div>
                    @can('configure')
                        <div class="col-3">
                            <br><br>
                            <table class="table table-sm table-bordered table-hover" style="font-size: 14px; font-family: var(--font-mono);">
                            <thead class="table-dark">
                              <tr>
                                <th style="width: 42%;">Variable</th>
                                <th>Description</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr><td><code>:content:</code></td><td>Corps du rapport</td></tr>
                              <tr><td><code>:timestamp:</code></td><td>Date et heure de génération</td></tr>
                              <tr><td><code>:version:</code></td><td>version de Mercator</td></tr>
                            </tbody>
                            </table>
                        </div>
                    @endcan
            </div>

        </div>
    </div>
@endsection

@section('scripts')
    @parent
@endsection
