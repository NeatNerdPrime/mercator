@props([
    'adminUser',
    'withLink' => false,
])
<table class="table table-bordered table-striped table-report" id="{{ $adminUser->getUID() }}">
    <tbody>
        <tr>
            <th width="10%">
                {{ trans('cruds.adminUser.fields.user_id') }}
            </th>
            <td colspan="3">
            @if ($withLink)
                @canShow($adminUser)
                <a href="{{ route('admin.admin-users.show', $adminUser->id) }}">{{ $adminUser->user_id }}</a>
                @elsecanShow
                {{ $adminUser->user_id }}
                @endcanShow
            @else
                {{ $adminUser->user_id }}
            @endif
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.adminUser.fields.firstname') }}
            </th>
            <td width="30%">
                {{ $adminUser->firstname }}
            </td>
            <th width="10%">
                {{ trans('cruds.adminUser.fields.lastname') }}
            </th>
            <td>
                {{ $adminUser->lastname }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('cruds.adminUser.fields.type') }}
            </th>
            <td>
                {{ $adminUser->type }}
            </td>
            <th>
                {{ trans('cruds.adminUser.fields.attributes') }}
            </th>
            <td>
                @php
                foreach(explode(" ",$adminUser->attributes) as $a)
                    echo "<div class='badge badge-info'>$a</div> ";
                @endphp
            </td>
        </tr>
        @canAccess(App\Models\Domain::class)
        <tr>
            <th>
                {{ trans('cruds.adminUser.fields.domain') }}
            </th>
            <td>
                @if ($adminUser->domain_id !== null)
                    @canShow($adminUser->domain)
                        <a href="{{ route('admin.domains.show', $adminUser->domain_id) }}">{{ $adminUser->domain->name }}</a>
                    @elsecanShow
                        {{ $adminUser->domain->name }}
                    @endcanShow
                @endif
            </td>
        </tr>
        @endcanAccess
        <tr>
            <th>
                {{ trans('cruds.adminUser.fields.description') }}
            </th>
            <td colspan="3">
                {!! $adminUser->description !!}
            </td>
        </tr>
    </tbody>
</table>
