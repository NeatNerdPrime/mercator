@props([
    'networkSwitch',
    'withLink' => false
])
<table class="table table-bordered table-striped table-report" id="{{ $networkSwitch->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ trans('cruds.networkSwitch.fields.name') }}
            </th>
            <td width="20%">
            @if ($withLink)
            @canShow($networkSwitch)
            <a href="{{ route('admin.network-switches.show', $networkSwitch->id) }}">{{ $networkSwitch->name }}</a>
            @elsecanShow
            {{ $networkSwitch->name }}
            @endcanShow
            @else
            {{ $networkSwitch->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.networkSwitch.fields.type') }}
            </th>
            <td width="20%">
                {{ $networkSwitch->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.networkSwitch.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $networkSwitch->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.networkSwitch.fields.description') }}
            </th>
            <td colspan="5">
                {!! $networkSwitch->description !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.networkSwitch.fields.ip') }}
            </th>
            <td colspan="5">
                {{ $networkSwitch->ip }}
            </td>
        </tr>
        @canAccess(App\Models\PhysicalSwitch::class)
        <tr>
            <th>
                {{ trans('cruds.networkSwitch.fields.physical_switches') }}
            </th>
            <td colspan="5">
                @foreach($networkSwitch->physicalSwitches as $physicalSwitch)
                    @canShow($physicalSwitch)
                        <a href="{{ route('admin.physical-switches.show', $physicalSwitch->id) }}">
                        {{ $physicalSwitch->name }}
                        </a>
                    @elsecanShow
                        {{ $physicalSwitch->name }}
                    @endcanShow
                    @if (!$loop->last)
                    ,
                    @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>
