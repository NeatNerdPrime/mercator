@props([
    'network',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $subnetwork->getUID() }}">
    <tbody>
    <tr>
        <th width='10%'>
            {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.subnetwork.fields.name') }}
        </th>
        <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $subnetwork->perimeter->name }} / @endif
        @if ($withLink)
            @canShow($subnetwork)
                <a href="{{ route('admin.subnetworks.show', $subnetwork) }}">{{ $subnetwork->name }}</a>
            @elsecanShow
                {{ $subnetwork->name }}
            @endcanShow
        @else
            {{ $subnetwork->name }}
        @endif
        </td>
        <th width="10%">
            {{ trans('cruds.subnetwork.fields.type') }}
        </th>
        <td width="20%">
            {{ $subnetwork->type }}
        </td>
        <th width="10%">
            {{ trans('cruds.subnetwork.fields.attributes') }}
        </th>
        <td width="30%">
            @foreach(explode(" ", $subnetwork->attributes) as $attribute)
                <span class="badge badge-info">{{ $attribute }}</span>
            @endforeach
        </td>
    </tr>
    <tr>
        <th>
            {{ trans('cruds.subnetwork.fields.description') }}
        </th>
        <td colspan="5">
            {!! $subnetwork->description !!}
        </td>
    </tr>
    </tbody>
</table>
