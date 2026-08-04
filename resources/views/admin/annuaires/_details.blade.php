@props([
    'annuaire',
    'withLink' => false,
])
<table class="table table-bordered table-striped table-report" id="{{ $annuaire->getUID() }}">
    <tbody>
        <tr>
            <th width='10%'>
                {{ trans('cruds.annuaire.fields.name') }}
            </th>
            <td>
            @if ($withLink)
            @canShow($annuaire)
            <a href="{{ route('admin.annuaires.show', $annuaire->id) }}">{{ $annuaire->name }}</a>
            @elsecanShow
            {{ $annuaire->name }}
            @endcanShow
            @else
                {{ $annuaire->name }}
            @endif
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.annuaire.fields.description') }}
            </th>
            <td>
                {!! $annuaire->description !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.annuaire.fields.solution') }}
            </th>
            <td>
                {{ $annuaire->solution }}
            </td>
        </tr>
        @canAccess(App\Models\ZoneAdmin::class)
        <tr>
            <th>
                {{ trans('cruds.annuaire.fields.zone_admin') }}
            </th>
            <td>
                @if ($annuaire->zoneAdmin!=null)
                    @canShow($annuaire->zoneAdmin)
                        <a href="{{ route('admin.zone-admins.show', $annuaire->zoneAdmin->id) }}">{{ $annuaire->zoneAdmin->name }}</a>
                    @elsecanShow
                        {{ $annuaire->zoneAdmin->name }}
                    @endcanShow
                @endif
            </td>
        </tr>
        @endcanAccess
        @canAccess(App\Models\Application::class)
        <tr>
            <th>
                {{ trans('cruds.annuaire.fields.application') }}
            </th>
            <td>
                @if ($annuaire->application != null)
                    @canShow($annuaire->application)
                        <a href="{{ route('admin.applications.show', $annuaire->application->id) }}">{{ $annuaire->application->name }}</a>
                    @elsecanShow
                        {{ $annuaire->application->name }}
                    @endcanShow
                @endif
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>
