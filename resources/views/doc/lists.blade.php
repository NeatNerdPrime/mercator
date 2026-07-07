@extends('layouts.admin')

@section('title')
    {{ trans('cruds.report.lists.title') }}
@endsection

@section('content')
    <div class="row">

        <div class="col-lg-6">

            <div class="card">
                <div class="card-header">
                    {{ trans("cruds.report.lists.title") }}
                </div>
                <div class="card-body">
                    <ul>
                        @can('entity_access')
                        <li>
                            <a href="{{ route('admin.report.entities') }}"
                               target="_new">{{ trans("cruds.report.lists.entities") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.entities_helper") }}
                            <br>
                            <br>
                        </li>
                        @endcan
                        @can('application_access')
                        <li>
                            <a href="{{ route('admin.report.applicationsByBlocks') }}"
                               target="_new">{{ trans("cruds.report.lists.applications") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.applications_helper") }}
                            <br><br>
                        </li>
                        @endcan
                        <li>
                            <a href="{{ route('admin.report.directory') }}"
                               target="_new">{{ trans("cruds.report.lists.directory") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.directory_helper") }}
                            <br><br>
                        </li>
                        @can('logical_server_access')
                        <li>
                            <a href="{{ route('admin.report.logicalServers') }}"
                               target="_new">{{ trans("cruds.report.lists.logical_servers") }}</a><br>
                            {{ trans("cruds.report.lists.logical_servers_helper") }}
                            <br><br>
                        </li>
                        @endcan
                        <li>
                            <a href="{{ route('admin.report.securityNeeds') }}"
                               target="_new">{{ trans("cruds.report.lists.security_needs") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.security_needs_helper") }}
                            <br><br>
                        </li>
                        @can('vlan_access')
                        <li>
                            <a href="{{ route('admin.report.vlans') }}"
                               target="_new">{{ trans("cruds.report.lists.vlans") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.vlans_helper") }}
                            <br><br>
                        </li>
                        @endcan
                        @can('logical_server_access')
                        <li>
                            <a href="{{ route('admin.report.logicalServerConfigs') }}"
                               target="_new">{{ trans("cruds.report.lists.logical_server_configurations") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.logical_server_configurations_helper") }}
                            <br><br>
                        </li>
                        @endcan
                        @can('backup_access')
                        <li>
                            <a href="{{ route('admin.report.backups') }}"
                               target="_new">{{ trans("cruds.report.lists.backup") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.backup_helper") }}
                            <br><br>
                        </li>
                        @endcan
                        <li>
                            <a href="{{ route('admin.report.externalAccess') }}"
                               target="_new">{{ trans("cruds.report.lists.external_access") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.external_access_helper") }}
                            <br><br>
                        </li>
                        <li>
                            <a href="{{ route('admin.report.physicalInventory') }}"
                               target="_new">{{ trans("cruds.report.lists.physical_inventory") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.physical_inventory_helper") }}
                            <br><br>
                        </li>
                        @can('workstation_access')
                        <li>
                            <a href="{{ route('admin.report.workstations') }}"
                               target="_new">{{ trans("cruds.report.lists.workstation_inventory") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.workstation_inventory_helper") }}
                            <br><br>
                        </li>
                        @endcan
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @can('activity_show')
                <div class="card">
                    <div class="card-header">
                        {{ trans('cruds.report.lists.bia') }}
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>
                                <a href="{{ route('admin.report.view.rto') }}"
                                   target="_new">{{ trans('cruds.report.lists.continuity_needs') }}</a>
                                <br>
                                {{ trans('cruds.report.lists.continuity_needs_helper') }}
                                <br><br>
                            </li>
                            <li>
                                <a href="{{ route('admin.report.view.impacts') }}"
                                   target="_new">{{ trans('cruds.report.lists.impacts') }}</a>
                                <br>
                                {{ trans('cruds.report.lists.impacts_helper') }}
                            </li>
                    </div>
                </div>
                <br>
            @endcan

            <div class="card">
                <div class="card-header">
                    Common Vulnerabilities and Exposures
                </div>
                <div class="card-body">
                    <ul>
                        <li>
                            <a href="/admin/report/cve" target="_new">{{ trans("cruds.report.lists.cve") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.cve_helper") }}
                            <br>
                            <br>
                        </li>
                    </ul>
                </div>
            </div>

            @can('gdpr_access')
                <br>
                <div class="card">
                    <div class="card-header">
                        {{ trans("cruds.report.lists.gdpr") }}
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>
                                <a href="/admin/report/activityReport"
                                   target="_new">{{ trans("cruds.report.lists.register_report") }}</a>
                                <br>
                                {{ trans("cruds.report.lists.register_report") }}
                                <br>
                                <br>
                            </li>
                            <li>
                                <a href="/admin/report/activityList"
                                   target="_new">{{ trans("cruds.report.lists.register_list") }}</a>
                                <br>
                                {{ trans("cruds.report.lists.register_list_helper") }}
                                <br>
                                <br>
                            </li>
                        </ul>
                    </div>
                </div>
            @endcan

            <br>
            <div class="card">
                <div class="card-header">
                    {{ trans("cruds.report.lists.audit") }}
                </div>
                <div class="card-body">
                    <ul>
                        <li>
                            <a href="/admin/audit/maturity" target="_new">{{ trans("cruds.report.lists.maturity") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.maturity_helper") }}
                            <br>
                            <br>
                        </li>
                        <li>
                            <a href="/admin/audit/changes" target="_new">{{ trans("cruds.report.lists.changes") }}</a>
                            <br>
                            {{ trans("cruds.report.lists.changes_helper") }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <br><br><br>
@endsection
