@props([
    'applicationFlow',
    'withLink' => false,
])
<table class="table table-bordered table-striped table-report">
    <tbody>
    <tr>
        <th width="10%">
            {{ trans('cruds.applicationFlow.fields.name') }}
        </th>
        <td width="30%">
        @if ($withLink)
        @canShow($applicationFlow)
        <a href="{{ route('admin.application-flows.show', $applicationFlow->id) }}">{{ $applicationFlow->name }}</a>
        @elsecanShow
        {{ $applicationFlow->name }}
        @endcanShow
        @else
            {{ $applicationFlow->name }}
        @endif
        </td>
        <th width="10%">
            {{ trans('cruds.applicationFlow.fields.nature') }}
        </th>
        <td width="20%">
            {{ $applicationFlow->nature }}
        </td>
        <th width="10%">
            {{ trans('cruds.applicationFlow.fields.attributes') }}
        </th>
        <td width="20%">
            @foreach(explode(" ",$applicationFlow->attributes) as $attribute)
                <span class="badge badge-info">{{ $attribute }}</span>
            @endforeach
        </td>
    </tr>
    <tr>
        <th>
            {{ trans('cruds.applicationFlow.fields.description') }}
        </th>
        <td colspan="5">
            {!! $applicationFlow->description !!}
        </td>
    </tr>

    @canAccessAny(App\Models\Application::class, App\Models\ApplicationService::class, App\Models\ApplicationModule::class, App\Models\Database::class)
    <tr>
        <th>
            {{ trans('cruds.applicationFlow.fields.source') }}
        </th>
        <td colspan="1">
            @if ($applicationFlow->applicationSource!=null)
                @canShow($applicationFlow->applicationSource)
                    <a href="{{ route('admin.applications.show',$applicationFlow->applicationSource->id) }}">{{ $applicationFlow->applicationSource->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->applicationSource->name }}
                @endcanShow
                [Application]
            @endif
            @if($applicationFlow->serviceSource!=null)
                @canShow($applicationFlow->serviceSource)
                    <a href="{{ route('admin.application-services.show', $applicationFlow->serviceSource->id) }}">{{ $applicationFlow->serviceSource->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->serviceSource->name }}
                @endcanShow
                [Service]
            @endif
            @if ($applicationFlow->moduleSource!=null)
                @canShow($applicationFlow->moduleSource)
                    <a href="{{ route('admin.application-modules.show', $applicationFlow->moduleSource->id) }}">{{ $applicationFlow->moduleSource->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->moduleSource->name }}
                @endcanShow
                [Module]
            @endif
            @if ($applicationFlow->databaseSource!=null)
                @canShow($applicationFlow->databaseSource)
                    <a href="{{ route('admin.databases.show',$applicationFlow->databaseSource->id) }}">{{ $applicationFlow->databaseSource->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->databaseSource->name }}
                @endcanShow
                [Database]
            @endif
        </td>

        <th>
            {{ trans('cruds.applicationFlow.fields.destination') }}
        </th>
        <td colspan="3">
            @if ($applicationFlow->applicationDest!=null)
                @canShow($applicationFlow->applicationDest)
                    <a href="{{ route('admin.applications.show',$applicationFlow->applicationDest->id) }}">{{ $applicationFlow->applicationDest->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->applicationDest->name }}
                @endcanShow
                [Application]
            @endif
            @if ($applicationFlow->serviceDest!=null)
                @canShow($applicationFlow->serviceDest)
                    <a href="{{ route('admin.application-services.show', $applicationFlow->serviceDest->id) }}">{{ $applicationFlow->serviceDest->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->serviceDest->name }}
                @endcanShow
                [Service]
            @endif
            @if ($applicationFlow->moduleDest!=null)
                @canShow($applicationFlow->moduleDest)
                    <a href="{{ route('admin.application-modules.show', $applicationFlow->moduleDest->id) }}">{{ $applicationFlow->moduleDest->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->moduleDest->name }}
                @endcanShow
                [Module]
            @endif
            @if ($applicationFlow->databaseDest!=null)
                @canShow($applicationFlow->databaseDest)
                    <a href="{{ route('admin.databases.show',$applicationFlow->databaseDest->id) }}">{{ $applicationFlow->databaseDest->name }}</a>
                @elsecanShow
                    {{ $applicationFlow->databaseDest->name }}
                @endcanShow
                [Database]
            @endif
        </td>
    </tr>
    @endcanAccessAny
    @canAccess(App\Models\Information::class)
    <tr>
        <th>
            {{ trans('cruds.applicationFlow.fields.information') }}
        </th>
        <td colspan="5">
            @foreach($applicationFlow->informations as $info)
                @canShow($info)
                    <a href="{{ route('admin.information.show',$info->id) }}">{{ $info->name }}</a>
                @elsecanShow
                    {{ $info->name }}
                @endcanShow
                @if (!$loop->last) , @endif
            @endforeach
        </td>
    </tr>
    @endcanAccess
    <tr>
        <th>
            {{ trans('cruds.applicationFlow.fields.crypted') }}
        </th>
        <td>
            @if ($applicationFlow->crypted==0)
                Non
            @elseif ($applicationFlow->crypted==1)
                Oui
            @endif
        </td>
        <th>
            {{ trans('cruds.applicationFlow.fields.bidirectional') }}
        </th>
        <td colspan="3">
            @if ($applicationFlow->bidirectional==0)
                Non
            @elseif ($applicationFlow->bidirectional==1)
                Oui
            @endif
        </td>
    </tr>
    </tbody>
</table>
