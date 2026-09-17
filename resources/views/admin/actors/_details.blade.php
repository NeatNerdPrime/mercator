@props([
    'actor',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $actor->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.actor.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $actor->perimeter->nom }} / @endif
            @if($withLink)
            @canShow($actor)
            <a href="{{ route('admin.actors.show', $actor->id) }}">{{ $actor->name }}</a>
            @elsecanShow
            {{ $actor->name }}
            @endcanShow
            @else
                {{ $actor->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.actor.fields.type') }}
            </th>
            <td width="20%">
                {{ $actor->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.actor.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $actor->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.actor.fields.contact') }}
            </th>
            <td colspan="5">
                {{ $actor->contact }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.actor.fields.nature') }}
            </th>
            <td colspan="5">
                {{ $actor->nature }}
            </td>
        </tr>
        @canAccess(App\Models\Operation::class)
        <tr>
            <th>
                {{ trans('cruds.actor.fields.operations') }}
            </th>
            <td colspan="5">
            @foreach($actor->operations as $operation)
                @canShow($operation)
                    <a href="{{ route('admin.operations.show', $operation->id) }}">{{ $operation->name }}</a>
                @elsecanShow
                    {{ $operation->name }}
                @endcanShow
                @if(!$loop->last)
                ,
                @endif
            @endforeach
            </td>
        </tr>
        @endcanAccess
        @if($actor->graphs()->count()>0)
        <tr>
            <th>
                <span style="border: 2px solid grey;
                     color: darkred;
                     padding: 6px 14px;
                     border-radius: 6px;
                     display: inline-flex;
                     align-items: center;
                     gap: 8px;
                     font-weight: 600;
                     background: #eff6ff;">
                    <i class="bi bi-diagram-2-fill" style="font-size: 1.3em;"></i>
                    <span style="color: black;">BPMN</span>
                </span>
            </th>
            <td style="vertical-align: middle;" colspan="5">
                @foreach($actor->graphs() as $graph)
                    @canShow($graph)
                        <a href="{{ route('admin.bpmn.show', $graph->id) }}">
                            {{ $graph->name }}
                        </a>
                    @elsecanShow
                        {{ $graph->name }}
                    @endcanShow
                    @if (!$loop->last)
                    ,
                    @endif
                @endforeach
            </td>
        </tr>
        @endif
    </tbody>
</table>
