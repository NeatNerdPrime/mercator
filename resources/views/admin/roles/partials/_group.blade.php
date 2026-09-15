@php($indices ??= [0, 4, 1, 3, 2])
<div class="col-md-2">
    <div class="form-check">
        @if(!empty($label))
            <label>{{ $label }}@if(!empty($deprecated)) <b>({{ trans('global.deprecated') }})</b>@endif</label>
        @endif
        @foreach($indices as $i)
            @if(isset($permission['actions'][$i]))
                @include('admin.roles.partials._checkbox', ['action' => $permission['actions'][$i]])
            @endif
        @endforeach
    </div>
</div>
