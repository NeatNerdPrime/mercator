<table class="table table-bordered table-striped table-report" id="{{ $container->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.container.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $container->perimeter->name }} / @endif
                {{ $container->name }}
            </td>
            <th width="10%">
                <dt>{{ trans('cruds.container.fields.type') }}</dt>
            </th>
            <td width="20%">
                {{ $container->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.container.fields.attributes') }}
            </th>
            <td width="30%" colspan="2">
                @foreach(explode(" ", (string) $container->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.container.fields.description') }}
            </th>
            <td colspan="5">
                {!! $container->description !!}
            </td>
            <td width="10%">
                @if ($container->icon_id === null)
                <img src='/images/container.png' width='60' height='60'>
                @else
                <img src='{{ route('admin.documents.show', $container->icon_id) }}' width='60' height='60'>
                @endif
            </td>
        </tr>
    </tbody>
</table>
