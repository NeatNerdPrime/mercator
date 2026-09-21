@props([
    'macroProcessus',
    'withLink' => false,
])

<table class="table table-bordered table-striped table-report" id="{{ $macroProcessus->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ auth()->user()->hasMultiplePerimeters() ? trans('cruds.perimeter.title_short').' / ' : '' }}{{ trans('cruds.macroProcessus.fields.name') }}
            </th>
            <td width="20%">
                @if (auth()->user()->hasMultiplePerimeters()){{ $macroProcessus->perimeter->name }} / @endif
            @if($withLink ?? false)
                @canShow($macroProcessus)
                    <a href="{{ route('admin.macro-processuses.show', $macroProcessus->id) }}">
                        {{ $macroProcessus->name }}
                    </a>
                @elsecanShow
                    {{ $macroProcessus->name }}
                @endcanShow
            @else
                {{ $macroProcessus->name }}
            @endif
            </td>
            <th width="10%">
                {{ trans('cruds.macroProcessus.fields.type') }}
            </th>
            <td width="20%">
                {{ $macroProcessus->type }}
            </td>
            <th width="10%">
                {{ trans('cruds.macroProcessus.fields.attributes') }}
            </th>
            <td width="30%">
                @foreach(explode(" ", (string) $macroProcessus->attributes) as $attribute)
                    @if(strlen(trim($attribute)) > 0)
                        <span class="badge badge-info">{{ $attribute }}</span>
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.macroProcessus.fields.description') }}
            </th>
            <td colspan="5">
                {!! $macroProcessus->description !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.macroProcessus.fields.io_elements') }}
            </th>
            <td colspan="5">
                {!! $macroProcessus->io_elements !!}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.macroProcessus.fields.security_need') }}
            </th>
            <td colspan="5">
            {{ trans('global.confidentiality') }} :
                @if ($macroProcessus->security_need_c==0){{ trans('global.none') }}@endif
                @if ($macroProcessus->security_need_c==1)<span class="veryLowRisk">{{ trans('global.low') }}</span>@endif
                @if ($macroProcessus->security_need_c==2)<span class="lowRisk">{{ trans('global.medium') }}</span>@endif
                @if ($macroProcessus->security_need_c==3)<span class="mediumRisk">{{ trans('global.strong') }}</span>@endif
                @if ($macroProcessus->security_need_c==4)<span class="highRisk">{{ trans('global.very_strong') }}</span>@endif
            &nbsp;
            {{ trans('global.integrity') }} :
                @if ($macroProcessus->security_need_i==0){{ trans('global.none') }}@endif
                @if ($macroProcessus->security_need_i==1)<span class="veryLowRisk">{{ trans('global.low') }}</span>@endif
                @if ($macroProcessus->security_need_i==2)<span class="lowRisk">{{ trans('global.medium') }}</span>@endif
                @if ($macroProcessus->security_need_i==3)<span class="mediumRisk">{{ trans('global.strong') }}</span>@endif
                @if ($macroProcessus->security_need_i==4)<span class="highRisk">{{ trans('global.very_strong') }}</span>@endif
            &nbsp;
            {{ trans('global.availability') }} :
                @if ($macroProcessus->security_need_a==0){{ trans('global.none') }}@endif
                @if ($macroProcessus->security_need_a==1)<span class="veryLowRisk">{{ trans('global.low') }}</span>@endif
                @if ($macroProcessus->security_need_a==2)<span class="lowRisk">{{ trans('global.medium') }}</span>@endif
                @if ($macroProcessus->security_need_a==3)<span class="mediumRisk">{{ trans('global.strong') }}</span>@endif
                @if ($macroProcessus->security_need_a==4)<span class="highRisk">{{ trans('global.very_strong') }}</span>@endif
            &nbsp;
            {{ trans('global.tracability') }} :
                @if ($macroProcessus->security_need_t==0){{ trans('global.none') }}@endif
                @if ($macroProcessus->security_need_t==1)<span class="veryLowRisk">{{ trans('global.low') }}</span>@endif
                @if ($macroProcessus->security_need_t==2)<span class="lowRisk">{{ trans('global.medium') }}</span>@endif
                @if ($macroProcessus->security_need_t==3)<span class="mediumRisk">{{ trans('global.strong') }}</span>@endif
                @if ($macroProcessus->security_need_t==4)<span class="highRisk">{{ trans('global.very_strong') }}</span>@endif
            @if (config('mercator-config.parameters.security_need_auth'))
            &nbsp;
            {{ trans('global.authenticity') }} :
                @if ($macroProcessus->security_need_auth==0){{ trans('global.none') }}@endif
                @if ($macroProcessus->security_need_auth==1)<span class="veryLowRisk">{{ trans('global.low') }}</span>@endif
                @if ($macroProcessus->security_need_auth==2)<span class="lowRisk">{{ trans('global.medium') }}</span>@endif
                @if ($macroProcessus->security_need_auth==3)<span class="mediumRisk">{{ trans('global.strong') }}</span>@endif
                @if ($macroProcessus->security_need_auth==4)<span class="highRisk">{{ trans('global.very_strong') }}</span>@endif
            @endif
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.macroProcessus.fields.owner') }}
            </th>
            <td colspan="5">
                {{ $macroProcessus->owner }}
            </td>
        </tr>
        @canAccess(App\Models\Process::class)
        <tr>
            <th>
                {{ trans('cruds.macroProcessus.fields.processes') }}
            </th>
            <td colspan="5">
                @foreach($macroProcessus->processes as $process)
                    @canShow($process)
                        <a href="{{ route('admin.processes.show', $process->id) }}">
                            {{ $process->name }}
                        </a>
                    @elsecanShow
                        {{ $process->name }}
                    @endcanShow
                    @if(!$loop->last)
                    ,
                    @endif
                @endforeach
            </td>
        </tr>
        @endcanAccess
    </tbody>
</table>



