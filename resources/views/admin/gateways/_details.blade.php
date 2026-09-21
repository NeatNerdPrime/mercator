<table class="table table-bordered table-striped">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.gateway.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $gateway->perimeter->name }} / @endif
                {{ $gateway->name }}
            </td>
            <th width="10%">
                {{ trans('cruds.gateway.fields.type') }}
            </th>
            <td width="10%">
                {{ $gateway->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.gateway.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $gateway->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.gateway.fields.description') }}
            </th>
            <td colspan="5">
                {!! $gateway->description !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.gateway.fields.authentification') }}
            </th>
            <td colspan="5">
                {{ $gateway->authentification }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.gateway.fields.ip') }}
            </th>
            <td colspan="5">
                {{ $gateway->ip }}
            </td>
        </tr>
    </tbody>
</table>
