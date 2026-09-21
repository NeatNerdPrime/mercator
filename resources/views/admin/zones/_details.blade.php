@props([
    'zone',
    'withLink' => false
])
<table class="table table-bordered table-striped table-report" id="{{ $zone->getUID() }}">
    <tbody>
    <tr>
        <th width="10%">{{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.zone.fields.name') }}</th>
        <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $zone->perimeter->name }} / @endif
        @if($withLink)
        @canShow($zone)
        <a href="{{ route('admin.zones.show', $zone->id) }}">{{ $zone->name }}</a>
        @elsecanShow
        {{ $zone->name }}
        @endcanShow
        @else
        {{ $zone->name }}
        @endif
        </td>
        <th width="10%">{{ trans('cruds.zone.fields.type') }}</th>
        <td width="20%">{{ $zone->type }}</td>
        <th width="10%">{{ trans('cruds.zone.fields.attributes') }}</th>
        <td width="30%">
        @foreach(explode(" ",$zone->attributes) as $attribute)
            <span class="badge badge-info">{{ $attribute }}</span>
        @endforeach
        </td>
    </tr>
    <tr>
        <th>{{ trans('cruds.zone.fields.description') }}</th>
        <td colspan="5">{!! $zone->description !!}</td>
    </tr>
    @canAccess(App\Models\Zone::class)
    <tr>
        <th>{{ trans('cruds.zone.fields.parent_zones') }}</th>
        <td colspan="2">
            @foreach($zone->parentZones as $parentZone)
                @canShow($parentZone)
                <a href="{{ route('admin.zones.show', $parentZone->id) }}">{{ $parentZone->name }}</a>
                @elsecanShow
                {{ $parentZone->name }}
                @endcanShow{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </td>
        <th>{{ trans('cruds.zone.fields.child_zones') }}</th>
        <td colspan="2">
            @foreach($zone->childZones as $childZone)
                @canShow($childZone)
                    <a href="{{ route('admin.zones.show', $childZone->id) }}">{{ $childZone->name }}</a>
                @elsecanShow
                    {{ $childZone->name }}
                @endcanShow{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </td>
    </tr>
    @endcanAccess
    @canAccess(App\Models\Building::class)
    <tr>
        <th>{{ trans('cruds.zone.fields.buildings') }}</th>
        <td colspan="5">
            @foreach($zone->buildings as $building)
                @canShow($building)
                <a href="{{ route('admin.buildings.show', $building->id) }}">{{ $building->name }}</a>
                @elsecanShow
                {{ $building->name }}
                @endcanShow{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </td>
    </tr>
    @endcanAccess
    @canAccess(App\Models\AdminUser::class)
    <tr>
        <th>{{ trans('cruds.zone.fields.admin_users') }}</th>
        <td colspan="5">
            @foreach($zone->adminUsers as $adminUser)
                @canShow($adminUser)
                <a href="{{ route('admin.admin-users.show', $adminUser->id) }}">{{ $adminUser->user_id }}</a>
                @elsecanShow
                {{ $adminUser->user_id }}
                @endcanShow{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </td>
    </tr>
    @endcanAccess
    </tbody>
</table>
