@props([
    'applicationService',
    'withLink' => false,
    'hasMultiplePerimeters' => null,
])
@php($hasMultiplePerimeters ??= auth()->user()->hasMultiplePerimeters())
<table class="table table-bordered table-striped table-report" id="{{ $applicationService->getUID() }}">
    <tbody>
        <tr>
            <th width='10%'>
                {{ $hasMultiplePerimeters ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.applicationService.fields.name') }}
            </th>
            <td width="20%">
                @if ($hasMultiplePerimeters){{ $applicationService->perimeter->nom }} / @endif
            @if ($withLink)
                @canShow($applicationService)
                <a href='{{ route("admin.application-services.show", $applicationService->id) }}'>{{ $applicationService->name }}</a>
                @elsecanShow
                    {{ $applicationService->name }}
                @endcanShow
            @else
                {{ $applicationService->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.applicationService.fields.type') }}
            </th>
            <td width="20%">
                {{ $applicationService->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.applicationService.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $applicationService->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.applicationService.fields.description') }}
            </th>
            <td colspan="5">
                {!! $applicationService->description !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.applicationService.fields.exposition') }}
            </th>
            <td colspan="5">
                {{ $applicationService->exposition }}
            </td>
        </tr>
        @canAccess(App\Models\ApplicationModule::class)
        <tr>
            <th>
                {{ trans('cruds.applicationService.fields.modules') }}
            </th>
            <td colspan="5">
                @foreach($applicationService->modules as $module)
                    @canShow($module)
                        <a href="{{ route('admin.application-modules.show', $module->id) }}">{{ $module->name }}</a>
                    @elsecanShow
                        {{ $module->name }}
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
