@props([
    'backup',
    'withLink' => false,
])
<table class="table table-bordered table-striped table-report">
    <tbody>
    <tr>
        <th width="10%">{{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.backup.fields.name') }}</th>
        <td width="30%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $backup->perimeter->name }} / @endif
            @if($withLink)
                @canShow($backup)
                <a href="{{ route('admin.backups.show', $backup->id) }}">{{ $backup->name }}</a>
                @elsecanShow
                {{ $backup->name }}
                @endcanShow
            @else
                {{ $backup->name }}
            @endif
        </td>
        <th width="10%">{{ trans('cruds.backup.fields.type') }}</th>
        <td width="20%">{{ $backup->type }}</td>
        <th width="10%">{{ trans('cruds.backup.fields.attributes') }}</th>
        <td width="20%">{{ $backup->attributes }}</td>
    </tr>
    <tr>
        <th>{{ trans('cruds.backup.fields.description') }}</th>
        <td colspan="5">{!! $backup->description !!}</td>
    </tr>
    <tr>
        <th>{{ trans('cruds.backup.frequency') }}</th>
        <td>{{ $backup->backup_frequency ? trans("cruds.backup.frequencies.{$backup->backup_frequency}") : '' }}</td>
        <th>{{ trans('cruds.backup.cycle') }}</th>
        <td>{{ $backup->backup_cycle ? trans("cruds.backup.cycles.{$backup->backup_cycle}") : '' }}</td>
        <th>{{ trans('cruds.backup.retention') }}</th>
        <td>{{ $backup->backup_retention ? $backup->backup_retention . ' ' . trans('cruds.backup.retention_unit') : '' }}</td>
    </tr>
    @canAccess(App\Models\LogicalServer::class)
    <tr>
        <th>{{ trans('cruds.backup.fields.logical_servers') }}</th>
        <td colspan="5">
            @foreach($backup->logicalServers as $server)
                @canShow($server)
                    <a href="{{ route('admin.logical-servers.show', $server->id) }}">{{ $server->name }}</a>@if(!$loop->last), @endif
                @elsecanShow
                    {{ $server->name }}@if(!$loop->last), @endif
                @endcanShow
            @endforeach
        </td>
    </tr>
    @endcanAccess
    @canAccess(App\Models\StorageDevice::class)
    <tr>
        <th>{{ trans('cruds.backup.fields.storage_devices') }}</th>
        <td colspan="5">
            @foreach($backup->storageDevices as $device)
                @canShow($device)
                    <a href="{{ route('admin.storage-devices.show', $device->id) }}">{{ $device->name }}</a>@if(!$loop->last), @endif
                @elsecanShow
                    {{ $device->name }}@if(!$loop->last), @endif
                @endcanShow
            @endforeach
        </td>
    </tr>
    @endcanAccess
    </tbody>
</table>
