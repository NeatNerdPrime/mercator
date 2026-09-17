@props([
    'securityDevice',
    'withLink' => false,
])
<table class="table table-bordered table-striped table-report" id="{{ $securityDevice->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.securityDevice.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $securityDevice->perimeter->nom }} / @endif
            @if($withLink)
            @canShow($securityDevice)
            <a href="{{ route('admin.security-devices.show', $securityDevice->id) }}">{{ $securityDevice->name }}</a>
            @elsecanShow
            {{ $securityDevice->name }}
            @endcanShow
            @else
            {{ $securityDevice->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.securityDevice.fields.type') }}
            </th>
            <td width="20%">
                {{ $securityDevice->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.securityDevice.fields.attributes') }}
            </th>
            <td width="30%" colspan="2">
                @foreach(explode(" ",$securityDevice->attributes) as $attribute)
                    <span class="badge badge-info">{{ $attribute }}</span>
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.securityDevice.fields.description') }}
            </th>
            <td colspan="5">
                {!! $securityDevice->description !!}
            </td>
            <td width="10%">
                @if ($securityDevice->icon_id === null)
                    <img src='/images/securitydevice.png' width='60' height='60'>
                @else
                    <img src='{{ route('admin.documents.show', $securityDevice->icon_id) }}' width='60'
                         height='60'>
                @endif
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.securityDevice.fields.address_ip') }}
            </th>
            <td colspan="6">
                {{ $securityDevice->address_ip}}
            </td>
        </tr>
        @canAccess(App\Models\Application::class)
        <tr>
            <th>
                {{ trans('cruds.securityDevice.fields.applications') }}
            </th>
            <td colspan="6">
                @foreach($securityDevice->applications as $application)
                    @canShow($application)
                    <a href="{{ route('admin.applications.show', $application->id) }}">{{ $application->name }}</a>
                    @elsecanShow
                        {{ $application->name }}
                    @endcanShow
                    @if(!$loop->last)
                        ,
                    @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
        @canAccess(App\Models\PhysicalSecurityDevice::class)
        <tr>
            <th>
                {{ trans('cruds.securityDevice.fields.physical_security_devices') }}
            </th>
            <td colspan="6">
                @foreach($securityDevice->physicalSecurityDevices as $device)
                    @canShow($device)
                    <a href="{{ route('admin.physical-security-devices.show', $device->id) }}">{{ $device->name }}</a>
                    @elsecanShow
                        {{ $device->name }}
                    @endcanShow
                    @if(!$loop->last)
                        ,
                    @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>
