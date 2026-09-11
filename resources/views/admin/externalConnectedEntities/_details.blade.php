@props([
    'network',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $externalConnectedEntity->getUID() }}">
    <tbody>
    <tr>
        <th width="10%">
            {{ trans('cruds.externalConnectedEntity.fields.name') }}
        </th>
        <td width="20%">
        @if($withLink)
            @canShow($externalConnectedEntity)
                <a href="{{ route('admin.external-connected-entities.show', $externalConnectedEntity) }}">{{ $externalConnectedEntity->name }}</a>
            @elsecanShow
                {{ $externalConnectedEntity->name }}
            @endcanShow
        @else
            {{ $externalConnectedEntity->name }}
        @endif
        </td>
        <th width="10%">
            {{ trans('cruds.externalConnectedEntity.fields.type') }}
        </th>
        <td width="20%">
            {{ $externalConnectedEntity->type }}
        </td>
        <th width="10%">
            {{ trans('cruds.externalConnectedEntity.fields.attributes') }}
        </th>
        <td width="30%">
            @foreach(explode(" ", (string) $externalConnectedEntity->attributes) as $attribute)
                @if(strlen(trim($attribute)) > 0)
                    <span class="badge badge-info">{{ $attribute }}</span>
                @endif
            @endforeach
        </td>
    </tr>
    <tr>
        <th width="10%">
            {{ trans('cruds.externalConnectedEntity.fields.description') }}
        </th>
        <td colspan="5">
            {!! $externalConnectedEntity->description !!}
        </td>

    </tr>
    </tbody>
</table>
