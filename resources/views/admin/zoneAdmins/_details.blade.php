@props([
    'zoneAdmin',
    'withLink' => false
])
<table class="table table-bordered table-striped table-report" id="{{ $zoneAdmin->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.zoneAdmin.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $zoneAdmin->perimeter->nom }} / @endif
            @if ($withLink)
                @canShow($zoneAdmin)
                <a href="{{ route('admin.zone-admins.show', $zoneAdmin->id) }}">{{ $zoneAdmin->name }}</a>
                @elsecanShow
                {{ $zoneAdmin->name }}
                @endcanShow
            @else
                {{ $zoneAdmin->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.zoneAdmin.fields.type') }}
            </th>
            <td width="20%">
                {{ $zoneAdmin->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.zoneAdmin.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $zoneAdmin->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.zoneAdmin.fields.description') }}
            </th>
            <td colspan="5">
                {!! $zoneAdmin->description !!}
            </td>
        </tr>
        @canAccess(App\Models\Annuaire::class)
        <tr>
            <th>
                {{ trans('cruds.zoneAdmin.fields.annuaires') }}
            </th>
            <td colspan="5">
                @foreach($zoneAdmin->annuaires as $annuaire)
                @canShow($annuaire)
                <a href="{{ route('admin.annuaires.show', $annuaire->id) }}">
                    {{ $annuaire->name }}
                </a>
                @elsecanShow
                    {{ $annuaire->name }}
                @endcanShow
                @if ($zoneAdmin->annuaires->last()!=$annuaire)
                    ,
                @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
        @canAccess(App\Models\ForestAd::class)
        <tr>
            <th>
                {{ trans('cruds.zoneAdmin.fields.forests') }}
            </th>
            <td colspan="5">
                @foreach($zoneAdmin->forestAds as $forestAd)
                @canShow($forestAd)
                <a href="{{ route('admin.forest-ads.show', $forestAd->id) }}">
                    {{ $forestAd->name ?? '' }}
                </a>
                @elsecanShow
                    {{ $forestAd->name ?? '' }}
                @endcanShow
                @if ($zoneAdmin->forestAds->last()!=$forestAd)
                    ,
                @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>
