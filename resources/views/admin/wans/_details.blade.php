@props([
    'wan',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $wan->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.wan.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $wan->perimeter->name }} / @endif
            @if($withLink)
            @canShow($wan)
            <a href="{{ route('admin.wans.show', $wan) }}">{{ $wan->name }}</a>
            @elsecanShow
            {{ $wan->name }}
            @endcanShow
            @else
            {{ $wan->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.wan.fields.type') }}
            </th>
            <td width="20%">
                {{ $wan->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.wan.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $wan->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        @canAccess(App\Models\Man::class)
        <tr>
            <th>
                {{ trans('cruds.wan.fields.mans') }}
            </th>
            <td colspan="5">
                @foreach($wan->mans as $mans)
                @canShow($mans)
                <a href="{{ route('admin.mans.show', $mans) }}">{{ $mans->name }}</a>
                @elsecanShow
                {{ $mans->name }}
                @endcanShow
                @if(!$loop->last), @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
        @canAccess(App\Models\Lan::class)
        <tr>
            <th>
                {{ trans('cruds.wan.fields.lans') }}
            </th>
            <td colspan="5">
                @foreach($wan->lans as $lan)
                @canShow($lan)
                <a href="{{ route('admin.lans.show', $lan) }}">{{ $lan->name }}</a>
                @elsecanShow
                {{ $lan->name }}
                @endcanShow
                @if(!$loop->last), @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>
