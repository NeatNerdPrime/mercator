@props([
    'actor',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $cluster->getUID() }}">
    <tbody>
    <tr>
        <th width="10%">
            {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.cluster.fields.name') }}
        </th>
        <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $cluster->perimeter->name }} / @endif
        @if($withLink)
            @canShow($cluster)
            <a href="{{ route('admin.clusters.show', $cluster->id) }}">{{ $cluster->name }}</a>
            @elsecanShow
            {{ $cluster->name }}
            @endcanShow
        @else
            {{ $cluster->name }}
        @endif
        </td>
        <th width="10%">
            {{ trans('cruds.cluster.fields.type') }}
        </th>
        <td width="20%">
            {{ $cluster->type }}
        </td>
        <th width="10%">
            {{ trans('cruds.cluster.fields.attributes') }}
        </th>
        <td width="30%" colspan="2">
            @foreach(explode(" ",$cluster->attributes) as $attribute)
                <span class="badge badge-info">{{ $attribute }}</span>
            @endforeach
        </td>
    </tr>
    <tr>
        <th>
            {{ trans('cruds.cluster.fields.description') }}
        </th>
        <td colspan="5">
            {!! $cluster->description !!}
        </td>
        <td width="10%">
            @if ($cluster->icon_id === null)
                <img src='/images/cluster.png' width='60' height='60'>
            @else
                <img src='{{ route('admin.documents.show', $cluster->icon_id) }}' width='60' height='60'>
            @endif
        </td>
    </tr>
    <tr>
        <th>
            {{ trans('cruds.cluster.fields.address_ip') }}
        </th>
        <td colspan="6">
            {{ $cluster->address_ip }}
        </td>
    </tr>
    @canAccessAny(App\Models\LogicalServer::class, App\Models\Router::class)
    <tr>
        <th>
            {{ trans('cruds.cluster.fields.logical_servers') }} / {{ 'Routers' }}
        </th>
        <td colspan="6">
            @foreach($cluster->logicalServers as $server)
                @canShow($server)
                    <a href="{{ route('admin.logical-servers.show', $server->id) }}">
                        {{ $server->name }}
                    </a>
                @elsecanShow
                    {{ $server->name }}
                @endcanShow
            @endforeach
            @foreach($cluster->routers as $router)
                @canShow($router)
                    <a href="{{ route('admin.routers.show', $router->id) }}">
                        {{ $router->name }}
                    </a>
                @elsecanShow
                    {{ $router->name }}
                @endcanShow
            @endforeach
        </td>
    </tr>
    @endcanAccessAny
    @canAccess(App\Models\PhysicalServer::class)
    <tr>
        <th>
            {{ trans('cruds.cluster.fields.physical_servers') }}
        </th>
        <td colspan="6">
            @foreach($cluster->physicalServers as $server)
                @canShow($server)
                    <a href="{{ route('admin.physical-servers.show', $server->id) }}">
                        {{ $server->name }}
                    </a>
                @elsecanShow
                    {{ $server->name }}
                @endcanShow
            @endforeach
        </td>
    </tr>
    @endcanAccess
    </tbody>
</table>
